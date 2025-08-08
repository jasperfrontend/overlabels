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
                'Authorization' => "Bearer $accessToken"
            ])->get($this->validateUrl);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Token validation failed: ' . $e->getMessage());
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
                ];
            }

            Log::error('Failed to refresh token', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Token refresh exception: ' . $e->getMessage());
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

        // Validate token with Twitch
        if (!$this->validateToken($user->access_token)) {
            return $this->refreshUserToken($user);
        }

        return true;
    }

    /**
     * Refresh and update the user's token
     */
    public function refreshUserToken(User $user): bool
    {
        if (!$user->refresh_token) {
            Log::error('No refresh token available for user', ['user_id' => $user->id]);
            return false;
        }

        $tokenData = $this->refreshAccessToken($user->refresh_token);

        if (!$tokenData) {
            Log::error('Failed to refresh token for user', ['user_id' => $user->id]);
            return false;
        }

        // Update the user with new tokens
        $user->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
        ]);

        Log::info('Successfully refreshed token for user', ['user_id' => $user->id]);
        return true;
    }
}
