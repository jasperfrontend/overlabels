<?php

namespace App\Services\Lists;

use App\Events\ListUpdated;
use App\Jobs\FlushEventFeed;
use App\Models\ExternalEvent;
use App\Models\OptionSet;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Support\ListItems;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The orchestration layer behind "recent events -> list" feeds. Lists opt in
 * via their `event_feed` config (set from /recents). Two entry
 * points:
 *
 *   - handleEvent(): called by EventFeedAppender for every incoming Twitch /
 *     external event. Finds the user's enabled feed lists whose type filter
 *     accepts the event, formats one line, and appends it (FIFO past
 *     max_items, same locked-write discipline as the list_writer control).
 *   - seed(): called once when a feed is switched on, so the widget is
 *     populated immediately from the events that already happened rather than
 *     sitting empty until the next live event.
 *
 * Append + seed share appendLine()/buildItems() so the FIFO + id-allocation
 * rules live in exactly one place.
 */
class EventFeedService
{
    /**
     * Default FIFO window applied when a feed is enabled on a list that has
     * no max_items set. Keeps the full-state ListUpdated broadcast well under
     * the Reverb 10 KB payload cap and matches a sensible "recent events"
     * widget length.
     */
    public const int DEFAULT_FEED_CAP = 50;

    /** How long a feed collects lines before writing them, on the Twitch lane path. */
    public const int BATCH_WINDOW_SECONDS = 1;

    public function __construct(
        private readonly RecentEventFormatter $formatter,
    ) {}

    /**
     * Append the formatted line for one event to every enabled feed list of
     * the user that accepts this event_type. No-op when the user has no feed
     * lists, so the per-event cost is a single indexed query.
     *
     * @param  array<string, mixed>|null  $eventData
     * @param  array<string, mixed>|null  $normalizedPayload
     */
    public function handleEvent(User $user, string $source, string $eventType, ?array $eventData, ?array $normalizedPayload): void
    {
        $lists = OptionSet::where('user_id', $user->id)
            ->whereNotNull('event_feed')
            ->where('event_feed->enabled', true)
            ->get();

        if ($lists->isEmpty()) {
            return;
        }

        $line = null; // formatted lazily; only pay for it once a list matches
        foreach ($lists as $list) {
            if (! $list->eventFeedAccepts($eventType)) {
                continue;
            }
            $line ??= $this->formatter->format($source, $eventType, $eventData, $normalizedPayload);
            $this->appendLine($list->id, $line, $user);
        }
    }

    /**
     * The Twitch event path, run on the streamer's event lane (#343). Same
     * lines as handleEvent(), but a flood writes once per window instead of
     * once per event: the first event in a quiet window is written straight
     * away, everything inside the window waits in the cache and goes in one
     * write - by the next event after the window, or by FlushEventFeed when
     * the window ends quietly. A single event therefore shows as fast as
     * before, and 3,000 follows cost a few dozen list writes and broadcasts
     * instead of 3,000.
     *
     * Only safe because a streamer's lane runs one job at a time: the pending
     * lines are a read-modify-write with no lock. External events keep
     * handleEvent(); they arrive one per donation, never as a flood.
     *
     * The pending lines live in the cache store, which may evict them under
     * memory pressure; a feed then misses those lines, nothing else breaks.
     *
     * @param  array<string, mixed>|null  $eventData
     */
    public function handleTwitchEventOnLane(User $user, string $eventType, ?array $eventData): void
    {
        $lists = $this->feedLists($user);
        if (! $lists->contains(fn (OptionSet $list) => $list->eventFeedAccepts($eventType))) {
            return;
        }

        $pending = Cache::get(self::pendingKey($user->id), []);
        $pending[] = ['type' => $eventType, 'line' => $this->formatter->format('twitch', $eventType, $eventData, null)];

        if (Cache::add(self::windowKey($user->id), 1, self::BATCH_WINDOW_SECONDS)) {
            Cache::forget(self::pendingKey($user->id));
            $this->appendPending($user, $lists, $pending);

            return;
        }

        Cache::put(self::pendingKey($user->id), $pending, now()->addMinutes(10));

        if (Cache::add(self::trailingKey($user->id), 1, 60)) {
            FlushEventFeed::dispatch($user->id, (string) $user->twitch_id)
                ->delay(now()->addSeconds(self::BATCH_WINDOW_SECONDS));
        }
    }

    /** FlushEventFeed: write whatever a window left waiting. */
    public function flushPending(User $user): void
    {
        Cache::forget(self::trailingKey($user->id));

        $pending = Cache::pull(self::pendingKey($user->id), []);
        if ($pending === []) {
            return;
        }

        $this->appendPending($user, $this->feedLists($user), $pending);
    }

    /** @return Collection<int, OptionSet> */
    private function feedLists(User $user): Collection
    {
        return OptionSet::where('user_id', $user->id)
            ->whereNotNull('event_feed')
            ->where('event_feed->enabled', true)
            ->get();
    }

    /**
     * @param  Collection<int, OptionSet>  $lists
     * @param  list<array{type: string, line: string}>  $pending
     */
    private function appendPending(User $user, Collection $lists, array $pending): void
    {
        foreach ($lists as $list) {
            $lines = array_values(array_map(
                fn (array $entry) => $entry['line'],
                array_filter($pending, fn (array $entry) => $list->eventFeedAccepts($entry['type'])),
            ));

            if ($lines !== []) {
                $this->appendLines($list->id, $lines, $user);
            }
        }
    }

    private static function windowKey(int $userId): string
    {
        return "event-feed:window:{$userId}";
    }

    private static function pendingKey(int $userId): string
    {
        return "event-feed:pending:{$userId}";
    }

    private static function trailingKey(int $userId): string
    {
        return "event-feed:trailing:{$userId}";
    }

    /**
     * Replace a freshly-enabled feed list's items with the most recent events
     * that match its type filter, oldest-first so the newest sits last (the
     * same order live appends produce). Honours the list's max_items window.
     */
    public function seed(OptionSet $list, User $user): void
    {
        $types = $list->eventFeedTypes();
        $cap = $list->max_items ?? self::DEFAULT_FEED_CAP;

        $lines = $this->recentLines($user->id, $types, $cap);

        $built = ListItems::freshFromValues($lines, $list->next_item_id);
        $list->update([
            'items' => $built['items'],
            'next_item_id' => $built['next_id'],
        ]);

        ListUpdated::dispatchFor((string) $user->twitch_id, $list->fresh());
    }

    /**
     * Append one value to the target list, FIFO-dropping past max_items.
     * Row-locked inside a transaction so two near-simultaneous events can't
     * race the items array. Mirrors ListWriterAppend::appendToList - control
     * and event feeds share the same "keep latest N" semantics.
     */
    private function appendLine(int $listId, string $value, User $user): void
    {
        $this->appendLines($listId, [$value], $user);
    }

    /**
     * Append several values in order in one locked write and one broadcast.
     *
     * @param  list<string>  $values
     */
    private function appendLines(int $listId, array $values, User $user): void
    {
        DB::transaction(function () use ($listId, $values, $user) {
            /** @var OptionSet|null $list */
            $list = OptionSet::lockForUpdate()->find($listId);
            if (! $list || $list->user_id !== $user->id || $list->disabled_at !== null) {
                return;
            }

            $itemId = $list->next_item_id;
            $items = $list->items ?? [];
            foreach ($values as $value) {
                $items = ListItems::appendValue($items, $value, $itemId);
                $itemId++;
            }

            if ($list->max_items !== null && count($items) > $list->max_items) {
                $overflow = count($items) - $list->max_items;
                $items = array_values(array_slice($items, $overflow));
            }

            $list->update([
                'items' => $items,
                'next_item_id' => $itemId,
            ]);

            ListUpdated::dispatchFor((string) $user->twitch_id, $list->fresh());
        });
    }

    /**
     * The most recent Twitch + external events for the user, optionally
     * narrowed to a type whitelist, formatted into feed lines and returned
     * oldest-first (newest last). GPS events are excluded, matching the
     * recents view.
     *
     * @param  array<int, string>  $types
     * @return array<int, string>
     */
    private function recentLines(int $userId, array $types, int $limit): array
    {
        $twitch = TwitchEvent::where('user_id', $userId)
            ->when($types !== [], fn ($q) => $q->whereIn('event_type', $types))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (TwitchEvent $e) => [
                'created_at' => $e->created_at,
                'line' => $this->formatter->format('twitch', $e->event_type, $e->event_data, null),
            ]);

        $external = ExternalEvent::where('user_id', $userId)
            ->where('service', '!=', 'gps')
            ->when($types !== [], fn ($q) => $q->whereIn('event_type', $types))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (ExternalEvent $e) => [
                'created_at' => $e->created_at,
                'line' => $this->formatter->format($e->service, $e->event_type, null, $e->normalized_payload),
            ]);

        return $twitch->concat($external)
            ->sortByDesc('created_at')   // newest first
            ->take($limit)               // cap to the FIFO window
            ->reverse()                  // flip to oldest-first for appending
            ->pluck('line')
            ->values()
            ->all();
    }
}
