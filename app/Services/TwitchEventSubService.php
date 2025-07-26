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

            // Always log the response for debugging
            Log::info('Twitch EventSub API Response', [
                'event_type' => $eventType,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'payload_sent' => $payload
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            // Return error info instead of null
            return [
                'error' => true,
                'status' => $response->status(),
                'message' => $response->body(),
                'payload' => $payload
            ];

            // This is the important part - log the actual error
            Log::error('EventSub subscription FAILED', [
                'event_type' => $eventType,
                'status' => $response->status(),
                'response_body' => $response->body(),
                'response_headers' => $response->headers(),
                'payload_sent' => $payload
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('EventSub subscription exception', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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