<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TwitchApiService
{
    private string $clientId;
    private string $baseUrl = 'https://api.twitch.tv/helix';

    // Cache keys mapping for different data types
    private const CACHE_KEYS = [
        'user' => 'twitch_user_info_',
        'channel' => 'twitch_channel_info_',
        'followed_channels' => 'twitch_followed_channels_',
        'channel_followers' => 'twitch_channel_followers_',
        'subscribers' => 'twitch_subscribers_',
        'goals' => 'twitch_goals_',
    ];

    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
    }

    /**
     * Generic method to make API requests to Twitch with retry logic
     * @throws ConnectionException
     */
    private function makeApiRequest(string $accessToken, string $endpoint, array $params = [], string $errorContext = 'API request'): ?array
    {
        $maxRetries = 3;
        $retryDelay = 1000; // Start with 1 second

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer $accessToken",
                    'Client-Id' => $this->clientId,
                ])->timeout(10)->get("$this->baseUrl/$endpoint", $params);

                if ($response->successful()) {
                    return $response->json();
                }

                // Handle specific error codes
                if ($response->status() === 401) {
                    // Token is invalid, throw exception to trigger re-auth
                    throw new Exception('Invalid OAuth token - requires re-authentication');
                }

                if ($response->status() === 429) {
                    // Rate limited, wait longer before retry
                    $retryAfter = $response->header('Retry-After') ?? 60;
                    Log::warning("Rate limited on $errorContext, waiting $retryAfter seconds");
                    sleep(min($retryAfter, 60)); // Cap at 60 seconds
                    continue;
                }

                // For other 5xx errors, retry with exponential backoff
                if ($response->status() >= 500 && $attempt < $maxRetries) {
                    Log::warning("Server error on attempt $attempt for $errorContext, retrying...", [
                        'status' => $response->status(),
                        'endpoint' => $endpoint
                    ]);
                    usleep($retryDelay * 1000); // Convert to microseconds
                    $retryDelay *= 2; // Exponential backoff
                    continue;
                }

                Log::warning("Failed to $errorContext after $attempt attempts", [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'endpoint' => $endpoint,
                    'params' => $params
                ]);

                return null;
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'Invalid OAuth token')) {
                    throw $e; // Re-throw auth errors
                }

                if ($attempt === $maxRetries) {
                    Log::error("Error during $errorContext after $maxRetries attempts: " . $e->getMessage());
                    return null;
                }

                Log::warning("Error on attempt $attempt for $errorContext: " . $e->getMessage());
                usleep($retryDelay * 1000);
                $retryDelay *= 2;
            }
        }

        return null;
    }

    /**
     * Generic method to handle cached data retrieval
     */
    private function getCachedData(string $cacheKey, string $userId, callable $dataCallback): array
    {
        $fullCacheKey = self::CACHE_KEYS[$cacheKey] . $userId;
        return Cache::remember($fullCacheKey, now()->addDays(365), function () use ($dataCallback) {
            return $dataCallback() ?? [];
        });
    }

    /**
     * Clear a specific cache for a user
     */
    private function clearCache(string $cacheKey, string $userId): void
    {
        $fullCacheKey = self::CACHE_KEYS[$cacheKey] . $userId;
        Cache::forget($fullCacheKey);
    }

    /**
     * @throws Exception
     */
    public function getChannelInfo(string $accessToken, string $userId): ?array
    {
        $response = $this->makeApiRequest(
            $accessToken,
            'channels',
            ['broadcaster_id' => $userId],
            'get channel info'
        );

        return $response ? ($response['data'][0] ?? null) : null;
    }

    /**
     * @throws Exception
     */
    public function getUserInfo(string $accessToken, string $userId): ?array
    {
        $response = $this->makeApiRequest(
            $accessToken,
            'users',
            ['id' => $userId],
            'get user info'
        );

        return $response ? ($response['data'][0] ?? null) : null;
    }

    /**
     * @throws Exception
     */
    public function getFollowedChannels(string $accessToken, string $userId, int $first = 20): ?array
    {
        return $this->makeApiRequest(
            $accessToken,
            'channels/followed',
            ['user_id' => $userId, 'first' => $first],
            'get followed channels'
        );
    }

    /**
     * @throws Exception
     */
    public function getChannelFollowers(string $accessToken, string $userId, int $first = 20): ?array
    {
        // Special case: needs channel info first to get broadcaster_id
        $channelInfo = $this->getChannelInfo($accessToken, $userId);

        if (!$channelInfo) {
            Log::warning('Could not get channel info for followers request', ['user_id' => $userId]);
            return null;
        }

        return $this->makeApiRequest(
            $accessToken,
            'channels/followers',
            ['broadcaster_id' => $channelInfo['broadcaster_id'], 'first' => $first],
            'get channel followers'
        );
    }

    /**
     * @throws Exception
     */
    public function getChannelSubscribers(string $accessToken, string $userId, int $first = 20): ?array
    {
        return $this->makeApiRequest(
            $accessToken,
            'subscriptions',
            ['broadcaster_id' => $userId, 'first' => $first],
            'get subscribers'
        );
    }

    /**
     * @throws Exception
     */
    public function getChannelGoals(string $accessToken, string $userId): ?array
    {
        return $this->makeApiRequest(
            $accessToken,
            'goals',
            ['broadcaster_id' => $userId],
            'get channel goals'
        );
    }

    // Cache the everliving heck out of the data because any EventSub event will kick off a cache refresh for that user anyway.
    protected function getCachedChannelInfo(string $accessToken, string $userId): array
    {
        return $this->getCachedData('channel', $userId, fn() => $this->getChannelInfo($accessToken, $userId));
    }

    protected function getCachedUserInfo(string $accessToken, string $userId): array
    {
        return $this->getCachedData('user', $userId, fn() => $this->getUserInfo($accessToken, $userId));
    }

    protected function getCachedFollowedChannels(string $accessToken, string $userId): array
    {
        return $this->getCachedData('followed_channels', $userId, fn() => $this->getFollowedChannels($accessToken, $userId));
    }

    protected function getCachedChannelFollowers(string $accessToken, string $userId): array
    {
        return $this->getCachedData('channel_followers', $userId, fn() => $this->getChannelFollowers($accessToken, $userId));
    }

    protected function getCachedSubscribers(string $accessToken, string $userId): array
    {
        return $this->getCachedData('subscribers', $userId, fn() => $this->getChannelSubscribers($accessToken, $userId));
    }

    protected function getCachedGoals(string $accessToken, string $userId): array
    {
        return $this->getCachedData('goals', $userId, fn() => $this->getChannelGoals($accessToken, $userId));
    }

    /*
     * getExtendedUserData gets the user data from the Laravel Cache.
     */
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

    /*
     * getFreshTwitchData gets data directly from the Twitch API. This is an expensive call
     * because it refreshes ALL your data. The user is advised to use this API request with
     * caution and to not overuse it.
     */
    /**
     * @throws Exception
     */
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
        foreach (self::CACHE_KEYS as $key => $prefix) {
            $this->clearCache($key, $userId);
        }
    }

    public function clearUserInfoCache(string $userId): void
    {
        $this->clearCache('user', $userId);
    }

    public function clearChannelInfoCaches(string $userId): void
    {
        $this->clearCache('channel', $userId);
    }

    public function clearFollowedChannelsCaches(string $userId): void
    {
        $this->clearCache('followed_channels', $userId);
    }

    public function clearChannelFollowersCaches(string $userId): void
    {
        $this->clearCache('channel_followers', $userId);
    }

    public function clearSubscribersCaches(string $userId): void
    {
        $this->clearCache('subscribers', $userId);
    }

    public function clearGoalsCaches(string $userId): void
    {
        $this->clearCache('goals', $userId);
    }
}
