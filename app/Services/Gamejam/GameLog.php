<?php

namespace App\Services\Gamejam;

use App\Models\Game;
use Illuminate\Support\Str;

class GameLog
{
    /**
     * Max entries kept in the live `log` ticker. Older entries roll off so the
     * broadcast snapshot stays bounded regardless of game length; the full
     * history is preserved in `recap`. Tuned to keep the snapshot well under
     * Reverb's 10 KB per-message limit (an uncapped 50-entry log was ~6.4 KB
     * and tipped the wrapped broadcast request over the limit, crashing the game).
     */
    public const LIVE_LOG_LIMIT = 30;

    /**
     * Append an event to the game's log (rolling window for the live ticker)
     * and recap (full append-only history). Both columns are JSON; the live
     * ticker only ever broadcasts `log`, while `recap` is queried at end of
     * game for post-mortem display.
     */
    public static function append(Game $game, string $type, array $data = []): void
    {
        $entry = [
            'id' => (string) Str::uuid(),
            'at' => now()->timestamp,
            'type' => $type,
            'data' => $data,
        ];

        $log = $game->log ?? [];
        $log[] = $entry;
        // Roll the oldest entries off the live ticker so the broadcast snapshot
        // stays bounded; the full history is kept in `recap` below.
        $log = array_slice($log, -self::LIVE_LOG_LIMIT);

        $recap = $game->recap ?? [];
        $recap[] = $entry;

        $game->update([
            'log' => $log,
            'recap' => $recap,
        ]);
    }
}
