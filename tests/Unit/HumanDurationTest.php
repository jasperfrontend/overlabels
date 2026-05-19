<?php

use App\Support\HumanDuration;
use Carbon\Carbon;

it('returns "just now" for a zero span', function () {
    $now = Carbon::parse('2026-05-20 12:00:00');
    expect(HumanDuration::between($now, $now))->toBe('just now');
});

it('formats seconds', function () {
    $start = Carbon::parse('2026-05-20 12:00:00');
    $end = $start->copy()->addSeconds(45);
    expect(HumanDuration::between($start, $end))->toBe('45 seconds');
});

it('formats single units with singular nouns', function () {
    $start = Carbon::parse('2026-05-20 12:00:00');
    $end = $start->copy()->addHour();
    expect(HumanDuration::between($start, $end))->toBe('1 hour');
});

it('skips zero units in the middle of the breakdown', function () {
    $start = Carbon::parse('2020-01-15 10:00:00');
    $end = Carbon::parse('2025-01-15 10:30:00');

    // 5 years and 30 minutes - months, days, hours all zero
    expect(HumanDuration::between($start, $end))->toBe('5 years, 30 minutes');
});

it('returns a top-down multi-unit breakdown', function () {
    $start = Carbon::parse('2020-02-15 08:00:00');
    $end = Carbon::parse('2025-06-27 17:04:00');

    expect(HumanDuration::between($start, $end))
        ->toBe('5 years, 4 months, 12 days, 9 hours, 4 minutes');
});

it('caps at max units', function () {
    $start = Carbon::parse('2020-02-15 08:00:00');
    $end = Carbon::parse('2025-06-27 17:04:30');

    expect(HumanDuration::between($start, $end, 3))
        ->toBe('5 years, 4 months, 12 days');
});

it('handles reversed argument order by swapping internally', function () {
    $a = Carbon::parse('2026-05-20 12:00:00');
    $b = Carbon::parse('2025-05-20 12:00:00');

    expect(HumanDuration::between($a, $b))->toBe('1 year');
});
