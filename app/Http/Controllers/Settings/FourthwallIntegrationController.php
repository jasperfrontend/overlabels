<?php

namespace App\Http\Controllers\Settings;

use App\Models\ExternalIntegration;
use App\Services\External\ExternalControlService;
use App\Services\External\FourthwallApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FourthwallIntegrationController extends DonationIntegrationController
{
    private const WEBHOOK_EVENT_TYPES = ['DONATION'];

    private const OAUTH_STATE_SESSION_KEY = 'fw_oauth_state';

    public function __construct(
        ExternalControlService $controlService,
        private readonly FourthwallApiClient $apiClient,
    ) {
        parent::__construct($controlService);
    }

    protected function service(): string
    {
        return 'fourthwall';
    }

    /**
     * Fourthwall registers its own webhook through the API during the OAuth
     * callback, so the user never has to copy a URL anywhere.
     */
    protected function showsWebhookUrl(): bool
    {
        return false;
    }

    /**
     * Redirect the user to Fourthwall's shop-scoped authorize URL.
     * The URL is pre-baked in the env (client id is path-embedded, not a query param)
     * and trails with `?redirect_uri=` for us to append to.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $authUrl = config('services.fourthwall.auth_url');
        $redirectUrl = config('services.fourthwall.redirect_url');

        if (! $authUrl || ! $redirectUrl) {
            Log::error('Fourthwall integration is not configured', [
                'auth_url_present' => (bool) $authUrl,
                'redirect_url_present' => (bool) $redirectUrl,
            ]);

            return redirect()->route('settings.integrations.fourthwall.show')
                ->with('error', 'Fourthwall is not configured on this server. Contact the administrator.');
        }

        $state = Str::random(40);
        $request->session()->put(self::OAUTH_STATE_SESSION_KEY, $state);

        $url = $authUrl.urlencode($redirectUrl).'&state='.urlencode($state);

        return redirect()->away($url);
    }

    /**
     * Handle the OAuth callback from Fourthwall.
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $expectedState = $request->session()->pull(self::OAUTH_STATE_SESSION_KEY);

        if (! $code) {
            return redirect()->route('settings.integrations.fourthwall.show')
                ->with('error', 'Fourthwall authorization was cancelled.');
        }

        if (! $expectedState || ! is_string($state) || ! hash_equals($expectedState, $state)) {
            return redirect()->route('settings.integrations.fourthwall.show')
                ->with('error', 'Invalid OAuth state. Please try connecting again.');
        }

        try {
            $tokenData = $this->apiClient->exchangeCode($code);
        } catch (ConnectionException|RequestException $e) {
            Log::error('Fourthwall token exchange failed', ['error' => $e->getMessage()]);

            return redirect()->route('settings.integrations.fourthwall.show')
                ->with('error', 'Failed to connect to Fourthwall. Please try again.');
        }

        $accessToken = $tokenData['access_token'] ?? null;
        $refreshToken = $tokenData['refresh_token'] ?? null;

        if (! $accessToken) {
            return redirect()->route('settings.integrations.fourthwall.show')
                ->with('error', 'Fourthwall did not return an access token.');
        }

        $user = auth()->user();

        $isNew = ! $this->integration($user);

        $integration = $this->connectIntegration($user);

        // Preserve any existing webhook_id so we can clean it up before registering a new one.
        $previousCredentials = $integration->getCredentialsDecrypted();
        $previousWebhookId = $previousCredentials['webhook_id'] ?? null;

        $integration->setCredentialsEncrypted([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $this->expiresAt($tokenData['expires_in'] ?? null),
        ]);
        $integration->enabled = true;
        $integration->save();

        if ($previousWebhookId) {
            $this->bestEffortDeregister($integration, $previousWebhookId);
        }

        $webhookUrl = url("/api/webhooks/fourthwall/{$integration->webhook_token}");

        try {
            $webhookData = $this->apiClient->registerWebhook($integration, $webhookUrl, self::WEBHOOK_EVENT_TYPES);
        } catch (ConnectionException|RequestException $e) {
            $responseBody = $e instanceof RequestException ? $e->response->body() : null;
            $responseStatus = $e instanceof RequestException ? $e->response->status() : null;

            Log::error('Fourthwall webhook registration failed', [
                'user_id' => $user->id,
                'status' => $responseStatus,
                'response_body' => $responseBody,
                'exception' => $e->getMessage(),
            ]);

            // Fresh connects with no webhook are useless - roll the row back so the
            // next attempt starts clean. The controls provisioned on the way in have
            // to go with it, or a failed first connect leaves six orphaned
            // service-managed controls that nothing can write and the user cannot
            // delete. Reconnects keep their previous state, controls included.
            if ($isNew) {
                $this->controlService->deprovision($user, $this->service());
                $integration->delete();
            }

            $flashMessage = $responseStatus === 403
                ? 'Fourthwall accepted the login but refused to register the webhook (403 Forbidden). Your app likely needs the webhook_write scope enabled - check the app settings in Fourthwall and reconnect.'
                : 'Connected to Fourthwall, but registering the webhook failed. Please try again.';

            return redirect()->route('settings.integrations.fourthwall.show')
                ->with('error', $flashMessage);
        }

        $credentials = $integration->getCredentialsDecrypted();
        $credentials['webhook_id'] = $webhookData['id'] ?? null;
        // Intentionally not storing a per-webhook secret: Fourthwall's registerWebhook
        // response does not include one, and inbound verification uses the app-level
        // FW_HMAC (see FourthwallServiceDriver::verifyRequest).
        $integration->setCredentialsEncrypted($credentials);
        $integration->save();

        return redirect()->route('settings.integrations.fourthwall.show')
            ->with('success', 'Fourthwall connected successfully.');
    }

    /**
     * Deregister the remote webhook before the integration row goes away.
     */
    protected function beforeDisconnect(ExternalIntegration $integration): void
    {
        $webhookId = $integration->getCredentialsDecrypted()['webhook_id'] ?? null;

        if ($webhookId) {
            $this->bestEffortDeregister($integration, $webhookId);
        }
    }

    /**
     * Attempt to deregister a webhook on Fourthwall's side. Failure is logged
     * but doesn't block progress - we'd rather leave an orphan webhook on the
     * remote shop than have the user stuck unable to disconnect.
     */
    private function bestEffortDeregister(ExternalIntegration $integration, string $webhookId): void
    {
        try {
            $this->apiClient->deregisterWebhook($integration, $webhookId);
        } catch (Throwable $e) {
            Log::warning('Fourthwall webhook deregistration failed', [
                'integration_id' => $integration->id,
                'webhook_id' => $webhookId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function expiresAt(?int $expiresIn): string
    {
        return now()->addSeconds($expiresIn ?: 300)->toIso8601String();
    }
}
