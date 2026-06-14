<?php

namespace App\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Counts outbound Reverb broadcasts per user, per calendar month.
 *
 * This is the single defacto usage limit in Overlabels. Reverb fan-out is the
 * one resource that gets expensive as the platform grows, so we meter it the
 * way shared hosting meters bandwidth: a running monthly total per user that a
 * free tier can eventually be capped against.
 *
 * Attribution is by channel name - every per-user channel embeds the owner's
 * twitch_id as its first segment (alerts.{id}, twitch-events.{id},
 * lists.{id}.{slug}), so MeteredBroadcaster can hand us the channel list and we
 * resolve the owner without touching the event payload.
 *
 * Metering must NEVER break a broadcast and must never hang a page: every Redis
 * touch goes through withRedis(), which fails fast. The first failed call in a
 * request trips $redisDown so the rest short-circuit instead of each blocking on
 * a dead/slow connection. Registered as a singleton (see AppServiceProvider) so
 * that flag is shared across the request.
 */
class BroadcastMeter
{
    /**
     * Set once a Redis op fails this request; subsequent ops skip Redis and
     * return defaults rather than each paying the full connect timeout.
     */
    private bool $redisDown = false;

    /**
     * Increment each owning user's monthly counter for one broadcast call.
     *
     * A single broadcast can hit several channels (e.g. ListUpdated fires on
     * both alerts.{id} and lists.{id}.{slug}); we count it once per distinct
     * owner so it reads as one "overlay update", not two.
     *
     * @param  array<int, string>  $channels
     */
    public function recordChannels(array $channels): void
    {
        if (! config('metering.enabled', true)) {
            return;
        }

        // In 'input' mode the broadcast counter is retired; 'both' keeps it
        // writing as an internal verification signal alongside the event meter.
        if (config('metering.meter_mode', 'both') === 'input') {
            return;
        }

        $prefixes = config('metering.channels', ['alerts', 'twitch-events', 'lists']);

        $owners = [];
        foreach ($channels as $channel) {
            $owner = self::ownerFromChannel((string) $channel, $prefixes);
            if ($owner !== null) {
                $owners[$owner] = true;
            }
        }

        foreach (array_keys($owners) as $twitchId) {
            $this->record($twitchId);
        }
    }

    /**
     * Resolve the owning twitch_id from a (possibly prefixed) channel name, or
     * null if the channel is not a metered per-user channel.
     *
     * Pure and dependency-free so it can be unit-tested without Redis. Strips
     * the Pusher visibility prefix (private-/presence-) then matches one of the
     * metered channel families followed by a numeric twitch_id.
     *
     * @param  array<int, string>  $prefixes
     */
    public static function ownerFromChannel(string $channel, array $prefixes): ?string
    {
        $name = preg_replace('/^(private-|presence-)/', '', $channel) ?? $channel;

        foreach ($prefixes as $prefix) {
            $pattern = '/^'.preg_quote($prefix, '/').'\.(\d+)(?:\.|$)/';
            if (preg_match($pattern, $name, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Increment a single user's counter for the current month.
     */
    public function record(string $twitchId, int $count = 1): void
    {
        if ($count < 1) {
            return;
        }

        $this->withRedis(function (Connection $redis) use ($twitchId, $count) {
            $key = $this->key($twitchId);
            $total = (int) $redis->incrby($key, $count);

            // First write of the month: stamp a TTL so the key self-expires and
            // we never need a scheduled reset job.
            if ($total === $count) {
                $redis->expire($key, (int) config('metering.ttl_days', 70) * 86400);
            }

            return null;
        }, null);
    }

    /**
     * Current broadcast count for a user in the given month (default: now).
     */
    public function usageFor(string $twitchId, ?string $period = null): int
    {
        $value = $this->withRedis(
            fn (Connection $redis) => $redis->get($this->key($twitchId, $period)),
            null,
        );

        return $value === null ? 0 : (int) $value;
    }

    /**
     * The most recent $months counters, newest first. Powers the Usage page's
     * history strip. One mget round-trip, not one per month.
     *
     * @return array<int, array{period: string, broadcasts: int}>
     */
    public function historyFor(string $twitchId, int $months = 6): array
    {
        $periods = [];
        $cursor = now()->startOfMonth();
        for ($i = 0; $i < $months; $i++) {
            $periods[] = $cursor->format('Y-m');
            $cursor = $cursor->subMonthNoOverflow();
        }

        $keys = array_map(fn (string $period) => $this->key($twitchId, $period), $periods);

        /** @var array<int, string|null> $values */
        $values = $this->withRedis(
            fn (Connection $redis) => $redis->mget($keys),
            array_fill(0, count($keys), null),
        );

        $history = [];
        foreach ($periods as $i => $period) {
            $history[] = [
                'period' => $period,
                'broadcasts' => (int) ($values[$i] ?? 0),
            ];
        }

        return $history;
    }

    /**
     * The free-tier monthly ceiling, or null when running observe-only.
     */
    public function freeLimit(): ?int
    {
        $limit = config('metering.free_monthly_broadcasts');

        if ($limit === null || $limit === '') {
            return null;
        }

        return (int) $limit;
    }

    /**
     * Compact usage summary for the dashboard strip and Usage page.
     *
     * @return array{broadcasts: int, limit: int|null, period: string}
     */
    public function summaryFor(string $twitchId): array
    {
        return [
            'broadcasts' => $this->usageFor($twitchId),
            'limit' => $this->freeLimit(),
            'period' => now()->format('Y-m'),
        ];
    }

    public function key(string $twitchId, ?string $period = null): string
    {
        $period ??= now()->format('Y-m');

        return "metering:bcast:{$twitchId}:{$period}";
    }

    /**
     * Run a Redis op, failing fast and non-fatally. Once anything fails this
     * request, $redisDown short-circuits every later call so a dead or slow
     * Redis costs one connect timeout, not one per read. Metering is best-effort
     * - a failure degrades to "uncounted"/zero, never a broken broadcast or a
     * hung page.
     *
     * @template T
     *
     * @param  callable(Connection): T  $op
     * @param  T  $default
     * @return T
     */
    private function withRedis(callable $op, mixed $default): mixed
    {
        if ($this->redisDown) {
            return $default;
        }

        try {
            return $op($this->redis());
        } catch (\Throwable $e) {
            // Log once per request, then stop trying.
            $this->redisDown = true;
            report($e);

            return $default;
        }
    }

    private function redis(): Connection
    {
        return Redis::connection(config('metering.redis_connection', 'default'));
    }
}
