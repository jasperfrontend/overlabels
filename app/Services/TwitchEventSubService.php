<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwitchEventSubService
{
    private string $clientId;
    private string $baseUrl = 'https://api.twitch.tv/helix/eventsub/subscriptions';
    
    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
    }

    /**
     * Subscribe to a Twitch EventSub event
     */
    public function createSubscription(string $accessToken, string $eventType, string $userId, string $callbackUrl): ?array
    {
        try {
            $payload = [
                'type' => $eventType,
                'version' => '1',
                'condition' => [
                    'broadcaster_user_id' => $userId
                ],
                'transport' => [
                    'method' => 'webhook',
                    'callback' => $callbackUrl,
                    'secret' => config('app.twitch_webhook_secret', 'fallback-secret')
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $this->clientId,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl, $payload);

            if ($response->successful()) {
                Log::info('EventSub subscription created', [
                    'type' => $eventType,
                    'response' => $response->json()
                ]);
                return $response->json();
            }

            Log::error('Failed to create EventSub subscription', [
                'status' => $response->status(),
                'response' => $response->body(),
                'payload' => $payload,
                'headers' => $response->headers()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error creating EventSub subscription: ' . $e->getMessage());
            return null;
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            Log::error('Error deleting EventSub subscription: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Subscribe to channel follow events
     */
    public function subscribeToFollows(string $accessToken, string $userId, string $callbackUrl): ?array
    {
        return $this->createSubscription($accessToken, 'channel.follow', $userId, $callbackUrl);
    }

    /**
     * Subscribe to channel subscription events
     */
    public function subscribeToSubscriptions(string $accessToken, string $userId, string $callbackUrl): ?array
    {
        return $this->createSubscription($accessToken, 'channel.subscribe', $userId, $callbackUrl);
    }
}