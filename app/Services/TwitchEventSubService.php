<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwitchEventSubService
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl = 'https://api.twitch.tv/helix/eventsub/subscriptions';

    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
        $this->clientSecret = config('services.twitch.client_secret');
    }

    /**
     * Get an app access token for EventSub subscriptions that require it
     * Made public so the controller can use it for status checks
     */
    public function getAppAccessToken(): ?string
    {
        try {
            $response = Http::post('https://id.twitch.tv/oauth2/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'];
            }

            Log::error('Failed to get app access token', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return null;
        } catch (Exception $e) {
            Log::error('Exception getting app access token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Subscribe to a Twitch EventSub event
     */
    public function createSubscription(string $accessToken, array $payload): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $this->clientId,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            // Return error info instead of null so we can see what went wrong
            return [
                'error' => true,
                'status' => $response->status(),
                'message' => $response->body(),
                'payload' => $payload
            ];

        } catch (Exception $e) {
            Log::error('EventSub subscription exception', [
                'event_type' => $payload['type'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'payload' => $payload
            ];
        }
    }

    /**
     * Get all active subscriptions
     */
    public function getSubscriptions(string $accessToken): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $this->clientId,
            ])->get($this->baseUrl);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error('Error getting EventSub subscriptions: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a subscription
     */
    public function deleteSubscription(string $accessToken, string $subscriptionId): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $this->clientId,
            ])->delete($this->baseUrl, [
                'id' => $subscriptionId
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Error deleting EventSub subscription: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Subscribe to the channel follow events (Version 2 - REQUIRES APP TOKEN)
     */
    public function subscribeToFollows(string $userAccessToken, string $userId, string $callbackUrl): ?array
    {
        // Get app access token for follow events (REQUIRED by Twitch)
        $appToken = $this->getAppAccessToken();
        if (!$appToken) {
            return [
                'error' => true,
                'message' => 'Could not get app access token for follow events'
            ];
        }

        $payload = [
            'type' => 'channel.follow',
            'version' => '2', // Use version 2
            'condition' => [
                'broadcaster_user_id' => $userId,
                'moderator_user_id' => $userId // Required for follows
            ],
            'transport' => [
                'method' => 'webhook',
                'callback' => $callbackUrl,
                'secret' => config('app.twitch_webhook_secret', 'fallback-secret')
            ]
        ];

        // Use APP access token for follow events (not user token)
        return $this->createSubscription($appToken, $payload);
    }

    /**
     * Subscribe to channel subscription events (Requires app access token)
     */
    public function subscribeToSubscriptions(string $userAccessToken, string $userId, string $callbackUrl): ?array
    {
        // Get app access token for subscription events
        if (!$userAccessToken) {
            return [
                'error' => true,
                'message' => 'Could not get app access token for subscription events'
            ];
        }

        $payload = [
            'type' => 'channel.subscribe',
            'version' => '1',
            'condition' => [
                'broadcaster_user_id' => $userId
            ],
            'transport' => [
                'method' => 'webhook',
                'callback' => $callbackUrl,
                'secret' => config('app.twitch_webhook_secret')
            ]
        ];

        // Use app access token for subscription events
        return $this->createSubscription($userAccessToken, $payload);
    }

    /**
     * Subscribe to channel raids
     */
    public function subscribeToRaids(string $userAccessToken, string $userId, string $callbackUrl): ?array
    {
        $payload = [
            'type' => 'channel.raid',
            'version' => '1',
            'condition' => [
                'to_broadcaster_user_id' => $userId // When someone raids YOU
            ],
            'transport' => [
                'method' => 'webhook',
                'callback' => $callbackUrl,
                'secret' => config('app.twitch_webhook_secret')
            ]
        ];

        return $this->createSubscription($userAccessToken, $payload);
    }

    /**
     * Subscribe to stream online events
     */
    public function subscribeToStreamOnline(string $userAccessToken, string $userId, string $callbackUrl): ?array
    {
        $payload = [
            'type' => 'stream.online',
            'version' => '1',
            'condition' => [
                'broadcaster_user_id' => $userId
            ],
            'transport' => [
                'method' => 'webhook',
                'callback' => $callbackUrl,
                'secret' => config('app.twitch_webhook_secret')
            ]
        ];

        return $this->createSubscription($userAccessToken, $payload);
    }
}
