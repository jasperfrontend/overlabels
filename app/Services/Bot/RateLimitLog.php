<?php

namespace App\Services\Bot;

use Illuminate\Support\Facades\Cache;

class RateLimitLog
{
    private const KEY = 'bot:rate-limit-events';

    private const MAX_EVENTS = 50;

    private const TTL_SECONDS = 86400;

    public static function record(string $scope, ?string $login, string $ip): void
    {
        $events = Cache::get(self::KEY, []);
        array_unshift($events, [
            'scope' => $scope,
            'login' => $login,
            'ip' => $ip,
            'at' => now()->toIso8601String(),
        ]);
        Cache::put(self::KEY, array_slice($events, 0, self::MAX_EVENTS), self::TTL_SECONDS);
    }

    public static function recent(int $limit = 20): array
    {
        return array_slice(Cache::get(self::KEY, []), 0, $limit);
    }

    public static function clear(): void
    {
        Cache::forget(self::KEY);
    }
}
