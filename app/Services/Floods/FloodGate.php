<?php

namespace App\Services\Floods;

use App\Enums\DeliveryOutcome;
use App\Events\TwitchEventReceived;
use App\Http\Controllers\TwitchEventSubController;
use App\Jobs\FloodTick;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\Alerts\AlertBursts;
use App\Services\EventMeter;
use App\Services\Lists\EventFeedService;
use App\Services\LivingTitleService;
use App\Services\StreamSessionService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Floods become numbers (#346, Jasper 2026-11-26): "big data turns into a big
 * number; the payload is discarded". During a flood of follows or channel
 * point redemptions only the last events matter; the rest is a count.
 *
 * At intake (offer()), per streamer and kind, the events of one burst - no gap
 * longer than KEEP_OPEN_SECONDS between arrivals - are counted. The first
 * PASS_THROUGH are handled exactly as always: stored, queued, alerted. Every
 * one after that is never stored and never queued: it is counted, and its
 * payload is kept in a rolling list of the last KEPT. A redelivery of a counted
 * event is recognised by its message id, since there is no row to catch it.
 *
 * On the streamer's lane (tick(), every TICK_SECONDS while the flood lasts),
 * the new count is applied in one go: the per-stream counter and the meter by
 * N, one overlay broadcast carrying `flood_count` (the overlay adds it to
 * followers_total; the payload is the newest kept event, so user_* is the
 * latest follower), the burst grown by N so a long burst is still announced
 * again as it doubles, and ONE summary row per flood holding the count
 * (outcome flood_counted, event_data.flood_count). When the flood is over the
 * kept events become ordinary rows and the summary row holds the rest:
 * "+5,400 more".
 *
 * Subs, cheers, gifts and raids are never counted away: they are rare next to
 * follows and they are money.
 *
 * The counts live in the cache store. The cache Redis evicts under memory
 * pressure; a flood's few keys are the last thing it would pick, and losing
 * one costs part of a count, nothing else.
 */
final class FloodGate
{
    /** The event types a flood is made of. */
    public const array EVENT_TYPES = ['channel.follow', 'channel.channel_points_custom_reward_redemption.add'];

    /** Events of one burst handled one by one before the rest are counted. */
    public const int PASS_THROUGH = 100;

    /** Counted events whose payload is kept: the flood's last ones. */
    public const int KEPT = 100;

    /** A gap this long between arrivals ends the burst at intake. Same gap as AlertBursts. */
    public const int KEEP_OPEN_SECONDS = AlertBursts::KEEP_OPEN_SECONDS;

    /** How often a flood's count is applied while it lasts. */
    public const int TICK_SECONDS = 2;

    /** How long a counted event's message id is remembered against redelivery. */
    public const int DEDUP_SECONDS = 900;

    private const int STATE_TTL_SECONDS = 3600;

    public const string PASS = 'pass';

    public const string COUNTED = 'counted';

    public const string DUPLICATE = 'duplicate';

    /**
     * At intake, before the row is written. PASS: handle it as always.
     * COUNTED: counted, not stored, not queued. DUPLICATE: a redelivery of an
     * event already counted.
     *
     * @param  array<string, mixed>  $event  the scrubbed payload
     */
    public function offer(User $user, string $eventType, array $event, ?string $messageId): string
    {
        if (! in_array($eventType, self::EVENT_TYPES, true)) {
            return self::PASS;
        }

        if ($messageId !== null && Cache::has(self::messageKey($messageId))) {
            return self::DUPLICATE;
        }

        $kind = AlertBursts::kindOf($eventType, $event) ?? $eventType;
        $base = self::base($user->id, $kind);

        $open = Cache::has("{$base}:last");
        Cache::put("{$base}:last", 1, self::KEEP_OPEN_SECONDS);
        if (! $open) {
            Cache::put("{$base}:n", 0, self::STATE_TTL_SECONDS);
        }

        if ((int) Cache::increment("{$base}:n") <= self::PASS_THROUGH) {
            return self::PASS;
        }

        if ($messageId !== null) {
            Cache::put(self::messageKey($messageId), 1, self::DEDUP_SECONDS);
        }
        Cache::add("{$base}:dropped", 0, self::STATE_TTL_SECONDS);
        Cache::increment("{$base}:dropped");
        $this->keep($base, $event);

        if (Cache::add("{$base}:tick", 1, 60)) {
            FloodTick::dispatch($user->id, (string) $user->twitch_id, $eventType, $kind)
                ->delay(now()->addSeconds(self::TICK_SECONDS));
        }

        return self::COUNTED;
    }

    /**
     * On the streamer's lane: apply what was counted since the last tick,
     * then either come back in TICK_SECONDS or, once the flood is over,
     * finish it.
     */
    public function tick(User $user, string $eventType, string $kind): void
    {
        $base = self::base($user->id, $kind);

        $n = (int) Cache::get("{$base}:dropped", 0);
        if ($n > 0) {
            // Decrement rather than reset: events counted meanwhile survive.
            Cache::decrement("{$base}:dropped", $n);
            $this->apply($user, $eventType, $kind, $base, $n);
        }

        if ($n > 0 || Cache::has("{$base}:last")) {
            FloodTick::dispatch($user->id, (string) $user->twitch_id, $eventType, $kind)
                ->delay(now()->addSeconds(self::TICK_SECONDS));

            return;
        }

        $this->finish($user, $eventType, $base);
    }

    /** A tick whose streamer is gone: drop the state, nothing to apply it to. */
    public function forget(int $userId, string $kind): void
    {
        foreach (['last', 'n', 'dropped', 'tail', 'tick', 'summary'] as $key) {
            Cache::forget(self::base($userId, $kind).":{$key}");
        }
    }

    private function apply(User $user, string $eventType, string $kind, string $base, int $n): void
    {
        $tail = Cache::get("{$base}:tail", []);
        $latest = $tail !== [] ? end($tail) : [];

        $summary = $this->summary($user, $eventType, $base, $latest);
        $this->addToSummary($summary, $n);

        app(EventMeter::class)->record($user->id, $n);
        app(StreamSessionService::class)->handleEvent($user, $eventType, [], $n);

        // Once per tick what processStoredEvent() does per event for the ones it
        // handles: the follower/goal cache refresh and the living title. Both
        // are rate-limited on their own (Jasper, 2026-11-26; OL-2611-039 F1).
        app(TwitchEventSubController::class)->refreshCachesForEvent($eventType, (string) $user->twitch_id);
        app(LivingTitleService::class)->schedule($user);

        // Its own message_id: the overlay drops an event whose id it has seen,
        // and without one a follow's id is built from its followed_at, so two
        // ticks with the same newest follower would lose the second count.
        broadcast((new TwitchEventReceived(
            $eventType,
            [...$latest, 'flood_count' => $n, 'message_id' => 'flood-'.Str::uuid()],
            (string) $user->twitch_id,
        ))->markFeedHandled());

        if (app(AlertBursts::class)->grow($user, $kind, $n) === AlertBursts::REANNOUNCE && $latest !== []) {
            $this->reannounce($user, $eventType, $kind, $base, $summary);
        }
    }

    /**
     * The burst doubled while flooding: the newest kept event becomes a row of
     * its own and plays the burst alert with the running count. It leaves the
     * count it was part of, so nothing is counted twice.
     */
    private function reannounce(User $user, string $eventType, string $kind, string $base, TwitchEvent $summary): void
    {
        $event = $this->takeNewest($base);
        if ($event === null) {
            return;
        }

        $row = TwitchEvent::create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'event_data' => $event,
            'twitch_timestamp' => now(),
            'processed' => false,
        ]);
        $this->addToSummary($summary, -1);

        $burst = app(AlertBursts::class)->announced($user, $kind);
        if ($burst !== null) {
            $event['burst_count'] = $burst['count'];
            $event['burst_started_at'] = $burst['started_at'];
        }

        app(TwitchEventSubController::class)->fireAlert($user, ['subscription' => ['type' => $eventType], 'event' => $event], $row);
    }

    /**
     * The flood is over: the kept events become ordinary rows, the summary
     * keeps the rest, and the recent-events feed gets one line for the flood.
     */
    private function finish(User $user, string $eventType, string $base): void
    {
        $tail = Cache::get("{$base}:tail", []);
        $summaryId = Cache::get("{$base}:summary");
        $summary = $summaryId ? TwitchEvent::find($summaryId) : null;

        if ($tail !== []) {
            $now = now();
            DB::table('twitch_events')->insert(array_map(fn (array $event) => [
                'user_id' => $user->id,
                'event_type' => $eventType,
                'event_data' => json_encode($event),
                'twitch_timestamp' => $now,
                'processed' => false,
                'outcome' => DeliveryOutcome::BurstAbsorbed->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_values($tail)));
        }

        if ($summary !== null) {
            $this->addToSummary($summary, -count($tail));
            $rest = (int) ($summary->event_data['flood_count'] ?? 0);

            if ($rest > 0) {
                app(EventFeedService::class)->handleTwitchEventOnLane($user, $eventType, $summary->event_data);
            } else {
                // Every counted event was kept: nothing for "+N more" to say.
                $summary->delete();
            }
        }

        foreach (['tail', 'summary', 'tick', 'dropped', 'n'] as $key) {
            Cache::forget("{$base}:{$key}");
        }
    }

    /** @param  array<string, mixed>  $latest */
    private function summary(User $user, string $eventType, string $base, array $latest): TwitchEvent
    {
        $id = Cache::get("{$base}:summary");
        if ($id && ($row = TwitchEvent::find($id))) {
            return $row;
        }

        $row = TwitchEvent::create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'event_data' => array_filter([
                'broadcaster_user_id' => (string) $user->twitch_id,
                'broadcaster_user_login' => $latest['broadcaster_user_login'] ?? null,
                'broadcaster_user_name' => $latest['broadcaster_user_name'] ?? null,
                'reward' => $latest['reward'] ?? null,
                'flood_count' => 0,
                'flood_started_at' => now()->toIso8601String(),
            ], fn ($v) => $v !== null),
            'twitch_timestamp' => now(),
            'processed' => false,
            'outcome' => DeliveryOutcome::FloodCounted,
        ]);
        Cache::put("{$base}:summary", $row->id, self::STATE_TTL_SECONDS);

        return $row;
    }

    private function addToSummary(TwitchEvent $summary, int $by): void
    {
        $data = $summary->event_data;
        $data['flood_count'] = max(0, (int) ($data['flood_count'] ?? 0) + $by);
        $data['flood_ended_at'] = now()->toIso8601String();
        $summary->forceFill(['event_data' => $data])->save();
    }

    /** @param  array<string, mixed>  $event */
    private function keep(string $base, array $event): void
    {
        $this->withTail($base, function (array $tail) use ($event) {
            $tail[] = $event;

            return array_slice($tail, -self::KEPT);
        });
    }

    /** @return array<string, mixed>|null */
    private function takeNewest(string $base): ?array
    {
        $newest = null;
        $this->withTail($base, function (array $tail) use (&$newest) {
            $newest = array_pop($tail);

            return $tail;
        });

        return $newest;
    }

    /**
     * Read-modify-write of the kept list under a short lock: up to ten front
     * desk batches write it at once. A lock not got in time skips the write;
     * the count is exact either way, only the kept list loses an entry.
     *
     * @param  callable(array<int, array<string, mixed>>): array<int, array<string, mixed>>  $change
     */
    private function withTail(string $base, callable $change): void
    {
        try {
            Cache::lock("{$base}:tail-lock", 5)->block(2, function () use ($base, $change) {
                Cache::put("{$base}:tail", $change(Cache::get("{$base}:tail", [])), self::STATE_TTL_SECONDS);
            });
        } catch (LockTimeoutException) {
            // See above.
        }
    }

    private static function base(int $userId, string $kind): string
    {
        return "flood:{$userId}:{$kind}";
    }

    private static function messageKey(string $messageId): string
    {
        return "flood:msg:{$messageId}";
    }
}
