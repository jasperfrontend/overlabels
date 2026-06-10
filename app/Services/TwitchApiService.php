<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TwitchApiService
{
    private string $clientId;

    private string $baseUrl = 'https://api.twitch.tv/helix';

    // Cache keys mapping for different data types
    private const array CACHE_KEYS = [
        'user' => 'twitch_user_info_',
        'channel' => 'twitch_channel_info_',
        'followed_channels' => 'twitch_followed_channels_',
        'channel_followers' => 'twitch_channel_followers_',
        'subscribers' => 'twitch_subscribers_',
        'goals' => 'twitch_goals_',
    ];

    /**
     * Per-type cache TTLs in seconds. Volatile reads (latest follower, subs,
     * goal progress) get short windows so cold consumers - notably Bot
     * Expressions, which read the cache without the overlay's live EventSub
     * patching - see current data. Near-static reads (profile, follow list)
     * keep longer windows. Replaces the old blanket 365-day TTL that let a
     * single failed fetch poison a key for a year.
     */
    private const array CACHE_TTL = [
        'user' => 21600,             // 6h  - profile/bio rarely changes
        'channel' => 300,            // 5m  - title/game can change mid-stream
        'followed_channels' => 3600, // 1h  - changes slowly
        'channel_followers' => 120,  // 2m  - "latest follower" is volatile
        'subscribers' => 300,        // 5m  - volatile
        'goals' => 300,              // 5m  - volatile
    ];

    /**
     * Negative-cache window: how long a null/empty fetch is held before a
     * retry. Long enough to shield Twitch from a hammer during an outage,
     * short enough that a transient failure self-heals in seconds instead of
     * persisting for the full positive TTL.
     */
    private const int EMPTY_CACHE_TTL = 30;

    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
    }

    /**
     * Generic method to make API requests to Twitch with retry logic
     *
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
                        'endpoint' => $endpoint,
                    ]);
                    usleep($retryDelay * 1000); // Convert to microseconds
                    $retryDelay *= 2; // Exponential backoff

                    continue;
                }

                Log::warning("Failed to $errorContext after $attempt attempts", [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'endpoint' => $endpoint,
                    'params' => $params,
                ]);

                return null;
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'Invalid OAuth token')) {
                    throw $e; // Re-throw auth errors
                }

                if ($attempt === $maxRetries) {
                    Log::error("Error during $errorContext after $maxRetries attempts: ".$e->getMessage());

                    return null;
                }

                Log::warning("Error on attempt $attempt for $errorContext: ".$e->getMessage());
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
        $fullCacheKey = self::CACHE_KEYS[$cacheKey].$userId;

        $cached = Cache::get($fullCacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $data = $dataCallback();

        // A null result means the fetch failed (e.g. transient 401/429/5xx, or
        // an inner channel-info lookup hiccup). Hold the empty result only
        // briefly so the next read retries, instead of caching the failure for
        // the full TTL. A genuinely empty-but-successful response (e.g. a
        // streamer with zero followers) is a non-empty array - {total:0,data:[]}
        // - so it takes the positive path and caches normally.
        if ($data === null || $data === []) {
            Cache::put($fullCacheKey, [], self::EMPTY_CACHE_TTL);

            return [];
        }

        Cache::put($fullCacheKey, $data, self::CACHE_TTL[$cacheKey]);

        return $data;
    }

    /**
     * Clear a specific cache for a user
     */
    private function clearCache(string $cacheKey, string $userId): void
    {
        $fullCacheKey = self::CACHE_KEYS[$cacheKey].$userId;
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
     * Batch-fetch user info for up to 100 IDs per Helix call. Returns a map
     * keyed by Twitch user id for O(1) lookup during enrichment.
     *
     * @param  array<int, string>  $userIds
     * @return array<string, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getUsersInfo(string $accessToken, array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $userIds), fn ($id) => $id !== '')));

        if (empty($ids)) {
            return [];
        }

        $map = [];

        foreach (array_chunk($ids, 100) as $chunk) {
            $response = $this->makeApiRequest(
                $accessToken,
                'users',
                ['id' => $chunk],
                'batch get user info'
            );

            foreach ($response['data'] ?? [] as $user) {
                if (isset($user['id'])) {
                    $map[(string) $user['id']] = $user;
                }
            }
        }

        return $map;
    }

    /**
     * @throws Exception
     */
    public function getFollowedChannels(string $accessToken, string $userId, int $first = 20): ?array
    {
        $response = $this->makeApiRequest(
            $accessToken,
            'channels/followed',
            ['user_id' => $userId, 'first' => $first],
            'get followed channels'
        );

        return $this->enrichWithProfileImages($accessToken, $response, ['broadcaster_id']);
    }

    /**
     * @throws Exception
     */
    public function getChannelFollowers(string $accessToken, string $userId, int $first = 20): ?array
    {
        // Special case: needs channel info first to get broadcaster_id
        $channelInfo = $this->getChannelInfo($accessToken, $userId);

        if (! $channelInfo) {
            Log::warning('Could not get channel info for followers request', ['user_id' => $userId]);

            return null;
        }

        $response = $this->makeApiRequest(
            $accessToken,
            'channels/followers',
            ['broadcaster_id' => $channelInfo['broadcaster_id'], 'first' => $first],
            'get channel followers'
        );

        return $this->enrichWithProfileImages($accessToken, $response, ['user_id']);
    }

    /**
     * Look up a single follower relationship: is $followerUserId following
     * $broadcasterId, and if so since when? Helix returns at most one row
     * because the user_id filter pins the relationship. Returns null on
     * "not following" so callers can branch cleanly; returns the row array
     * (with `followed_at`) when the relationship exists.
     *
     * Used by !followage. Requires the broadcaster's user token with the
     * `moderator:read:followers` scope. Skips the enrichWithProfileImages
     * pass since chat replies don't surface avatars.
     *
     * @throws Exception
     */
    public function getChannelFollower(string $accessToken, string $broadcasterId, string $followerUserId): ?array
    {
        $response = $this->makeApiRequest(
            $accessToken,
            'channels/followers',
            ['broadcaster_id' => $broadcasterId, 'user_id' => $followerUserId],
            'get single channel follower'
        );

        if (! is_array($response)) {
            return null;
        }

        return $response['data'][0] ?? null;
    }

    /**
     * Resolve a Twitch login (the URL slug, e.g. "jasperdiscovers") to the
     * full user record. Used by !followage / !accountage when chat targets
     * another user. Returns null when no user matches.
     *
     * @throws Exception
     */
    public function getUserByLogin(string $accessToken, string $login): ?array
    {
        $response = $this->makeApiRequest(
            $accessToken,
            'users',
            ['login' => strtolower($login)],
            'get user by login'
        );

        return $response ? ($response['data'][0] ?? null) : null;
    }

    /**
     * @throws Exception
     */
    public function getChannelSubscribers(string $accessToken, string $userId, int $first = 20): ?array
    {
        $response = $this->makeApiRequest(
            $accessToken,
            'subscriptions',
            ['broadcaster_id' => $userId, 'first' => $first],
            'get subscribers'
        );

        // Twitch always returns the broadcaster as a tier-3 subscriber to their
        // own channel, so it's noise in every template that iterates or
        // surfaces subscribers - and frequently top of the list as the most
        // recent "subscriber". StreamElements and StreamLabs filter this for
        // the same reason; matching that behaviour at the source so foreach,
        // `subscribers_latest_*` scalars, and the events page all stay clean.
        if (is_array($response) && ! empty($response['data']) && is_array($response['data'])) {
            $response['data'] = array_values(array_filter(
                $response['data'],
                fn ($row) => ($row['user_id'] ?? null) !== $userId,
            ));
        }

        return $this->enrichWithProfileImages($accessToken, $response, ['user_id', 'gifter_id']);
    }

    /**
     * Decorate each row in a Helix paginated response with the `profile_image_url`
     * of whichever Twitch user the row references. `$idFields` lists the row keys
     * that hold a user id (e.g. `broadcaster_id`, `user_id`, `gifter_id`). For
     * each present field an `<field_prefix>_profile_image_url` entry is added,
     * where the prefix is the part before `_id` (e.g. `broadcaster`, `user`,
     * `gifter`). Empty string ids are skipped so ungifted subs stay clean.
     *
     * @param  array<int, string>  $idFields
     *
     * @throws Exception
     */
    private function enrichWithProfileImages(string $accessToken, ?array $response, array $idFields): ?array
    {
        if (! is_array($response) || empty($response['data']) || ! is_array($response['data'])) {
            return $response;
        }

        $ids = [];
        foreach ($response['data'] as $row) {
            foreach ($idFields as $field) {
                $id = $row[$field] ?? null;
                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }
        }

        if (empty($ids)) {
            return $response;
        }

        $users = $this->getUsersInfo($accessToken, $ids);

        foreach ($response['data'] as &$row) {
            foreach ($idFields as $field) {
                $id = $row[$field] ?? null;
                $prefix = str_ends_with($field, '_id') ? substr($field, 0, -3) : $field;
                $targetKey = $prefix.'_profile_image_url';

                if (is_string($id) && $id !== '' && isset($users[$id]['profile_image_url'])) {
                    $row[$targetKey] = $users[$id]['profile_image_url'];
                } else {
                    $row[$targetKey] = '';
                }
            }
        }
        unset($row);

        return $response;
    }

    /**
     * Walk an EventSub `event` payload and inject `<prefix>_avatar` next to every
     * sibling-key trigram (`<prefix>_id`, `<prefix>_login`, `<prefix>_name`) where
     * all three are present and the `_id` is a non-empty string. Bare `user_id`
     * / `user_login` / `user_name` (no prefix) is treated as the `user_` family
     * with output key `user_avatar`.
     *
     * Covers top-level (`user_*`, `broadcaster_user_*`, `from_broadcaster_user_*`,
     * `moderator_user_*`), nested arrays (`top_contributions[]`), and nested
     * objects (`last_contribution`). Profile lookups are batched into a single
     * `getUsersInfo` call across the whole tree, then memoised per user id for
     * 60s so gift bombs and hype trains don't hammer Helix /users.
     *
     * Defensive by design: any failure (token expired, Helix down, malformed
     * payload) is logged and the original `$event` is returned unchanged. Event
     * processing must never break because enrichment failed.
     */
    public function enrichEventWithUserAvatars(string $accessToken, array $event): array
    {
        try {
            $detected = [];
            $this->collectUserTrigrams($event, $detected);

            if (empty($detected)) {
                return $event;
            }

            $idToAvatar = [];
            $idsToFetch = [];
            foreach ($detected as $id) {
                $cached = Cache::get("twitch_event_avatar_$id");
                if ($cached !== null) {
                    $idToAvatar[$id] = $cached;
                } else {
                    $idsToFetch[] = $id;
                }
            }

            if (! empty($idsToFetch)) {
                $users = $this->getUsersInfo($accessToken, $idsToFetch);
                foreach ($idsToFetch as $id) {
                    $avatar = (string) ($users[$id]['profile_image_url'] ?? '');
                    $idToAvatar[$id] = $avatar;
                    Cache::put("twitch_event_avatar_$id", $avatar, now()->addSeconds(60));
                }
            }

            $this->injectAvatars($event, $idToAvatar);

            return $event;
        } catch (Throwable $e) {
            Log::warning('EventSub avatar enrichment failed; returning event unchanged', [
                'error' => $e->getMessage(),
            ]);

            return $event;
        }
    }

    /**
     * Pass 1 of avatar enrichment: walk the payload and collect every user id
     * referenced by a complete `<prefix>_id` + `<prefix>_login` + `<prefix>_name`
     * trigram. Bare `user_id`/`user_login`/`user_name` (no prefix) is treated as
     * the `user_` family. Output is a deduped list of string ids.
     *
     * @param  array<int, string>  $out  Accumulator, mutated by reference.
     */
    private function collectUserTrigrams(mixed $node, array &$out): void
    {
        if (! is_array($node)) {
            return;
        }

        if (! array_is_list($node)) {
            foreach ($this->trigramPrefixes($node) as $prefix) {
                $id = $node[$prefix.'id'] ?? null;
                if (is_string($id) && $id !== '' && ! in_array($id, $out, true)) {
                    $out[] = $id;
                }
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collectUserTrigrams($value, $out);
            }
        }
    }

    /**
     * Pass 2 of avatar enrichment: walk the payload and inject `<prefix>_avatar`
     * (or `user_avatar` for the bare trigram) into every object that owns a
     * complete trigram. Missing avatars resolve to an empty string so templates
     * see a stable key shape (matches the Helix enrichment behaviour).
     *
     * @param  array<string, string>  $idToAvatar
     */
    private function injectAvatars(array &$node, array $idToAvatar): void
    {
        if (! array_is_list($node)) {
            foreach ($this->trigramPrefixes($node) as $prefix) {
                $id = $node[$prefix.'id'] ?? null;
                if (! is_string($id) || $id === '') {
                    continue;
                }
                $node[$prefix.'avatar'] = $idToAvatar[$id] ?? '';
            }
        }

        foreach ($node as &$value) {
            if (is_array($value)) {
                $this->injectAvatars($value, $idToAvatar);
            }
        }
        unset($value);
    }

    /**
     * Return every prefix in `$node` that has a complete trigram. A prefix is
     * the part of the key before the `_id`/`_login`/`_name` suffix - the bare
     * `user_id` trigram returns prefix `user_` (i.e. output key `user_avatar`).
     *
     * @param  array<string, mixed>  $node
     * @return array<int, string>
     */
    private function trigramPrefixes(array $node): array
    {
        $prefixes = [];

        foreach ($node as $key => $_) {
            if (! is_string($key)) {
                continue;
            }
            if (! str_ends_with($key, '_id')) {
                continue;
            }

            $prefix = substr($key, 0, -2); // keeps trailing `_`; e.g. `broadcaster_user_id` -> `broadcaster_user_`
            if ($prefix === '') {
                continue;
            }

            // Bare `user_id` lives alongside `user_login`/`user_name` - prefix
            // would be `user_` which already matches that pattern. So a single
            // rule works for both cases.
            if (! array_key_exists($prefix.'login', $node)) {
                continue;
            }
            if (! array_key_exists($prefix.'name', $node)) {
                continue;
            }

            $prefixes[] = $prefix;
        }

        return $prefixes;
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
        return $this->getCachedData('channel', $userId, fn () => $this->getChannelInfo($accessToken, $userId));
    }

    protected function getCachedUserInfo(string $accessToken, string $userId): array
    {
        return $this->getCachedData('user', $userId, fn () => $this->getUserInfo($accessToken, $userId));
    }

    protected function getCachedFollowedChannels(string $accessToken, string $userId): array
    {
        return $this->getCachedData('followed_channels', $userId, fn () => $this->getFollowedChannels($accessToken, $userId));
    }

    protected function getCachedChannelFollowers(string $accessToken, string $userId): array
    {
        return $this->getCachedData('channel_followers', $userId, fn () => $this->getChannelFollowers($accessToken, $userId));
    }

    protected function getCachedSubscribers(string $accessToken, string $userId): array
    {
        return $this->getCachedData('subscribers', $userId, fn () => $this->getChannelSubscribers($accessToken, $userId));
    }

    protected function getCachedGoals(string $accessToken, string $userId): array
    {
        return $this->getCachedData('goals', $userId, fn () => $this->getChannelGoals($accessToken, $userId));
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

    /**
     * Check if a user is currently streaming via Helix GET /streams.
     * Returns null on API failure (indeterminate), or an array with is_live and stream data.
     *
     * @throws ConnectionException
     */
    public function getStreamStatus(string $accessToken, string $userId): ?array
    {
        $response = $this->makeApiRequest(
            $accessToken,
            'streams',
            ['user_id' => $userId],
            'get stream status'
        );

        if ($response === null) {
            return null;
        }

        $data = $response['data'] ?? [];

        if (empty($data)) {
            return ['is_live' => false, 'stream' => null];
        }

        $stream = $data[0];

        return [
            'is_live' => true,
            'stream' => [
                'id' => $stream['id'],
                'started_at' => $stream['started_at'],
                'game_name' => $stream['game_name'] ?? null,
                'title' => $stream['title'] ?? null,
                'viewer_count' => $stream['viewer_count'] ?? 0,
            ],
        ];
    }

    /**
     * Fetch Twitch global + channel emotes using an app access token.
     * Returns an array of ['code' => string, 'url' => string] entries.
     * No user credentials required — app token (client credentials) is sufficient.
     *
     * @throws ConnectionException
     */
    public function getChannelEmotes(string $appToken, string $broadcasterId): array
    {
        $emotes = [];

        $global = $this->makeApiRequest($appToken, 'chat/emotes/global', [], 'get global Twitch emotes');
        if ($global && isset($global['data'])) {
            foreach ($global['data'] as $emote) {
                $emotes[] = [
                    'code' => $emote['name'],
                    'url' => "https://static-cdn.jtvnw.net/emoticons/v2/{$emote['id']}/default/dark/1.0",
                ];
            }
        }

        $channel = $this->makeApiRequest($appToken, 'chat/emotes', ['broadcaster_id' => $broadcasterId], 'get channel Twitch emotes');
        if ($channel && isset($channel['data'])) {
            foreach ($channel['data'] as $emote) {
                $emotes[] = [
                    'code' => $emote['name'],
                    'url' => "https://static-cdn.jtvnw.net/emoticons/v2/{$emote['id']}/default/dark/1.0",
                ];
            }
        }

        return $emotes;
    }
}
