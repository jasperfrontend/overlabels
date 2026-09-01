<?php

use App\Services\Messages\PipeFormatter;

/**
 * Pins the PHP side of every pipe formatter to formatters.ts behavior -
 * formatters.ts is the contract (it is what overlays render), this class
 * mirrors it, quirks included. The distance pipe has its own file
 * (PipeFormatterDistanceTest); this covers the rest of the audit that added
 * speed and re-aligned round, number, currency, date and duration.
 *
 * Date assertions are structural (contains) where ICU versions disagree on
 * cosmetic whitespace; numbers and durations are exact.
 */

// ── speed ────────────────────────────────────────────────────────────────────

test('speed converts m/s to km/h with one fraction digit', function () {
    expect(PipeFormatter::apply('8.7', 'speed:kmh', 'en-US'))->toBe('31.3');
});

test('speed converts m/s to mph via km/h', function () {
    // 8.7 * 3.6 / 1.609344 = 19.46...
    expect(PipeFormatter::apply('8.7', 'speed:mph', 'en-US'))->toBe('19.5');
});

test('speed with no unit passes the raw value through, like formatters.ts', function () {
    expect(PipeFormatter::apply('8.7', 'speed', 'en-US'))->toBe('8.7');
});

test('speed with an unknown unit falls back to km/h', function () {
    expect(PipeFormatter::apply('8.7', 'speed:warp', 'en-US'))->toBe('31.3');
});

test('speed passes non-numeric input through', function () {
    expect(PipeFormatter::apply('fast', 'speed:kmh', 'en-US'))->toBe('fast');
});

// ── round ────────────────────────────────────────────────────────────────────

test('round pads to the precision like toFixed', function () {
    // The old PHP round() said "5"; the overlay says "5.00".
    expect(PipeFormatter::apply('5', 'round:2', 'en-US'))->toBe('5.00')
        ->and(PipeFormatter::apply('5.406', 'round:2', 'en-US'))->toBe('5.41')
        ->and(PipeFormatter::apply('5.6', 'round', 'en-US'))->toBe('6');
});

test('round returns the value unchanged on junk or negative precision', function () {
    expect(PipeFormatter::apply('5.4', 'round:abc', 'en-US'))->toBe('5.4')
        ->and(PipeFormatter::apply('5.4', 'round:-1', 'en-US'))->toBe('5.4');
});

// ── number ───────────────────────────────────────────────────────────────────

test('number with no precision keeps natural decimals', function () {
    // The old PHP number() forced zero decimals and said "1,235".
    expect(PipeFormatter::apply('1234.5', 'number', 'en-US'))->toBe('1,234.5')
        ->and(PipeFormatter::apply('1234567', 'number', 'en-US'))->toBe('1,234,567');
});

test('number with a precision pins both minimum and maximum digits', function () {
    expect(PipeFormatter::apply('1234.5', 'number:2', 'en-US'))->toBe('1,234.50');
});

test('number ignores junk precision like formatters.ts', function () {
    expect(PipeFormatter::apply('1234.5', 'number:abc', 'en-US'))->toBe('1,234.5');
});

test('number follows the locale', function () {
    expect(PipeFormatter::apply('1234.5', 'number', 'nl-NL'))->toBe('1.234,5');
});

// ── currency ─────────────────────────────────────────────────────────────────

test('currency with an explicit code formats as that currency', function () {
    expect(PipeFormatter::apply('12.34', 'currency:EUR', 'en-US'))->toBe('€12.34')
        ->and(PipeFormatter::apply('12.34', 'currency:eur', 'en-US'))->toBe('€12.34');
});

test('currency with no code mirrors the formatters.ts quirk for mapped locales', function () {
    // LOCALE_CURRENCY_MAP holds SYMBOLS, which Intl rejects, so the JS side
    // lands in its catch and returns a plain toFixed(2). Mirror, not fix:
    // fixing it means fixing the JS map first, in lockstep.
    expect(PipeFormatter::apply('12.34', 'currency', 'en-US'))->toBe('12.34')
        ->and(PipeFormatter::apply('12.34', 'currency', 'nl-NL'))->toBe('12.34');
});

test('currency with no code falls back to USD for unmapped locales', function () {
    expect(PipeFormatter::apply('12.34', 'currency', 'en'))->toBe('$12.34');
});

// ── duration ─────────────────────────────────────────────────────────────────

test('duration with no args auto-formats by magnitude', function () {
    // The old PHP default forced hh:mm:ss ("00:01:30"); the overlay says "1:30".
    expect(PipeFormatter::apply('90', 'duration', 'en-US'))->toBe('1:30')
        ->and(PipeFormatter::apply('8107', 'duration', 'en-US'))->toBe('2:15:07')
        ->and(PipeFormatter::apply('90061', 'duration', 'en-US'))->toBe('1d 1h 1m')
        ->and(PipeFormatter::apply('86402', 'duration', 'en-US'))->toBe('1d 2s');
});

test('duration keeps a negative sign instead of clamping to zero', function () {
    expect(PipeFormatter::apply('-90', 'duration', 'en-US'))->toBe('-1:30');
});

test('duration patterns overflow into the largest present unit', function () {
    expect(PipeFormatter::apply('3661', 'duration:mm:ss', 'en-US'))->toBe('61:01')
        ->and(PipeFormatter::apply('8107', 'duration:hh:mm:ss', 'en-US'))->toBe('02:15:07');
});

// ── date ─────────────────────────────────────────────────────────────────────
// 1788261900 = 2026-09-01 11:25:00 UTC; tests run with the app timezone (UTC).

test('date default is locale-aware, not Y-m-d', function () {
    $result = PipeFormatter::apply('1788261900', 'date', 'en-US');

    // The old PHP default said "2026-09-01 11:25" in every locale.
    expect($result)->toContain('Sep 1, 2026')
        ->and($result)->toContain('11:25');
});

test('date presets resolve instead of being read as format characters', function () {
    // "short"/"long"/"date"/"time" used to fall through to Carbon::format(),
    // which read the letters as format tokens and produced garbage.
    expect(PipeFormatter::apply('1788261900', 'date:date', 'en-US'))->toBe('Sep 1, 2026')
        ->and(PipeFormatter::apply('1788261900', 'date:date', 'nl-NL'))->toBe('1 sep 2026')
        ->and(PipeFormatter::apply('1788261900', 'date:time', 'en-US'))->toContain('11:25:00');
});

test('date custom patterns replace the six shared tokens', function () {
    expect(PipeFormatter::apply('1788261900', 'date:dd-MM-yyyy HH:mm', 'en-US'))->toBe('01-09-2026 11:25');
});

test('date passes unparseable input through', function () {
    expect(PipeFormatter::apply('soon', 'date', 'en-US'))->toBe('soon');
});
