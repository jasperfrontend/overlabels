<?php

namespace App\Support;

/**
 * The single chokepoint for building and validating List item objects.
 *
 * A List item is a fixed-shape associative array:
 *
 *   [
 *     'id'       => int,          // server-assigned, stable for the item's life,
 *                                 //   unique-in-list, never reused
 *     'value'    => string,       // the primary content (REQUIRED; may be empty -
 *                                 //   we never strip user content)
 *     'added_at' => int,          // Unix seconds, when it was appended
 *     'label'    => string|null,  // optional human display label (null = absent)
 *     'weight'   => int|float,    // picker weight; positive, defaults to 1
 *     'color'    => string|null,  // optional hex (#rgb / #rrggbb), null = absent
 *   ]
 *
 * Ids come from a per-list monotonic counter (`option_sets.next_item_id`):
 * the allocating mutator passes the next id in and persists the returned
 * `next_id`. This class never touches the database - it is pure functions so
 * it can be unit-tested in isolation and reused by the schema migration.
 *
 * This replaces ListItemTimestamps: the per-item `added_at` lives inside the
 * item, so the fragile parallel `item_added_at` array (and its sync discipline)
 * goes away.
 */
final class ListItems
{
    /** Picker weight when none is supplied or a bad one is given. */
    public const int DEFAULT_WEIGHT = 1;

    /**
     * Build one canonical, validated item object. Every other builder here
     * funnels through this, so validation lives in exactly one place:
     *   - value is coerced to a string (empty allowed, never stripped)
     *   - weight is clamped to a positive number, defaulting to DEFAULT_WEIGHT
     *   - color is kept only if it is a valid hex string, else null
     *   - label is kept only if it is a non-empty string, else null
     *
     * @return array{id:int,value:string,added_at:int,label:?string,weight:int|float,color:?string}
     */
    public static function make(
        int $id,
        mixed $value,
        int $addedAt,
        mixed $label = null,
        mixed $weight = self::DEFAULT_WEIGHT,
        mixed $color = null,
    ): array {
        return [
            'id' => $id,
            'value' => self::coerceValue($value),
            'added_at' => $addedAt,
            'label' => self::normalizeLabel($label),
            'weight' => self::normalizeWeight($weight),
            'color' => self::normalizeColor($color),
        ];
    }

    /**
     * Append one new value as a fresh item with the given id. The caller
     * (chat append, control-driven list_writer append) bumps next_item_id
     * to id + 1 after this returns.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, array{id:int,value:string,added_at:int,label:?string,weight:int|float,color:?string}>
     */
    public static function appendValue(array $items, mixed $value, int $id, ?int $now = null): array
    {
        $items = array_values($items);
        $items[] = self::make($id, $value, $now ?? now()->timestamp);

        return $items;
    }

    /**
     * Build a list of items de-novo from a flat array of value strings,
     * assigning sequential ids from $startId. Used by store, snapshot
     * restore, and recipe install (the manifest authors plain strings).
     *
     * @param  array<int, mixed>  $values
     * @return array{items: array<int, array{id:int,value:string,added_at:int,label:?string,weight:int|float,color:?string}>, next_id: int}
     */
    public static function freshFromValues(array $values, int $startId, ?int $now = null): array
    {
        $now ??= now()->timestamp;
        $id = $startId;
        $items = [];
        foreach (array_values($values) as $value) {
            $items[] = self::make($id, $value, $now);
            $id++;
        }

        return ['items' => $items, 'next_id' => $id];
    }

    /**
     * Reconcile a wholesale value-array edit (the dashboard textarea save)
     * against the existing item objects. For each new value we preserve the
     * whole matching old item - its id, added_at, label, weight, color - if
     * one exists (oldest match wins for duplicates); otherwise we mint a fresh
     * item with the next id and `now`. Values removed from the list silently
     * drop. This is the object-era successor to
     * ListItemTimestamps::preserveByValue, extended to carry the rich fields.
     *
     *   - reordering values keeps their ids/ages/colors/weights
     *   - removing a value drops its item
     *   - adding a value mints a fresh item
     *   - renaming is "remove old, add new" (fresh item, loses rich fields)
     *
     * @param  array<int, array<string, mixed>>  $oldItems
     * @param  array<int, mixed>  $newValues
     * @return array{items: array<int, array{id:int,value:string,added_at:int,label:?string,weight:int|float,color:?string}>, next_id: int}
     */
    public static function reconcileByValue(array $oldItems, array $newValues, int $nextId, ?int $now = null): array
    {
        $now ??= now()->timestamp;

        // value-string -> queue of full old items, oldest first.
        $byValue = [];
        foreach (array_values($oldItems) as $item) {
            $key = self::coerceValue($item['value'] ?? '');
            $byValue[$key][] = $item;
        }

        $id = $nextId;
        $result = [];
        foreach (array_values($newValues) as $value) {
            $key = self::coerceValue($value);
            if (! empty($byValue[$key])) {
                $matched = array_shift($byValue[$key]);
                $hasId = isset($matched['id']);
                $result[] = self::make(
                    $hasId ? (int) $matched['id'] : $id,
                    $matched['value'] ?? $value,
                    isset($matched['added_at']) ? (int) $matched['added_at'] : $now,
                    $matched['label'] ?? null,
                    $matched['weight'] ?? self::DEFAULT_WEIGHT,
                    $matched['color'] ?? null,
                );
                // A matched-but-id-less item (pathological, e.g. pre-migration
                // data) consumes a fresh id so we never emit a duplicate.
                if (! $hasId) {
                    $id++;
                }
            } else {
                $result[] = self::make($id, $value, $now);
                $id++;
            }
        }

        return ['items' => $result, 'next_id' => $id];
    }

    /**
     * Convert a legacy {items: string[], item_added_at: int[]} pair into
     * object items, assigning ids 1..n. A missing or non-numeric stamp falls
     * back to $fallbackTs (the row's created_at, per the migration design).
     * Used by the schema migration and by list_snapshots conversion.
     *
     * @param  array<int, mixed>  $values
     * @param  array<int, mixed>  $stamps
     * @return array{items: array<int, array{id:int,value:string,added_at:int,label:?string,weight:int|float,color:?string}>, next_id: int}
     */
    public static function fromLegacy(array $values, array $stamps, int $fallbackTs): array
    {
        $values = array_values($values);
        $stamps = array_values($stamps);
        $items = [];
        foreach ($values as $i => $value) {
            $stamp = $stamps[$i] ?? null;
            $addedAt = is_numeric($stamp) ? (int) $stamp : $fallbackTs;
            $items[] = self::make($i + 1, $value, $addedAt);
        }

        return ['items' => $items, 'next_id' => count($items) + 1];
    }

    /**
     * Remove the item at $index and reindex. Used by draw and pop. With
     * objects there is no parallel array to keep in sync, so this is a plain
     * splice - the named helper just keeps the reindex discipline in one place
     * and reads clearly at the call site.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    public static function removeAt(array $items, int $index): array
    {
        $items = array_values($items);
        if (! array_key_exists($index, $items)) {
            return $items;
        }
        unset($items[$index]);

        return array_values($items);
    }

    /**
     * Extract the plain value strings from item objects. This is the
     * backward-compat anchor every scalar reader leans on (`:sum`, `:first`,
     * `:last`, the bare `c:list:slug` string-array tag, snapshot display).
     * Defensively tolerates raw strings too, so it is safe to call during the
     * transition before all data is objects.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, string>
     */
    public static function values(array $items): array
    {
        return array_map(
            static fn ($item): string => self::coerceValue(is_array($item) ? ($item['value'] ?? '') : $item),
            array_values($items),
        );
    }

    private static function coerceValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        // Arrays/objects have no sensible string form here; collapse to empty
        // rather than throw - this only ever fires on malformed input.
        return '';
    }

    private static function normalizeLabel(mixed $label): ?string
    {
        return is_string($label) && $label !== '' ? $label : null;
    }

    private static function normalizeWeight(mixed $weight): int|float
    {
        if (! is_numeric($weight)) {
            return self::DEFAULT_WEIGHT;
        }
        $f = (float) $weight;
        if (! is_finite($f) || $f <= 0) {
            return self::DEFAULT_WEIGHT;
        }

        // Keep integer-valued weights as ints so they ride the wire and render
        // without a spurious ".0"; genuine fractional weights stay floats.
        return ($f == (int) $f) ? (int) $f : $f;
    }

    private static function normalizeColor(mixed $color): ?string
    {
        if (! is_scalar($color)) {
            return null;
        }
        $c = strtolower(trim((string) $color));

        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $c) === 1 ? $c : null;
    }
}
