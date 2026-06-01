<?php

use App\Support\ListItems;

// ──────────────────────────────────────────────────────────────────────────────
// make() - the single validating constructor
// ──────────────────────────────────────────────────────────────────────────────

it('builds the full canonical item shape', function () {
    $item = ListItems::make(7, 'Tacos', 1716500000, 'Lunch', 3, '#ff8800');

    expect($item)->toBe([
        'id' => 7,
        'value' => 'Tacos',
        'added_at' => 1716500000,
        'label' => 'Lunch',
        'weight' => 3,
        'color' => '#ff8800',
    ]);
});

it('defaults label/color to null and weight to 1', function () {
    $item = ListItems::make(1, 'x', 100);

    expect($item['label'])->toBeNull()
        ->and($item['color'])->toBeNull()
        ->and($item['weight'])->toBe(1);
});

it('never strips user content: empty value is preserved', function () {
    expect(ListItems::make(1, '', 100)['value'])->toBe('');
});

it('coerces non-string values to strings', function () {
    expect(ListItems::make(1, 42, 100)['value'])->toBe('42')
        ->and(ListItems::make(1, 3.5, 100)['value'])->toBe('3.5')
        ->and(ListItems::make(1, true, 100)['value'])->toBe('1')
        ->and(ListItems::make(1, null, 100)['value'])->toBe('');
});

it('treats an empty-string label as absent', function () {
    expect(ListItems::make(1, 'x', 100, '')['label'])->toBeNull()
        ->and(ListItems::make(1, 'x', 100, 'Hi')['label'])->toBe('Hi');
});

it('clamps weight to a positive number, defaulting bad input to 1', function () {
    expect(ListItems::make(1, 'x', 100, null, 0)['weight'])->toBe(1)
        ->and(ListItems::make(1, 'x', 100, null, -5)['weight'])->toBe(1)
        ->and(ListItems::make(1, 'x', 100, null, 'nope')['weight'])->toBe(1)
        ->and(ListItems::make(1, 'x', 100, null, INF)['weight'])->toBe(1);
});

it('keeps integer-valued weights as ints and fractional weights as floats', function () {
    expect(ListItems::make(1, 'x', 100, null, 2.0)['weight'])->toBe(2)
        ->and(ListItems::make(1, 'x', 100, null, 2.5)['weight'])->toBe(2.5)
        ->and(ListItems::make(1, 'x', 100, null, '4')['weight'])->toBe(4);
});

it('validates color, keeping only #rgb / #rrggbb and lowercasing it', function () {
    expect(ListItems::make(1, 'x', 100, null, 1, '#ABC')['color'])->toBe('#abc')
        ->and(ListItems::make(1, 'x', 100, null, 1, '  #AABBCC  ')['color'])->toBe('#aabbcc')
        ->and(ListItems::make(1, 'x', 100, null, 1, 'red')['color'])->toBeNull()
        ->and(ListItems::make(1, 'x', 100, null, 1, '#12')['color'])->toBeNull()
        ->and(ListItems::make(1, 'x', 100, null, 1, '#12345')['color'])->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// appendValue() - chat + control-driven append
// ──────────────────────────────────────────────────────────────────────────────

it('appends one item with the given id and reindexes', function () {
    $items = ListItems::freshFromValues(['a', 'b'], 1, 100)['items'];
    $next = ListItems::appendValue($items, 'c', 3, 200);

    expect($next)->toHaveCount(3)
        ->and($next[2]['id'])->toBe(3)
        ->and($next[2]['value'])->toBe('c')
        ->and($next[2]['added_at'])->toBe(200)
        ->and(array_keys($next))->toBe([0, 1, 2]);
});

it('defaults added_at to now when not given', function () {
    $before = now()->timestamp;
    $item = ListItems::appendValue([], 'a', 1)[0];

    expect($item['added_at'])->toBeGreaterThanOrEqual($before);
});

// ──────────────────────────────────────────────────────────────────────────────
// freshFromValues() - de-novo (store / restore / recipe install)
// ──────────────────────────────────────────────────────────────────────────────

it('builds sequential ids from a start id and reports the next id', function () {
    $out = ListItems::freshFromValues(['a', 'b', 'c'], 5, 100);

    expect(array_column($out['items'], 'id'))->toBe([5, 6, 7])
        ->and($out['next_id'])->toBe(8)
        ->and(array_column($out['items'], 'value'))->toBe(['a', 'b', 'c']);
});

it('handles an empty value array', function () {
    $out = ListItems::freshFromValues([], 5, 100);

    expect($out['items'])->toBe([])
        ->and($out['next_id'])->toBe(5);
});

// ──────────────────────────────────────────────────────────────────────────────
// reconcileByValue() - dashboard textarea save
// ──────────────────────────────────────────────────────────────────────────────

it('preserves matched items across a reorder, minting fresh ones for new values', function () {
    $old = [
        ListItems::make(1, 'a', 100, null, 1, '#aaaaaa'),
        ListItems::make(2, 'b', 200),
        ListItems::make(3, 'c', 300),
    ];

    // reorder to c, b, then add new value d
    $out = ListItems::reconcileByValue($old, ['c', 'b', 'd'], 4, 999);

    expect(array_column($out['items'], 'value'))->toBe(['c', 'b', 'd'])
        ->and(array_column($out['items'], 'id'))->toBe([3, 2, 4])
        ->and($out['items'][0]['added_at'])->toBe(300)   // c kept its age
        ->and($out['items'][2]['added_at'])->toBe(999)   // d is fresh
        ->and($out['next_id'])->toBe(5);
});

it('preserves the rich fields (color/weight) of a matched item', function () {
    $old = [ListItems::make(1, 'a', 100, 'Label A', 5, '#ff0000')];
    $out = ListItems::reconcileByValue($old, ['a'], 2, 999);

    expect($out['items'][0])->toBe([
        'id' => 1,
        'value' => 'a',
        'added_at' => 100,
        'label' => 'Label A',
        'weight' => 5,
        'color' => '#ff0000',
    ])->and($out['next_id'])->toBe(2);
});

it('matches duplicates oldest-first', function () {
    $old = [
        ListItems::make(1, 'dup', 100),
        ListItems::make(2, 'dup', 200),
    ];
    // keep a single 'dup' -> should reuse the oldest (id 1, ts 100)
    $out = ListItems::reconcileByValue($old, ['dup'], 3, 999);

    expect($out['items'])->toHaveCount(1)
        ->and($out['items'][0]['id'])->toBe(1)
        ->and($out['items'][0]['added_at'])->toBe(100);
});

it('drops removed values', function () {
    $old = ListItems::freshFromValues(['a', 'b', 'c'], 1, 100)['items'];
    $out = ListItems::reconcileByValue($old, ['b'], 4, 999);

    expect(array_column($out['items'], 'value'))->toBe(['b'])
        ->and($out['items'][0]['id'])->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// fromLegacy() - the schema migration path
// ──────────────────────────────────────────────────────────────────────────────

it('converts legacy string + timestamp arrays into object items', function () {
    $out = ListItems::fromLegacy(['A', 'B'], [111, 222], 999);

    expect($out['items'])->toBe([
        ['id' => 1, 'value' => 'A', 'added_at' => 111, 'label' => null, 'weight' => 1, 'color' => null],
        ['id' => 2, 'value' => 'B', 'added_at' => 222, 'label' => null, 'weight' => 1, 'color' => null],
    ])->and($out['next_id'])->toBe(3);
});

it('falls back to the supplied timestamp when a stamp is missing or non-numeric', function () {
    $out = ListItems::fromLegacy(['A', 'B', 'C'], [111], 999);

    expect(array_column($out['items'], 'added_at'))->toBe([111, 999, 999]);
});

it('handles a legacy empty list', function () {
    $out = ListItems::fromLegacy([], [], 999);

    expect($out['items'])->toBe([])
        ->and($out['next_id'])->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// adopt() - snapshot restore
// ──────────────────────────────────────────────────────────────────────────────

it('preserves ids and rich fields when adopting objects, reseating next_id past the max', function () {
    $snapshot = [
        ListItems::make(3, 'a', 100, 'Label', 5, '#abcdef'),
        ListItems::make(7, 'b', 200),
    ];
    $out = ListItems::adopt($snapshot, 4, 999);

    expect(array_column($out['items'], 'id'))->toBe([3, 7])
        ->and($out['items'][0]['color'])->toBe('#abcdef')
        ->and($out['items'][0]['weight'])->toBe(5)
        ->and($out['items'][0]['added_at'])->toBe(100)
        ->and($out['next_id'])->toBe(8); // max(4, 7+1)
});

it('refreshes added_at but keeps ids/rich fields when asked', function () {
    $snapshot = [ListItems::make(2, 'a', 100, 'L', 3, '#fff')];
    $out = ListItems::adopt($snapshot, 5, 999, refreshAddedAt: true);

    expect($out['items'][0]['id'])->toBe(2)
        ->and($out['items'][0]['added_at'])->toBe(999) // refreshed to now
        ->and($out['items'][0]['weight'])->toBe(3)
        ->and($out['items'][0]['color'])->toBe('#fff')
        ->and($out['next_id'])->toBe(5); // max(5, 2+1)
});

it('mints ids for legacy string-array snapshots', function () {
    $out = ListItems::adopt(['a', 'b'], 5, 999);

    expect(array_column($out['items'], 'id'))->toBe([5, 6])
        ->and(array_column($out['items'], 'value'))->toBe(['a', 'b'])
        ->and($out['next_id'])->toBe(7);
});

// ──────────────────────────────────────────────────────────────────────────────
// removeAt() - draw / pop
// ──────────────────────────────────────────────────────────────────────────────

it('removes the item at an index and reindexes', function () {
    $items = ListItems::freshFromValues(['a', 'b', 'c'], 1, 100)['items'];
    $out = ListItems::removeAt($items, 1);

    expect(array_column($out, 'value'))->toBe(['a', 'c'])
        ->and(array_keys($out))->toBe([0, 1]);
});

it('is a no-op for an out-of-range index', function () {
    $items = ListItems::freshFromValues(['a'], 1, 100)['items'];

    expect(ListItems::removeAt($items, 9))->toBe($items);
});

// ──────────────────────────────────────────────────────────────────────────────
// values() - reader back-compat
// ──────────────────────────────────────────────────────────────────────────────

it('extracts value strings from object items', function () {
    $items = [
        ListItems::make(1, 'a', 100),
        ListItems::make(2, 'b', 200),
    ];

    expect(ListItems::values($items))->toBe(['a', 'b']);
});

it('tolerates raw strings during the transition', function () {
    expect(ListItems::values(['a', 'b']))->toBe(['a', 'b']);
});
