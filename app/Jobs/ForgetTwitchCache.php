<?php

namespace App\Jobs;

use App\Services\TwitchApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The trailing half of the volatile-cache throttle in
 * TwitchEventSubController::refreshCachesForEvent().
 *
 * The first event of a window clears the cache at once, so its own alert reads
 * fresh data; the rest of the window leaves it alone. This runs when the window
 * ends and clears it once more, so a change made by the LAST event of a burst is
 * picked up by the next reader instead of waiting out the full TTL (five minutes
 * for goals and subscribers). It only forgets: it fetches nothing and broadcasts
 * nothing - the overlay keeps its own count from the events themselves.
 *
 * Default queue, never twitch-events: it must not take a turn on the worker that
 * delivers alerts.
 */
class ForgetTwitchCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** The volatile caches the throttle covers, mapped to their clear method. */
    public const array CACHES = [
        'channel_followers' => 'clearChannelFollowersCaches',
        'subscribers' => 'clearSubscribersCaches',
        'goals' => 'clearGoalsCaches',
    ];

    public function __construct(
        public readonly string $cache,
        public readonly string $broadcasterId,
    ) {}

    public function handle(TwitchApiService $twitch): void
    {
        self::forget($twitch, $this->cache, $this->broadcasterId);
    }

    public static function forget(TwitchApiService $twitch, string $cache, string $broadcasterId): void
    {
        $method = self::CACHES[$cache] ?? null;

        if ($method !== null) {
            $twitch->{$method}($broadcasterId);
        }
    }
}
