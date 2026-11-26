<?php

namespace App\Services\OverloadReports;

use App\Jobs\CloseBusyStretch;
use App\Models\BotChatOutbox;
use App\Models\OverloadReport;
use App\Models\StreamState;
use App\Models\User;
use App\Services\BotModeratedChannels;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;

/**
 * The busy stretch behind an Overlabels Overload Report (#345).
 *
 * A stretch opens with the first ticked event while the streamer is live (or
 * in test mode), stays open while ticked events keep coming, and closes after
 * a quiet spell (`quiet_seconds`, default 60). At close, a stretch that held at
 * least `threshold` events (default 50) becomes a report; a burst or a flood
 * counts as its individual events.
 *
 * The timeline is built as the events arrive, as aggregates (ReportKinds), in
 * one cache key per streamer. Everything here runs on the streamer's own lane
 * - processStoredEvent(), FloodTick and CloseBusyStretch all do - so the key is
 * never written by two workers at once. The cache store is evictable: losing
 * the key costs that stretch its report, nothing else.
 */
class BusyStretch
{
    /** How long "not live" is remembered, so an offline account's events do not each read stream_states. */
    private const int OFFLINE_MEMO_SECONDS = 30;

    /**
     * Note an event (or $times identical ones, from a flood tick) for the
     * streamer's stretch. Cheap when the feature is off or the kind unticked.
     */
    public function note(User $user, string $eventType, array $event, int $times = 1, ?CarbonInterface $at = null): void
    {
        $kind = ReportKinds::kindOf($eventType, $event);
        if ($kind === null || $times < 1 || ! self::settings($user)['enabled'] || ! self::ticks($user, $kind)) {
            return;
        }

        $key = self::key($user->id);
        $state = Cache::get($key);

        if (! is_array($state)) {
            if (! ReportKinds::opens($eventType) || ! $this->mayOpen($user)) {
                return;
            }
            $state = ['started_at' => ($at ?? now())->toIso8601String(), 'last' => null, 'n' => 0, 'timeline' => [], 'totals' => []];
            CloseBusyStretch::dispatch($user->id, (string) $user->twitch_id)
                ->delay(now()->addSeconds(self::settings($user)['quiet_seconds']));
        }

        $stamp = ($at ?? now())->toIso8601String();
        [$state['timeline'], $state['totals']] = ReportKinds::apply($state['timeline'], $state['totals'], $kind, $eventType, $event, $times, $stamp);
        $state['n'] += ReportKinds::weight($eventType, $event, $times);
        // The quiet spell runs from when the event was HANDLED, not from its
        // timestamp: a lane that is behind must not close a stretch that is
        // still pouring in.
        $state['last'] = now()->toIso8601String();

        Cache::put($key, $state, self::ttl($user));
    }

    /**
     * Run by CloseBusyStretch. Not quiet long enough yet: try again when it
     * would be. Quiet: forget the stretch, and make the report if it was busy
     * enough. Returns the report, if one was made.
     */
    public function close(User $user): ?OverloadReport
    {
        $key = self::key($user->id);
        $state = Cache::get($key);
        if (! is_array($state)) {
            return null;
        }

        $quiet = self::settings($user)['quiet_seconds'];
        $closesAt = CarbonImmutable::parse($state['last'] ?? $state['started_at'])->addSeconds($quiet);
        if ($closesAt->isFuture()) {
            CloseBusyStretch::dispatch($user->id, (string) $user->twitch_id)
                ->delay(now()->addSeconds(max(1, (int) ceil(now()->diffInSeconds($closesAt)))));

            return null;
        }

        Cache::forget($key);

        if ((int) $state['n'] < self::settings($user)['threshold'] || $state['timeline'] === []) {
            return null;
        }

        $report = $this->store($user, $state);
        $this->announce($user, $report);

        return $report;
    }

    /** Forget a stretch whose streamer is gone. */
    public static function forget(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    /**
     * The streamer's report settings, defaults filled in.
     *
     * @return array{enabled: bool, threshold: int, quiet_seconds: int, events: array<string, bool>, test_until: ?string}
     */
    public static function settings(User $user): array
    {
        $saved = (array) $user->preference('overload_report', []);
        $events = [];
        foreach (ReportKinds::KINDS as $kind => $meta) {
            $events[$kind] = (bool) ($saved['events'][$kind] ?? $meta['default']);
        }

        return [
            'enabled' => (bool) ($saved['enabled'] ?? true),
            'threshold' => max(1, (int) ($saved['threshold'] ?? 50)),
            'quiet_seconds' => max(10, (int) ($saved['quiet_seconds'] ?? 60)),
            'events' => $events,
            'test_until' => $saved['test_until'] ?? null,
        ];
    }

    /** Whether test mode is on right now (it switches itself off after an hour). */
    public static function testModeOn(User $user): bool
    {
        $until = self::settings($user)['test_until'];

        return is_string($until) && CarbonImmutable::parse($until)->isFuture();
    }

    /** Settings changed (test mode switched on, say): forget the offline memo. */
    public static function settingsChanged(User $user): void
    {
        Cache::forget(self::key($user->id).':offline');
    }

    private static function ticks(User $user, string $kind): bool
    {
        return self::settings($user)['events'][$kind] ?? false;
    }

    /** Live when the stretch starts, or test mode. */
    private function mayOpen(User $user): bool
    {
        if (self::testModeOn($user)) {
            return true;
        }

        $memo = self::key($user->id).':offline';
        if (Cache::has($memo)) {
            return false;
        }

        if (StreamState::forUser($user)->isConfidentlyLive()) {
            return true;
        }

        Cache::put($memo, 1, self::OFFLINE_MEMO_SECONDS);

        return false;
    }

    private function store(User $user, array $state): OverloadReport
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return OverloadReport::create([
                    'user_id' => $user->id,
                    'code' => str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT),
                    'started_at' => CarbonImmutable::parse($state['started_at']),
                    'ended_at' => CarbonImmutable::parse(end($state['timeline'])['last']),
                    'event_count' => (int) $state['n'],
                    'totals' => $state['totals'],
                    'timeline' => $state['timeline'],
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 4) {
                    throw $e;
                }
            }
        }
    }

    /**
     * One line in chat, only from a bot that can post links: enabled, and a
     * moderator in the channel. Unknown mod status posts nothing; the report
     * is on the dashboard either way.
     */
    private function announce(User $user, OverloadReport $report): void
    {
        if (! $user->bot_enabled || app(BotModeratedChannels::class)->moderates((string) $user->twitch_id) !== true) {
            return;
        }

        $seconds = (int) $report->started_at->diffInSeconds($report->ended_at);
        BotChatOutbox::create([
            'user_id' => $user->id,
            // The opt-out rides along: reports are on by default, and a streamer
            // who did not ask for them learns the way out where they appear.
            'message' => 'Overlabels Overload Report: '.ReportKinds::summary($report->totals, $seconds).' -> '.$report->url().' (!ol report stop turns these off)',
        ]);
    }

    private static function key(int $userId): string
    {
        return "overload:stretch:{$userId}";
    }

    /** Outlives the quiet spell, so a lost close job cannot pin a stretch open forever. */
    private static function ttl(User $user): int
    {
        return self::settings($user)['quiet_seconds'] * 3 + 60;
    }
}
