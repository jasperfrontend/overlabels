<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Random\RandomException;

class StreamLabsIntegrationController extends DonationIntegrationController
{
    protected function service(): string
    {
        return 'streamlabs';
    }

    /**
     * Streamlabs is pulled over Socket.IO by the listener accessory rather than
     * pushed to us, so there is no inbound webhook URL for the user to copy.
     */
    protected function showsWebhookUrl(): bool
    {
        return false;
    }

    /**
     * Streamlabs API v2.0. v1.0 is deprecated (dev.streamlabs.com/docs/getting-started):
     * every endpoint keeps its path, the access token may only travel as a Bearer
     * header, and an app registered under v1.0 has to be registered again to use
     * v2.0 - so the client id and secret in the environment must come from a v2.0
     * registration, or the authorize step 400s before the user sees anything.
     */
    private const string API_BASE = 'https://streamlabs.com/api/v2.0';

    private const string OAUTH_STATE_KEY = 'streamlabs_oauth_state';

    /**
     * Redirect the user to the Streamlabs OAuth authorization page.
     *
     * @throws RandomException
     */
    public function redirect(Request $request): RedirectResponse
    {
        // `state` is the CSRF guard the OAuth flow gives us: the callback only
        // accepts a code that arrives with the value this session handed out.
        $state = bin2hex(random_bytes(20));
        $request->session()->put(self::OAUTH_STATE_KEY, $state);

        $params = http_build_query([
            'client_id' => config('services.streamlabs.client_id'),
            'redirect_uri' => url('/auth/callback/streamlabs'),
            'response_type' => 'code',
            'scope' => 'socket.token donations.read donations.create',
            'state' => $state,
        ]);

        return redirect(self::API_BASE."/authorize?$params");
    }

    /**
     * Handle the OAuth callback from Streamlabs.
     *
     * @throws ConnectionException|RandomException
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');

        if (! $code) {
            return redirect()->route('settings.integrations.streamlabs.show')
                ->with('error', 'StreamLabs authorization was cancelled.');
        }

        $expectedState = (string) $request->session()->pull(self::OAUTH_STATE_KEY, '');
        $state = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            Log::warning('StreamLabs OAuth callback state mismatch');

            return redirect()->route('settings.integrations.streamlabs.show')
                ->with('error', 'StreamLabs authorization could not be verified. Please try again.');
        }

        // Exchange the authorization code for an access token. Codes are single
        // use and expire after five minutes, so this happens right here.
        $tokenResponse = Http::asForm()->post(self::API_BASE.'/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.streamlabs.client_id'),
            'client_secret' => config('services.streamlabs.client_secret'),
            'redirect_uri' => url('/auth/callback/streamlabs'),
            'code' => $code,
        ]);

        if (! $tokenResponse->ok()) {
            Log::error('StreamLabs token exchange failed', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);

            return redirect()->route('settings.integrations.streamlabs.show')
                ->with('error', 'Failed to connect to StreamLabs. Please try again.');
        }

        $tokenData = $tokenResponse->json();
        $accessToken = $tokenData['access_token'] ?? null;

        if (! $accessToken) {
            return redirect()->route('settings.integrations.streamlabs.show')
                ->with('error', 'StreamLabs did not return an access token.');
        }

        // Fetch the socket token for the Socket.IO listener. v2.0 accepts the
        // access token as a Bearer header only, never as a query parameter.
        $socketResponse = Http::withToken($accessToken)
            ->get(self::API_BASE.'/socket/token');

        if (! $socketResponse->ok()) {
            Log::error('StreamLabs socket token fetch failed', [
                'status' => $socketResponse->status(),
                'body' => $socketResponse->body(),
            ]);

            return redirect()->route('settings.integrations.streamlabs.show')
                ->with('error', 'Connected to StreamLabs but failed to get socket token.');
        }

        $socketToken = $socketResponse->json('socket_token');

        // Generate a per-integration secret for webhook verification
        $listenerSecret = bin2hex(random_bytes(32));

        $user = auth()->user();

        $integration = $this->connectIntegration($user);

        // v2.0 also hands back a refresh_token. Nothing here uses the access
        // token after this request (the listener runs on the socket token), so
        // it is stored for a future need rather than refreshed on a schedule.
        $integration->setCredentialsEncrypted([
            'access_token' => $accessToken,
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'socket_token' => $socketToken,
            'listener_secret' => $listenerSecret,
        ]);

        $integration->enabled = true;
        $integration->save();

        return redirect()->route('settings.integrations.streamlabs.show')
            ->with('success', 'StreamLabs connected successfully.');
    }
}
