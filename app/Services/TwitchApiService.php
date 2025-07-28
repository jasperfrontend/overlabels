<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TwitchApiService
{
    private string $clientId;
    private string $baseUrl = 'https://api.twitch.tv/helix';

    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
    }

    /**
     * Get channel information for a user
     */
    public function getChannelInfo(string $accessToken, string $userId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $this->clientId,
            ])->get("{$this->baseUrl}/channels", [
                'broadcaster_id' => $userId
            ]);

            if ($response->successful()) {
                return $response->json()['data'][0] ?? null;
            }

            Log::warning('Failed to get channel info', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting channel info: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get who the user follows
     */
    public function getFollowedChannels(string $accessToken, string $userId, int $first = 20): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $this->clientId,
            ])->get("{$this->baseUrl}/channels/followed", [
                'user_id' => $userId,
                'first' => $first
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting followed channels: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get who the user is followed by (channel followers)
     */
    public function getChannelFollowers(string $accessToken, string $userId, int $first = 20): ?array
    {
        try {
            // Get channel info to extract broadcaster_id
            $channelInfo = $this->getChannelInfo($accessToken, $userId);
            
            if (!$channelInfo) {
                Log::warning('Could not get channel info for followers request', ['user_id' => $userId]);
                return null;
            }
            
            $broadcasterId = $channelInfo['broadcaster_id'];
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $this->clientId,
            ])->get("{$this->baseUrl}/channels/followers", [
                'broadcaster_id' => $broadcasterId,
                'first' => $first
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Failed to get channel followers', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting channel followers: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get channel subscribers (requires Partner/Affiliate status)
     */
    public function getChannelSubscribers(string $accessToken, string $userId, int $first = 20): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $this->clientId,
            ])->get("{$this->baseUrl}/subscriptions", [
                'broadcaster_id' => $userId,
                'first' => $first
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting subscribers: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get channel goals
     */
    public function getChannelGoals(string $accessToken, string $userId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Client-Id' => $this->clientId,
            ])->get("{$this->baseUrl}/goals", [
                'broadcaster_id' => $userId
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting channel goals: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get Twitch channel info from the authenticated account (bio, profile pic, tags)
     */
    protected function getCachedChannelInfo(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_channel_info_{$userId}", now()->addHours(12), function () use ($accessToken, $userId) {
            return $this->getChannelInfo($accessToken, $userId) ?? [];
        });
    }

    /**
     * Get Channels that user is following from the authenticated account
     */
    protected function getCachedFollowedChannels(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_followed_channels_{$userId}", now()->addMinutes(10), function () use ($accessToken, $userId) {
            return $this->getFollowedChannels($accessToken, $userId) ?? [];
        });
    }

    /**
     * Get Followers from the authenticated account
     */
    protected function getCachedChannelFollowers(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_channel_followers_{$userId}", now()->addMinutes(10), function () use ($accessToken, $userId) {
            return $this->getChannelFollowers($accessToken, $userId) ?? [];
        });
    }

    /**
     * Get Subscribers from the authenticated account
     */
    protected function getCachedSubscribers(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_subscribers_{$userId}", now()->addMinutes(5), function () use ($accessToken, $userId) {
            return $this->getChannelSubscribers($accessToken, $userId) ?? [];
        });
    }

    /**
     * Get Goals from the authenticated account
     */
    protected function getCachedGoals(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_goals_{$userId}", now()->addMinutes(5), function () use ($accessToken, $userId) {
            return $this->getChannelGoals($accessToken, $userId) ?? [];
        });
    }

    /**
     * Get all extended data for a user. Each item has its own cache and caching duration
     */
    public function getExtendedUserData(string $accessToken, string $userId): array
    {
        return [
            'channel' => $this->getCachedChannelInfo($accessToken, $userId),
            'followed_channels' => $this->getCachedFollowedChannels($accessToken, $userId),
            'channel_followers' => $this->getCachedChannelFollowers($accessToken, $userId),
            'subscribers' => $this->getCachedSubscribers($accessToken, $userId),
            'goals' => $this->getCachedGoals($accessToken, $userId),
        ];
    }

    /**
     * Clear all Twitch API User Data Caches
     */
    public function clearAllUserCaches(string $userId): void
    {
        Cache::forget("twitch_channel_info_{$userId}");
        Cache::forget("twitch_followed_channels_{$userId}");
        Cache::forget("twitch_channel_followers_{$userId}");
        Cache::forget("twitch_subscribers_{$userId}");
        Cache::forget("twitch_goals_{$userId}");
    }

    /**
     * Clear Twitch API Channel Info Caches
     */
    public function clearChannelInfoCaches(string $userId): void
    {
        Cache::forget("twitch_channel_info_{$userId}");
    }

    /**
     * Clear Twitch API Followed Channels Caches
     */
    public function clearFollowedChannelsCaches(string $userId): void
    {
        Cache::forget("twitch_followed_channels_{$userId}");
    }

    /**
     * Clear Twitch API Channel Followers Caches
     */
    public function clearChannelFollowersCaches(string $userId): void
    {
        Cache::forget("twitch_channel_followers_{$userId}");
    }

    /**
     * Clear Twitch API Subscribers Caches
     */
    public function clearSubscribersCaches(string $userId): void
    {
        Cache::forget("twitch_subscribers_{$userId}");
    }

    /**
     * Clear Twitch API Goals Caches
     */
    public function clearGoalsCaches(string $userId): void
    {
        Cache::forget("twitch_goals_{$userId}");
    }

}