<?php

namespace App\Support;

/**
 * Helpers for keeping option_sets.item_added_at in sync with
 * option_sets.items. Every mutator that touches items must produce a
 * parallel item_added_at array of the same length; that's enforced by
 * call-site discipline, not by the database (we'd need a generated
 * column expression that PG can do but it's overkill for v1).
 *
 * All timestamps are Unix epoch seconds (the Overlabels-wide contract).
 */
final class ListItemTimestamps
{
    /**
     * Build a fresh timestamps array: one `now` entry per item. Used
     * when items are entered de-novo (store, clone-from-non-tracked
     * source, restore-from-snapshot).
     *
     * @param  array<int, mixed>  $items
     * @return array<int, int>
     */
    public static function freshFor(array $items, ?int $now = null): array
    {
        $now ??= now()->timestamp;

        return array_fill(0, count($items), $now);
    }

    /**
     * Append a new entry to an existing timestamps array. Caller must
     * also append the new item to the items array.
     *
     * @param  array<int, int>  $existing
     * @return array<int, int>
     */
    public static function append(array $existing, ?int $now = null): array
    {
        $existing[] = $now ?? now()->timestamp;

        return array_values($existing);
    }

    /**
     * Drop the timestamp at $index from the array. Caller must drop
     * the matching item from the items array too. Re-indexes so
     * subsequent indices line up.
     *
     * @param  array<int, int>  $timestamps
     * @return array<int, int>
     */
    public static function removeAt(array $timestamps, int $index): array
    {
        if (! array_key_exists($index, $timestamps)) {
            return array_values($timestamps);
        }
        unset($timestamps[$index]);

        return array_values($timestamps);
    }

    /**
     * Diff-style sync for dashboard saves where the user wholesale-
     * replaces the items array. For each new item, preserve the
     * timestamp of a matching old item if one exists (oldest match
     * wins for duplicates); otherwise use $now. Items removed from
     * the list silently drop their timestamps.
     *
     * Semantics aim to be predictable for the streamer:
     *   - reordering items keeps their ages
     *   - removing items removes their timestamps
     *   - adding new items gives them fresh timestamps
     *   - renaming an item is treated as "remove old, add new"
     *
     * @param  array<int, mixed>  $oldItems
     * @param  array<int, int>  $oldTimestamps
     * @param  array<int, mixed>  $newItems
     * @return array<int, int>
     */
    public static function preserveByValue(
        array $oldItems,
        array $oldTimestamps,
        array $newItems,
        ?int $now = null,
    ): array {
        $now ??= now()->timestamp;

        // Build a value->[timestamps] queue from the old data, oldest
        // first. Pop the front of the queue when we match.
        $byValue = [];
        foreach (array_values($oldItems) as $i => $val) {
            $key = (string) $val;
            $ts = $oldTimestamps[$i] ?? $now;
            $byValue[$key][] = $ts;
        }

        $newTimestamps = [];
        foreach ($newItems as $val) {
            $key = (string) $val;
            if (! empty($byValue[$key])) {
                $newTimestamps[] = (int) array_shift($byValue[$key]);
            } else {
                $newTimestamps[] = $now;
            }
        }

        return $newTimestamps;
    }
}
