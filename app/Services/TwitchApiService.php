<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Get all extended data for a user
     */
    public function getExtendedUserData(string $accessToken, string $userId): array
    {
        $data = [];

        // Get channel info
        $channelInfo = $this->getChannelInfo($accessToken, $userId);
        if ($channelInfo) {
            $data['channel'] = $channelInfo;
        }

        // Get followed channels
        $followedChannels = $this->getFollowedChannels($accessToken, $userId);
        if ($followedChannels) {
            $data['followed_channels'] = $followedChannels;
        }

        // Get channel followers
        $ChannelFollowers = $this->getChannelFollowers($accessToken, $userId);
        if ($ChannelFollowers) {
            $data['channel_followers'] = $ChannelFollowers;
        }

        // Get subscribers (might fail if not Partner/Affiliate)
        $subscribers = $this->getChannelSubscribers($accessToken, $userId);
        if ($subscribers) {
            $data['subscribers'] = $subscribers;
        }

        // Get channel goals
        $goals = $this->getChannelGoals($accessToken, $userId);
        if ($goals) {
            $data['goals'] = $goals;
        }

        return $data;
    }
}