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
 * Metering must NEVER break a broadcast: every Redis touch is wrapped so a
 * counter failure degrades to "uncounted", not "undelivered".
 */
class BroadcastMeter
{
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

        try {
            $key = $this->key($twitchId);
            $redis = $this->redis();
            $total = (int) $redis->incrby($key, $count);

            // First write of the month: stamp a TTL so the key self-expires and
            // we never need a scheduled reset job.
            if ($total === $count) {
                $redis->expire($key, (int) config('metering.ttl_days', 70) * 86400);
            }
        } catch (\Throwable $e) {
            // Observe-only: a metering failure must not stop the broadcast.
            report($e);
        }
    }

    /**
     * Current broadcast count for a user in the given month (default: now).
     */
    public function usageFor(string $twitchId, ?string $period = null): int
    {
        try {
            $value = $this->redis()->get($this->key($twitchId, $period));

            return $value === null ? 0 : (int) $value;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * The most recent $months counters, newest first. Powers the Usage page's
     * little history strip.
     *
     * @return array<int, array{period: string, broadcasts: int}>
     */
    public function historyFor(string $twitchId, int $months = 6): array
    {
        $history = [];
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $period = $cursor->format('Y-m');
            $history[] = [
                'period' => $period,
                'broadcasts' => $this->usageFor($twitchId, $period),
            ];
            $cursor = $cursor->subMonthNoOverflow();
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
     * Compact usage summary for sharing into Inertia / the dashboard.
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

    protected function redis(): Connection
    {
        return Redis::connection(config('metering.redis_connection', 'default'));
    }
}
