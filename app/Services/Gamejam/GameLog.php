<?php

namespace App\Services\Gamejam;

use App\Models\Game;
use Illuminate\Support\Str;

class GameLog
{
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

        $recap = $game->recap ?? [];
        $recap[] = $entry;

        $game->update([
            'log' => $log,
            'recap' => $recap,
        ]);
    }
}
