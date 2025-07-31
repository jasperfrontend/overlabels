<?php

namespace App\Services;

use Exception;
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

    public function getChannelInfo(string $accessToken, string $userId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Client-Id' => $this->clientId,
            ])->get("$this->baseUrl/channels", [
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
        } catch (Exception $e) {
            Log::error('Error getting channel info: ' . $e->getMessage());
            return null;
        }
    }

    public function getUserInfo(string $accessToken, string $userId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Client-Id' => $this->clientId,
            ])->get("$this->baseUrl/users", [
                'id' => $userId
            ]);

            if ($response->successful()) {
                return $response->json()['data'][0] ?? null;
            }

            Log::warning('Failed to get user info', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Error getting user info: ' . $e->getMessage());
            return null;
        }
    }

    public function getFollowedChannels(string $accessToken, string $userId, int $first = 20): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Client-Id' => $this->clientId,
            ])->get("$this->baseUrl/channels/followed", [
                'user_id' => $userId,
                'first' => $first
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error('Error getting followed channels: ' . $e->getMessage());
            return null;
        }
    }

    public function getChannelFollowers(string $accessToken, string $userId, int $first = 20): ?array
    {
        try {
            $channelInfo = $this->getChannelInfo($accessToken, $userId);

            if (!$channelInfo) {
                Log::warning('Could not get channel info for followers request', ['user_id' => $userId]);
                return null;
            }

            $broadcasterId = $channelInfo['broadcaster_id'];

            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Client-Id' => $this->clientId,
            ])->get("$this->baseUrl/channels/followers", [
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
        } catch (Exception $e) {
            Log::error('Error getting channel followers: ' . $e->getMessage());
            return null;
        }
    }

    public function getChannelSubscribers(string $accessToken, string $userId, int $first = 20): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Client-Id' => $this->clientId,
            ])->get("$this->baseUrl/subscriptions", [
                'broadcaster_id' => $userId,
                'first' => $first
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error('Error getting subscribers: ' . $e->getMessage());
            return null;
        }
    }

    public function getChannelGoals(string $accessToken, string $userId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $accessToken",
                'Client-Id' => $this->clientId,
            ])->get("$this->baseUrl/goals", [
                'broadcaster_id' => $userId
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error('Error getting channel goals: ' . $e->getMessage());
            return null;
        }
    }

    protected function getCachedChannelInfo(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_channel_info_$userId", now()->addHours(12), function () use ($accessToken, $userId) {
            return $this->getChannelInfo($accessToken, $userId) ?? [];
        });
    }

    protected function getCachedUserInfo(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_user_info_$userId", now()->addHours(12), function () use ($accessToken, $userId) {
            return $this->getUserInfo($accessToken, $userId) ?? [];
        });
    }

    protected function getCachedFollowedChannels(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_followed_channels_$userId", now()->addMinutes(10), function () use ($accessToken, $userId) {
            return $this->getFollowedChannels($accessToken, $userId) ?? [];
        });
    }

    protected function getCachedChannelFollowers(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_channel_followers_$userId", now()->addMinutes(10), function () use ($accessToken, $userId) {
            return $this->getChannelFollowers($accessToken, $userId) ?? [];
        });
    }

    protected function getCachedSubscribers(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_subscribers_$userId", now()->addMinutes(5), function () use ($accessToken, $userId) {
            return $this->getChannelSubscribers($accessToken, $userId) ?? [];
        });
    }

    protected function getCachedGoals(string $accessToken, string $userId): array
    {
        return Cache::remember("twitch_goals_$userId", now()->addMinutes(5), function () use ($accessToken, $userId) {
            return $this->getChannelGoals($accessToken, $userId) ?? [];
        });
    }

    public function getExtendedUserData(string $accessToken, string $userId): array
    {
        return [
            'user' => $this->getCachedUserInfo($accessToken, $userId),
            'channel' => $this->getCachedChannelInfo($accessToken, $userId),
            'followed_channels' => $this->getCachedFollowedChannels($accessToken, $userId),
            'channel_followers' => $this->getCachedChannelFollowers($accessToken, $userId),
            'subscribers' => $this->getCachedSubscribers($accessToken, $userId),
            'goals' => $this->getCachedGoals($accessToken, $userId),
        ];
    }

    public function getFreshTwitchData(string $accessToken, string $userId): array
    {
        return [
            'user' => $this->getUserInfo($accessToken, $userId),
            'channel' => $this->getChannelInfo($accessToken, $userId),
            'followed_channels' => $this->getFollowedChannels($accessToken, $userId),
            'channel_followers' => $this->getChannelFollowers($accessToken, $userId),
            'subscribers' => $this->getChannelSubscribers($accessToken, $userId),
            'goals' => $this->getChannelGoals($accessToken, $userId),
        ];
    }

    public function clearAllUserCaches(string $userId): void
    {
        Cache::forget("twitch_user_info_$userId");
        Cache::forget("twitch_channel_info_$userId");
        Cache::forget("twitch_followed_channels_$userId");
        Cache::forget("twitch_channel_followers_$userId");
        Cache::forget("twitch_subscribers_$userId");
        Cache::forget("twitch_goals_$userId");
    }

    public function clearUserInfoCache(string $userId): void
    {
        Cache::forget("twitch_user_info_$userId");
    }

    public function clearChannelInfoCaches(string $userId): void
    {
        Cache::forget("twitch_channel_info_$userId");
    }

    public function clearFollowedChannelsCaches(string $userId): void
    {
        Cache::forget("twitch_followed_channels_$userId");
    }

    public function clearChannelFollowersCaches(string $userId): void
    {
        Cache::forget("twitch_channel_followers_$userId");
    }

    public function clearSubscribersCaches(string $userId): void
    {
        Cache::forget("twitch_subscribers_$userId");
    }

    public function clearGoalsCaches(string $userId): void
    {
        Cache::forget("twitch_goals_$userId");
    }
}
