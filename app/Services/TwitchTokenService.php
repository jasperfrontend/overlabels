<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwitchTokenService
{
    private string $clientId;

    private string $clientSecret;

    private string $tokenUrl = 'https://id.twitch.tv/oauth2/token';

    private string $validateUrl = 'https://id.twitch.tv/oauth2/validate';

    private string $revokeUrl = 'https://id.twitch.tv/oauth2/revoke';

    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
        $this->clientSecret = config('services.twitch.client_secret');
    }

    /**
     * Validate if the current access token is still valid
     */
    public function validateToken(string $accessToken): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
            ])->get($this->validateUrl);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Token validation failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Hand an access token back to Twitch, which invalidates it and the refresh
     * token issued alongside it, and drops Overlabels off the user's connections
     * list at twitch.tv/settings/connections.
     *
     * Called when an account is erased. Until this existed, deleting an
     * Overlabels account left a live grant pointing at nothing, and only the
     * user could clear it.
     *
     * Twitch answers 200 for a token it revoked and 400 for one it does not
     * recognise. Both mean the token is not usable, so both count as done.
     */
    public function revokeToken(string $accessToken): bool
    {
        try {
            $response = Http::asForm()->post($this->revokeUrl, [
                'client_id' => $this->clientId,
                'token' => $accessToken,
            ]);

            if ($response->successful() || $response->status() === 400) {
                return true;
            }

            Log::warning('Twitch token revocation returned an unexpected status', [
                'status' => $response->status(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::warning('Twitch token revocation failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Refresh the access token using the refresh token
     */
    public function refreshAccessToken(string $refreshToken): ?array
    {
        try {
            $response = Http::asForm()->post($this->tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                    'expires_in' => $data['expires_in'] ?? 3600,
                    'scope' => $data['scope'] ?? null,
                ];
            }

            Log::error('Failed to refresh token', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Token refresh exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Check if the token needs refresh and refresh if necessary
     */
    public function ensureValidToken(User $user): bool
    {
        // Check if token is expired based on timestamp
        if ($user->token_expires_at && $user->token_expires_at->isPast()) {
            return $this->refreshUserToken($user);
        }

        // No readable access token: either the account never had one, or the
        // stored value could not be decrypted (see App\Casts\EncryptedOrNull).
        // Either way the refresh token is the only way back, and if that is
        // unreadable too refreshUserToken() reports failure and the user is
        // sent to re-authorize. Previously this passed null straight into
        // validateToken(string) and raised a TypeError.
        if (! $user->access_token) {
            return $this->refreshUserToken($user);
        }

        // Validate token with Twitch
        if (! $this->validateToken($user->access_token)) {
            return $this->refreshUserToken($user);
        }

        return true;
    }

    /**
     * Refresh and update the user's token
     */
    public function refreshUserToken(User $user): bool
    {
        if (! $user->refresh_token) {
            Log::error('No refresh token available for user', ['user_id' => $user->id]);

            return false;
        }

        $tokenData = $this->refreshAccessToken($user->refresh_token);

        if (! $tokenData) {
            Log::error('Failed to refresh token for user', ['user_id' => $user->id]);

            return false;
        }

        $updates = [
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
        ];

        // Capture granted scopes when Twitch returns them. Twitch always includes
        // scope on refresh, but guard anyway - absent response shouldn't clobber
        // stored scopes (which would falsely trigger the stale-scope banner).
        if (isset($tokenData['scope'])) {
            $newScopes = TwitchScopeService::sanitizeScopeList($tokenData['scope']);
            $updates['twitch_scopes'] = $newScopes;

            // Warn when scopes shrink - the banner will start showing and the
            // user may wonder why; a log line makes it traceable.
            $priorScopes = $user->twitch_scopes ?? [];
            if (is_array($priorScopes)) {
                $dropped = array_diff($priorScopes, $newScopes);
                if (! empty($dropped)) {
                    Log::warning('Twitch scopes shrunk on refresh', [
                        'user_id' => $user->id,
                        'dropped' => array_values($dropped),
                    ]);
                }
            }
        }

        $user->update($updates);

        Log::info('Successfully refreshed token for user', ['user_id' => $user->id]);

        return true;
    }
}
