<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Format a duration between two timestamps as a human-readable string like
 * "5 years, 4 months, 12 days, 9 hours, 4 minutes". Designed for chat-bot
 * replies (!followage, !accountage) where StreamElements-style legibility
 * matters more than precision: we break down by calendar units (years,
 * months, days, hours, minutes, seconds), skip zero units entirely, and
 * cap at the largest N non-zero units so we never speak something like
 * "5 years and 0 months and 7 days and 0 hours...".
 *
 * Why not Carbon::diffForHumans()? It collapses to a single unit ("5 years
 * ago"), which loses the granularity streamers expect from these commands.
 * Why calendar units instead of fixed seconds-per-month math? Because a
 * follow that landed on 2020-02-29 should read "5 years, 2 days" on
 * 2025-03-02 - not "5 years, 1 day, 23 hours, 59 minutes" from accumulated
 * 30.44-day approximation drift.
 */
final class HumanDuration
{
    /** @var array<string,string> singular form keyed by Carbon diff method suffix */
    private const array UNITS = [
        'Years' => 'year',
        'Months' => 'month',
        'Days' => 'day',
        'Hours' => 'hour',
        'Minutes' => 'minute',
        'Seconds' => 'second',
    ];

    /**
     * Break the span between $from and $to into calendar units and return
     * the top $maxUnits non-zero units, comma-joined, plural-correct.
     * Returns "just now" if the span is empty (every unit is zero).
     */
    public static function between(CarbonInterface $from, CarbonInterface $to, int $maxUnits = 5): string
    {
        // Normalise to immutable so the original carbon instances aren't
        // mutated by our subSeconds() walk below.
        $start = CarbonImmutable::instance($from);
        $end = CarbonImmutable::instance($to);

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        $parts = [];
        $cursor = $start;

        // Walk top-down: take whole years first, advance the cursor, take
        // whole months from there, and so on. This is how Carbon's diff
        // already works internally - we just expose every unit instead of
        // the largest one. Doing it in-line (rather than calling Carbon's
        // %y %m %d format) keeps the calendar-correct behaviour around
        // month boundaries (Feb 29 -> Feb 28 etc.).
        foreach (self::UNITS as $diffMethod => $singular) {
            $diff = $cursor->{'diffIn'.$diffMethod}($end, false);
            $whole = (int) floor($diff);

            if ($whole > 0) {
                $parts[] = $whole.' '.$singular.($whole === 1 ? '' : 's');
                $cursor = $cursor->{'add'.$diffMethod}($whole);
            }

            if (count($parts) >= $maxUnits) {
                break;
            }
        }

        if (empty($parts)) {
            return 'just now';
        }

        return implode(', ', $parts);
    }
}
