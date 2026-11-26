<?php

namespace App\Services\Alerts;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Many of the same Twitch event at once, one alert (#343).
 *
 * A raid arrives as a follow bomb: hundreds of `channel.follow` in a minute,
 * each its own alert, TTS line and chat line. The overlay queue drops most of
 * them anyway, the streamer is drowned out, and every one of them costs the
 * single events worker ~50 ms of alert work while every other streamer's
 * alerts wait behind it. So a burst is one alert.
 *
 * How it decides, per streamer and per kind (see kindOf()):
 *  - The first event is HELD: it opens a pending burst and the caller
 *    dispatches FlushAlertBurst with OPEN_WINDOW_SECONDS of delay. The worker
 *    carries on with everyone else's events meanwhile.
 *  - Events of the same kind that arrive before the flush JOIN it: stored,
 *    counted and broadcast as always, but no alert, TTS or chat line.
 *  - At the flush: one event plays its own alert, exactly as before. Two or
 *    more play ONE alert (the first event's, with `burst_count` and
 *    `burst_started_at` added) and the burst stays OPEN: every further event
 *    of that kind within KEEP_OPEN_SECONDS of the last one joins it too.
 *    A longer gap closes it.
 *  - While a burst stays open it is ANNOUNCED AGAIN, with its running count,
 *    each time the count has at least doubled since the last alert and at
 *    least REANNOUNCE_MIN_SECONDS have passed (Jasper, 2026-11-26). The event
 *    that tips it over plays the burst alert. Without this a long wave was
 *    one alert at its start: in the chaos run one follower burst lasted
 *    2 min 16 s and covered 6,081 follows, announced once at "20".
 *
 * One mechanism for any size, decided by rate and not by a magic count. Twitch
 * only: the donation services always alert once per donation.
 *
 * Gift recipients are not here: GiftSubAbsorber already makes a gift bomb one
 * alert and runs first. Cheers burst only across DIFFERENT viewers: one viewer
 * cheering again gets an alert per cheer (Jasper, 2026-11-24).
 *
 * The state is per streamer and lives in the cache. It is read and written
 * only by that streamer's events lane, which has exactly one worker
 * (ProcessTwitchEvent and FlushAlertBurst pick the same lane), so nothing
 * here races.
 */
final class AlertBursts
{
    /** How long the first event waits to see whether it is a burst. The database queue delays in whole seconds. */
    public const int OPEN_WINDOW_SECONDS = 1;

    /** How long an open burst waits for its next event before it closes. Tune here, nowhere else. */
    public const int KEEP_OPEN_SECONDS = 4;

    /**
     * How long a pending burst may wait for its flush. Long on purpose: the
     * flush queues behind whatever backlog the worker has, and a pending burst
     * that expired early would leave its joined events without any alert.
     * A flush that fails clears it (FlushAlertBurst::failed()).
     */
    public const int PENDING_TTL_SECONDS = 3600;

    /** An open burst is announced again once its count has grown by this factor since the last alert. */
    public const int REANNOUNCE_FACTOR = 2;

    /** ...and at least this long after the last alert. */
    public const int REANNOUNCE_MIN_SECONDS = 15;

    public const string HOLD = 'hold';

    public const string JOINED = 'joined';

    public const string ALONE = 'alone';

    public const string REANNOUNCE = 'reannounce';

    /**
     * What makes two events "the same thing", or null when this event never
     * bursts. Redemptions burst per reward: two hundred people redeeming one
     * reward is a bomb, two different rewards at once are two moments.
     */
    public static function kindOf(string $eventType, array $event): ?string
    {
        return match ($eventType) {
            'channel.follow', 'channel.subscription.message', 'channel.cheer' => $eventType,
            'channel.subscribe' => ($event['is_gift'] ?? false) === true ? null : $eventType,
            'channel.channel_points_custom_reward_redemption.add' => isset($event['reward']['id'])
                ? $eventType.':'.$event['reward']['id']
                : $eventType,
            default => null,
        };
    }

    /**
     * Offer an event to its kind's burst.
     *
     * HOLD: this event opened a pending burst; dispatch FlushAlertBurst.
     * JOINED: it is part of a burst; it gets no alert of its own.
     * ALONE: it plays its own alert now (a viewer's repeat cheer).
     * REANNOUNCE: it is part of an open burst that has doubled since its last
     * alert; it plays the burst alert with the running count (see announced()).
     */
    public function offer(User $user, string $kind, string $eventType, array $event): string
    {
        $key = self::key($user, $kind);
        $state = Cache::get($key);
        $actor = $eventType === 'channel.cheer' ? self::actorOf($event) : null;

        if (! is_array($state)) {
            Cache::put($key, [
                'phase' => 'pending',
                'count' => 1,
                'started_at' => now()->timestamp,
                'actors' => $actor !== null ? [$actor] : [],
            ], self::PENDING_TTL_SECONDS);

            return self::HOLD;
        }

        if ($actor !== null && in_array($actor, $state['actors'], true)) {
            return self::ALONE;
        }

        $state['count']++;
        if ($actor !== null) {
            $state['actors'][] = $actor;
        }

        $decision = self::JOINED;
        if (self::reannounces($state)) {
            $state['announced'] = $state['count'];
            $state['announced_at'] = now()->timestamp;
            $decision = self::REANNOUNCE;
        }

        $ttl = $state['phase'] === 'open' ? self::KEEP_OPEN_SECONDS : self::PENDING_TTL_SECONDS;
        Cache::put($key, $state, $ttl);

        return $decision;
    }

    /**
     * Add events counted by FloodGate (#346) to an existing burst of this kind:
     * they never pass through offer() one by one. Answers REANNOUNCE when that
     * makes the burst due another announcement, else JOINED. No burst, no
     * change: a burst only exists for a streamer with an alert mapped.
     */
    public function grow(User $user, string $kind, int $events): string
    {
        $key = self::key($user, $kind);
        $state = Cache::get($key);
        if (! is_array($state) || $events < 1) {
            return self::JOINED;
        }

        $state['count'] += $events;

        $decision = self::JOINED;
        if (self::reannounces($state)) {
            $state['announced'] = $state['count'];
            $state['announced_at'] = now()->timestamp;
            $decision = self::REANNOUNCE;
        }

        $ttl = $state['phase'] === 'open' ? self::KEEP_OPEN_SECONDS : self::PENDING_TTL_SECONDS;
        Cache::put($key, $state, $ttl);

        return $decision;
    }

    /**
     * The open burst's size and start as last announced, for the caller of an
     * offer() that answered REANNOUNCE.
     *
     * @return array{count: int, started_at: int}|null
     */
    public function announced(User $user, string $kind): ?array
    {
        $state = Cache::get(self::key($user, $kind));

        return is_array($state) ? ['count' => $state['count'], 'started_at' => $state['started_at']] : null;
    }

    /**
     * Whether offer() would answer JOINED for this event, without changing
     * anything. Asked before avatar enrichment: an event that joins a burst
     * plays no alert of its own, so its avatar lookup is skipped (#343).
     * A burst only ever exists for a streamer with an alert mapped, so this
     * is false for everyone else, exactly like offer() never being called.
     */
    public function wouldJoin(User $user, string $kind, string $eventType, array $event): bool
    {
        $state = Cache::get(self::key($user, $kind));
        if (! is_array($state)) {
            return false;
        }

        $actor = $eventType === 'channel.cheer' ? self::actorOf($event) : null;
        if ($actor !== null && in_array($actor, $state['actors'], true)) {
            return false;
        }

        // The event that re-announces plays an alert, so it is not silent.
        $state['count']++;

        return ! self::reannounces($state);
    }

    /**
     * Decide a pending burst. Returns null when it played alone (or there was
     * nothing to decide), else the burst's size and start, and leaves it open.
     *
     * @return array{count: int, started_at: int}|null
     */
    public function flush(User $user, string $kind): ?array
    {
        $key = self::key($user, $kind);
        $state = Cache::get($key);

        if (! is_array($state) || $state['phase'] !== 'pending' || $state['count'] < 2) {
            Cache::forget($key);

            return null;
        }

        $state['phase'] = 'open';
        $state['announced'] = $state['count'];
        $state['announced_at'] = now()->timestamp;
        Cache::put($key, $state, self::KEEP_OPEN_SECONDS);

        return ['count' => $state['count'], 'started_at' => $state['started_at']];
    }

    /** A flush that never ran must not leave its kind absorbing everything for an hour. */
    public function forget(User $user, string $kind): void
    {
        Cache::forget(self::key($user, $kind));
    }

    /**
     * Whether an open burst, with this event already counted, is due another
     * announcement. A burst opened before this existed has no `announced`:
     * it counts from now, so it never re-announces on the deploy itself.
     */
    private static function reannounces(array $state): bool
    {
        if ($state['phase'] !== 'open') {
            return false;
        }

        $announced = (int) ($state['announced'] ?? $state['count']);
        $announcedAt = (int) ($state['announced_at'] ?? now()->timestamp);

        return $state['count'] >= $announced * self::REANNOUNCE_FACTOR
            && now()->timestamp - $announcedAt >= self::REANNOUNCE_MIN_SECONDS;
    }

    /** Anonymous cheers carry no user id: each counts as a different viewer. */
    private static function actorOf(array $event): ?string
    {
        $id = $event['user_id'] ?? null;

        return ($event['is_anonymous'] ?? false) === true || $id === null ? null : (string) $id;
    }

    private static function key(User $user, string $kind): string
    {
        return "alert-burst:{$user->id}:{$kind}";
    }
}
