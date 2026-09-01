<?php

use App\Services\Messages\PipeFormatter;

/**
 * Pins the |distance: contract on the PHP side to what formatters.ts
 * documents and implements: input KILOMETERS, km passthrough, mi converts,
 * locale-formatted with at most two fraction digits, no args or non-numeric
 * input passes through unchanged.
 *
 * This existed nowhere before: the PHP formatter shipped assuming meters and
 * the only coverage (a resolver feature test) pinned that divergence with a
 * fixture holding "8704 meters" no real control ever stores. The vitest side
 * covers formatters.ts; this is its PHP twin for the distance pipe.
 */
test('km is a locale-formatted passthrough', function () {
    expect(PipeFormatter::apply('8.7', 'distance:km', 'en-US'))->toBe('8.7')
        ->and(PipeFormatter::apply('12345.678', 'distance:km', 'en-US'))->toBe('12,345.68');
});

test('mi converts from km', function () {
    // 8.7 km / 1.609344 = 5.4059... -> two fraction digits.
    expect(PipeFormatter::apply('8.7', 'distance:mi', 'en-US'))->toBe('5.41');
});

test('locale drives the number formatting', function () {
    // The /help/formatting table shows 11,61 for nl-NL - same value here.
    expect(PipeFormatter::apply('11.61', 'distance:km', 'nl-NL'))->toBe('11,61');
});

test('no unit argument passes the raw value through, like formatters.ts', function () {
    expect(PipeFormatter::apply('8.7', 'distance', 'en-US'))->toBe('8.7');
});

test('an unknown unit falls back to km, like formatters.ts', function () {
    // JS only special-cases 'mi'; everything else formats as km. The old PHP
    // 'm' and 'ft' units existed in neither the JS side nor any docs.
    expect(PipeFormatter::apply('8.7', 'distance:m', 'en-US'))->toBe('8.7');
});

test('non-numeric input passes through unchanged', function () {
    expect(PipeFormatter::apply('soon', 'distance:km', 'en-US'))->toBe('soon');
});
