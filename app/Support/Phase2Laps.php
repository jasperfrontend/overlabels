<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * PHASE2-TIMING (#343): temporary per-step timing of a follow on the events
 * worker. Remove together with its call sites once the flood has been read.
 */
final class Phase2Laps
{
    /** @var array<string, float> */
    private static array $laps = [];

    private static float $start = 0.0;

    private static float $at = 0.0;

    public static function start(): void
    {
        self::$laps = [];
        self::$start = self::$at = hrtime(true) / 1e6;
    }

    public static function lap(string $step): void
    {
        $now = hrtime(true) / 1e6;
        self::$laps[$step] = round((self::$laps[$step] ?? 0) + $now - self::$at, 2);
        self::$at = $now;
    }

    public static function log(string $eventType, int $eventId): void
    {
        if ($eventType !== 'channel.follow' || self::$start === 0.0) {
            return;
        }

        Log::info('phase2.follow_timing', [
            'event_id' => $eventId,
            'total_ms' => round(hrtime(true) / 1e6 - self::$start, 2),
            'laps_ms' => self::$laps,
        ]);
        self::$start = 0.0;
    }
}
