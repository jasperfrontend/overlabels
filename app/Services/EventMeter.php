<?php

namespace App\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Counts INBOUND events per user, per calendar month: GPS pings, donations,
 * Twitch EventSub notifications - the actual activity a user generates.
 *
 * This is the meter pricing should be set against. Unlike outbound broadcasts
 * (see {@see BroadcastMeter}), an inbound event is one event regardless of how
 * many overlays a user has or how badly a control fans out - a user with 1
 * overlay and a user with 200 produce the same ping count for the same ride.
 * That decouples the bill from the broadcast implementation, so the fan-out can
 * be fixed on its own schedule without ever touching anyone's usage number.
 *
 * Keyed by internal user_id (not twitch_id): the input boundaries always have
 * the owning user in hand, and GPS-only users need not be Twitch-attributed.
 *
 * Mirrors BroadcastMeter's fail-fast Redis discipline: every touch goes through
 * withRedis(), the first failure trips $redisDown so the rest short-circuit, and
 * metering never breaks a request. Registered as a singleton so that flag is
 * shared across a request (see AppServiceProvider).
 */
class EventMeter
{
    /**
     * Set once a Redis op fails this request; subsequent ops skip Redis and
     * return defaults rather than each paying the full connect timeout.
     */
    private bool $redisDown = false;

    /**
     * Increment a user's inbound-event counter for the current month.
     *
     * No-op when metering is disabled or running in 'broadcast'-only mode, so
     * callers at the input boundaries can fire unconditionally.
     */
    public function record(int $userId, int $count = 1): void
    {
        if ($count < 1 || ! config('metering.enabled', true)) {
            return;
        }

        if (config('metering.meter_mode', 'both') === 'broadcast') {
            return;
        }

        $this->withRedis(function (Connection $redis) use ($userId, $count) {
            $key = $this->key($userId);
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
     * Current event count for a user in the given month (default: now).
     */
    public function usageFor(int $userId, ?string $period = null): int
    {
        $value = $this->withRedis(
            fn (Connection $redis) => $redis->get($this->key($userId, $period)),
            null,
        );

        return $value === null ? 0 : (int) $value;
    }

    /**
     * The most recent $months counters, newest first. One mget round-trip.
     *
     * The `broadcasts` array key is kept for frontend compatibility with the
     * existing Usage history strip - the value now counts inbound events.
     *
     * @return array<int, array{period: string, broadcasts: int}>
     */
    public function historyFor(int $userId, int $months = 6): array
    {
        $periods = [];
        $cursor = now()->startOfMonth();
        for ($i = 0; $i < $months; $i++) {
            $periods[] = $cursor->format('Y-m');
            $cursor = $cursor->subMonthNoOverflow();
        }

        $keys = array_map(fn (string $period) => $this->key($userId, $period), $periods);

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
     * The free-tier monthly event ceiling, or null when running observe-only.
     */
    public function freeLimit(): ?int
    {
        $limit = config('metering.free_monthly_events');

        if ($limit === null || $limit === '') {
            return null;
        }

        return (int) $limit;
    }

    /**
     * Compact usage summary for the dashboard strip and Usage page. The
     * `broadcasts` key is retained for frontend compatibility; it now carries
     * the inbound-event count.
     *
     * @return array{broadcasts: int, limit: int|null, period: string}
     */
    public function summaryFor(int $userId): array
    {
        return [
            'broadcasts' => $this->usageFor($userId),
            'limit' => $this->freeLimit(),
            'period' => now()->format('Y-m'),
        ];
    }

    public function key(int $userId, ?string $period = null): string
    {
        $period ??= now()->format('Y-m');

        return "metering:events:{$userId}:{$period}";
    }

    /**
     * Run a Redis op, failing fast and non-fatally. Once anything fails this
     * request, $redisDown short-circuits every later call. Metering is
     * best-effort - a failure degrades to "uncounted"/zero, never a broken
     * request or a hung page.
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
            $this->redisDown = true;
            report($e);

            return $default;
        }
    }

    private function redis(): Connection
    {
        $connection = config('metering.event_redis_connection')
            ?: config('metering.redis_connection', 'default');

        return Redis::connection($connection);
    }
}
