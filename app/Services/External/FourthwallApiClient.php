<?php

namespace App\Services\External;

use App\Models\ExternalIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Fourthwall Open API.
 *
 * Handles OAuth token exchange/refresh and the webhook management endpoints
 * we need on connect/disconnect. Intentionally small - implementation detail
 * of FourthwallServiceDriver and FourthwallIntegrationController, not public API.
 */
class FourthwallApiClient
{
    /**
     * Refresh the access token this many seconds before it actually expires.
     * Fourthwall access tokens live "a few minutes", so being paranoid is cheap.
     */
    private const REFRESH_SAFETY_WINDOW_SECONDS = 60;

    /**
     * Exchange an authorization code for access + refresh tokens.
     *
     * @return array{access_token:string, refresh_token:?string, expires_in:?int}
     *
     * @throws ConnectionException|RequestException
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->post($this->tokenEndpoint(), [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.fourthwall.client_id'),
                'client_secret' => config('services.fourthwall.client_secret'),
                'redirect_uri' => config('services.fourthwall.redirect_url'),
                'code' => $code,
            ])
            ->throw();

        return $response->json();
    }

    /**
     * Refresh an integration's access token in place and persist the new values.
     *
     * @throws ConnectionException|RequestException
     */
    public function refreshAccessToken(ExternalIntegration $integration): void
    {
        $credentials = $integration->getCredentialsDecrypted();
        $refreshToken = $credentials['refresh_token'] ?? null;

        if (! $refreshToken) {
            throw new RuntimeException('Cannot refresh: no refresh_token stored.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post($this->tokenEndpoint(), [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.fourthwall.client_id'),
                'client_secret' => config('services.fourthwall.client_secret'),
                'refresh_token' => $refreshToken,
            ])
            ->throw();

        $data = $response->json();

        $credentials['access_token'] = $data['access_token'];
        if (! empty($data['refresh_token'])) {
            $credentials['refresh_token'] = $data['refresh_token'];
        }
        $credentials['expires_at'] = $this->computeExpiresAt($data['expires_in'] ?? null);

        $integration->setCredentialsEncrypted($credentials);
        $integration->save();
    }

    /**
     * Return a non-expired access token, refreshing first if we're inside the safety window.
     *
     * @throws ConnectionException|RequestException
     */
    public function getFreshAccessToken(ExternalIntegration $integration): string
    {
        $credentials = $integration->getCredentialsDecrypted();
        $expiresAt = $credentials['expires_at'] ?? null;

        if ($expiresAt && CarbonImmutable::parse($expiresAt)->subSeconds(self::REFRESH_SAFETY_WINDOW_SECONDS)->isFuture()) {
            return $credentials['access_token'];
        }

        $this->refreshAccessToken($integration);

        return $integration->getCredentialsDecrypted()['access_token'];
    }

    /**
     * Register a webhook subscription on the user's Fourthwall shop.
     * Returns the full response including the per-webhook `secret` we need
     * to verify inbound payloads.
     *
     * @param  array<string>  $allowedTypes  e.g. ['DONATION']
     * @return array{id:string, url:string, allowedTypes:array<string>, secret:string, apiVersion:string}
     *
     * @throws ConnectionException|RequestException
     */
    public function registerWebhook(ExternalIntegration $integration, string $webhookUrl, array $allowedTypes): array
    {
        $token = $this->getFreshAccessToken($integration);

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->apiBase().'/open-api/v1.0/webhooks', [
                'url' => $webhookUrl,
                'allowedTypes' => $allowedTypes,
            ])
            ->throw();

        return $response->json();
    }

    /**
     * Delete a webhook subscription. Best-effort - callers should log and continue
     * on failure so a disconnect still cleans up local state.
     *
     * @throws ConnectionException|RequestException
     */
    public function deregisterWebhook(ExternalIntegration $integration, string $webhookId): void
    {
        $token = $this->getFreshAccessToken($integration);

        Http::withToken($token)
            ->acceptJson()
            ->delete($this->apiBase()."/open-api/v1.0/webhooks/{$webhookId}")
            ->throw();
    }

    private function tokenEndpoint(): string
    {
        return $this->apiBase().'/open-api/v1.0/platform/token';
    }

    private function apiBase(): string
    {
        return rtrim((string) config('services.fourthwall.api_base'), '/');
    }

    private function computeExpiresAt(?int $expiresIn): string
    {
        // Default to 5 minutes if the provider didn't tell us - Fourthwall docs say
        // "within a few minutes" and a short default keeps us honest about refreshing.
        $seconds = $expiresIn ?: 300;

        return CarbonImmutable::now()->addSeconds($seconds)->toIso8601String();
    }
}
