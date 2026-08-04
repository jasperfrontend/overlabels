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
     * Redirect the user to StreamLabs OAuth authorization page.
     */
    public function redirect(): RedirectResponse
    {
        $params = http_build_query([
            'client_id' => config('services.streamlabs.client_id'),
            'redirect_uri' => url('/auth/callback/streamlabs'),
            'response_type' => 'code',
            'scope' => 'socket.token donations.read donations.create',
        ]);

        return redirect("https://www.streamlabs.com/api/v1.0/authorize?$params");
    }

    /**
     * Handle the OAuth callback from StreamLabs.
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

        // Exchange authorization code for access token
        $tokenResponse = Http::post('https://streamlabs.com/api/v1.0/token', [
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

        // Fetch socket token for the Socket.IO listener
        $socketResponse = Http::withToken($accessToken)
            ->get('https://streamlabs.com/api/v1.0/socket/token');

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

        $integration->setCredentialsEncrypted([
            'access_token' => $accessToken,
            'socket_token' => $socketToken,
            'listener_secret' => $listenerSecret,
        ]);

        $integration->enabled = true;
        $integration->save();

        return redirect()->route('settings.integrations.streamlabs.show')
            ->with('success', 'StreamLabs connected successfully.');
    }
}
