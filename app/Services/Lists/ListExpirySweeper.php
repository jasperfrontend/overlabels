<?php

namespace App\Services\Lists;

use App\Events\ListUpdated;
use App\Models\ListSnapshot;
use App\Models\OptionSet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Two sweeps, run together each tick:
 *
 *  1. Entry-TTL: for any list with entry_ttl_seconds set, drop items
 *     whose own added_at is older than now() - ttl. Broadcasts a
 *     ListUpdated when at least one entry was removed.
 *
 *  2. List expiry: for any list with expires_at <= now() AND
 *     disabled_at IS NULL, snapshot the current state (before_clear),
 *     clear items, and set disabled_at = expires_at so chat appenders
 *     silently no-op. Broadcasts a ListUpdated.
 *
 * Both sweeps are idempotent. Re-running on a freshly expired list is a
 * no-op because disabled_at is now non-null; re-running on an entry-TTL
 * list whose items are all young is a no-op because no items match the
 * cutoff.
 *
 * Disabled lists still get their entry-TTL applied: a list someone
 * disabled mid-stream should still age out its old entries instead of
 * keeping stale data alive.
 */
class ListExpirySweeper
{
    public function __construct(
        private readonly ListActionService $actions,
    ) {}

    /**
     * Returns: [entry_sweep_count, list_expire_count, items_removed]
     *
     * @return array{lists_swept:int, lists_expired:int, items_removed:int}
     */
    public function run(?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();
        $nowTs = $now->timestamp;

        $itemsRemoved = 0;
        $listsSwept = 0;
        $listsExpired = 0;

        // ── 1. Entry-TTL sweep ───────────────────────────────────────
        // Pull only lists that could possibly have something to remove:
        // entry_ttl_seconds set and at least one item. Postgres
        // jsonb_array_length keeps this efficient even with thousands of
        // lists in the table.
        $ttlLists = OptionSet::with('user')
            ->whereNotNull('entry_ttl_seconds')
            ->whereRaw('jsonb_array_length(items) > 0')
            ->get();

        foreach ($ttlLists as $list) {
            $removed = $this->sweepEntries($list, $nowTs);
            if ($removed > 0) {
                $listsSwept++;
                $itemsRemoved += $removed;
            }
        }

        // ── 2. List-expiry sweep ─────────────────────────────────────
        // Lists whose expires_at has passed and that haven't already been
        // disabled by a previous sweep tick.
        $expiringLists = OptionSet::with('user')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->whereNull('disabled_at')
            ->get();

        foreach ($expiringLists as $list) {
            $this->expireList($list);
            $listsExpired++;
        }

        return [
            'lists_swept' => $listsSwept,
            'lists_expired' => $listsExpired,
            'items_removed' => $itemsRemoved,
        ];
    }

    /**
     * Drop entries older than ttl from one list. Returns the count of
     * removed entries (0 if nothing aged out yet).
     */
    private function sweepEntries(OptionSet $list, int $nowTs): int
    {
        return DB::transaction(function () use ($list, $nowTs) {
            /** @var OptionSet|null $locked */
            $locked = OptionSet::lockForUpdate()->find($list->id);
            if (! $locked) {
                return 0;
            }

            $ttl = $locked->entry_ttl_seconds;
            if ($ttl === null || $ttl <= 0) {
                return 0;
            }

            $items = array_values($locked->items ?? []);
            if ($items === []) {
                return 0;
            }

            $cutoff = $nowTs - $ttl;
            $keptItems = [];
            $removed = 0;

            foreach ($items as $item) {
                // Each item carries its own added_at. An item with a
                // missing / non-numeric stamp gets a free pass - treated
                // as "infinitely young" until the next mutation refreshes
                // it - so a malformed row can never sweep itself away.
                $stamp = is_array($item) && is_numeric($item['added_at'] ?? null)
                    ? (int) $item['added_at']
                    : null;
                if ($stamp === null || $stamp > $cutoff) {
                    $keptItems[] = $item;

                    continue;
                }
                $removed++;
            }

            if ($removed === 0) {
                return 0;
            }

            $locked->update([
                'items' => array_values($keptItems),
            ]);

            ListUpdated::dispatchFor((string) ($locked->user?->twitch_id ?? ''), $locked->fresh());

            return $removed;
        });
    }

    /**
     * End-of-life for a list: snapshot, clear, set disabled_at to
     * expires_at (so it stays auditable as "expired at T", not just
     * "disabled by sweep at T+1m").
     */
    private function expireList(OptionSet $list): void
    {
        DB::transaction(function () use ($list) {
            /** @var OptionSet|null $locked */
            $locked = OptionSet::lockForUpdate()->find($list->id);
            if (! $locked || $locked->disabled_at !== null) {
                return;
            }

            // Snapshot only if there's anything to preserve. An expired
            // empty list still gets disabled, but doesn't pollute the
            // snapshot table with an empty row.
            if (! empty($locked->items)) {
                $this->actions->snapshot(
                    $locked,
                    ListSnapshot::REASON_BEFORE_CLEAR,
                    null, // sweeper has no triggering user
                );
            }

            $locked->update([
                'items' => [],
                'disabled_at' => $locked->expires_at,
            ]);

            ListUpdated::dispatchFor((string) ($locked->user?->twitch_id ?? ''), $locked->fresh());
        });
    }
}
