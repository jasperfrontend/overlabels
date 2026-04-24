<?php

namespace App\Http\Controllers\Settings;

use App\Events\ControlValueUpdated;
use App\Http\Controllers\Controller;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use App\Services\External\FourthwallApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class FourthwallIntegrationController extends Controller
{
    private const SERVICE = 'fourthwall';

    private const WEBHOOK_EVENT_TYPES = ['DONATION'];

    private const OAUTH_STATE_SESSION_KEY = 'fw_oauth_state';

    public function __construct(
        private readonly ExternalControlService $controlService,
        private readonly FourthwallApiClient $apiClient,
    ) {}

    public function show(): Response
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', self::SERVICE)
            ->first();

        $settings = $integration?->settings ?? [];

        return Inertia::render('settings/integrations/fourthwall', [
            'integration' => $integration ? [
                'connected' => true,
                'enabled' => $integration->enabled,
                'test_mode' => $integration->test_mode,
                'last_received_at' => $integration->last_received_at?->toIso8601String(),
                'settings' => $settings,
                'donations_seed_set' => ! empty($settings['donations_seed_set']),
                'donations_seed_value' => $settings['donations_seed_value'] ?? null,
            ] : [
                'connected' => false,
                'enabled' => false,
                'test_mode' => false,
                'last_received_at' => null,
                'settings' => [],
                'donations_seed_set' => false,
                'donations_seed_value' => null,
            ],
        ]);
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

        $isNew = ! ExternalIntegration::where('user_id', $user->id)
            ->where('service', self::SERVICE)
            ->exists();

        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => self::SERVICE],
            ['enabled' => true]
        );

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
            // next attempt starts clean. Reconnects keep their previous state.
            if ($isNew) {
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

        if ($isNew) {
            $driver = ExternalServiceRegistry::driver(self::SERVICE);
            $this->controlService->provision($user, $driver);
        }

        return redirect()->route('settings.integrations.fourthwall.show')
            ->with('success', 'Fourthwall connected successfully.');
    }

    public function setTestMode(Request $request): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', self::SERVICE)
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $validated = $request->validate(['test_mode' => 'required|boolean']);

        $integration->update(['test_mode' => $validated['test_mode']]);

        if (! $validated['test_mode']) {
            $settings = $integration->settings ?? [];
            $seedValue = (string) ($settings['donations_seed_value'] ?? 0);

            $controls = OverlayControl::where('user_id', $user->id)
                ->where('source', self::SERVICE)
                ->where('source_managed', true)
                ->with('template')
                ->get();

            foreach ($controls as $control) {
                $resetValue = match ($control->key) {
                    'donations_received' => $seedValue,
                    default => in_array($control->type, ['counter', 'number']) ? '0' : '',
                };

                $control->update(['value' => $resetValue]);

                $overlaySlug = $control->overlay_template_id
                    ? ($control->template?->slug ?? '')
                    : '';

                ControlValueUpdated::dispatch(
                    $overlaySlug,
                    $control->broadcastKey(),
                    $control->type,
                    $resetValue,
                    $user->twitch_id,
                );
            }
        }

        return response()->json(['test_mode' => $integration->test_mode]);
    }

    public function seedDonationCount(Request $request): JsonResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', self::SERVICE)
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Not connected.'], 404);
        }

        $settings = $integration->settings ?? [];

        if (! empty($settings['donations_seed_set'])) {
            return response()->json(['error' => 'Starting count has already been set.'], 403);
        }

        $validated = $request->validate([
            'initial_count' => 'required|integer|min:0|max:9999999',
        ]);

        OverlayControl::where('user_id', $user->id)
            ->where('source', self::SERVICE)
            ->where('key', 'donations_received')
            ->where('source_managed', true)
            ->update(['value' => (string) $validated['initial_count']]);

        $integration->settings = array_merge($settings, [
            'donations_seed_set' => true,
            'donations_seed_value' => $validated['initial_count'],
        ]);
        $integration->save();

        return response()->json([
            'donations_seed_set' => true,
            'donations_seed_value' => $validated['initial_count'],
        ]);
    }

    public function disconnect(): RedirectResponse
    {
        $user = auth()->user();

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', self::SERVICE)
            ->first();

        if ($integration) {
            $webhookId = $integration->getCredentialsDecrypted()['webhook_id'] ?? null;
            if ($webhookId) {
                $this->bestEffortDeregister($integration, $webhookId);
            }

            $this->controlService->deprovision($user, self::SERVICE);
            $integration->delete();
        }

        return redirect()->route('settings.integrations.index')
            ->with('success', 'Fourthwall disconnected.');
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
