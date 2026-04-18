<?php

namespace App\Services\Gamejam;

use App\Models\Game;
use App\Models\GameDoor;
use App\Models\GameHiddenTile;
use App\Models\GameHidingSpot;

class RoomSeeder
{
    public const int GRID_MIN = 1;

    public const int GRID_MAX = 9;

    public function seedRoom1(Game $game): void
    {
        $game->update([
            'current_room' => 1,
            'player_x' => 5,
            'player_y' => 9,
            'player_hiding_this_round' => false,
            'weapon_slot_1' => Game::WEAPON_FISTS,
            'weapon_slot_1_uses' => null,
            'weapon_slot_2' => null,
            'wears_iron_fists' => false,
        ]);

        GameDoor::create([
            'game_id' => $game->id,
            'room' => 1,
            'x' => 5,
            'y' => 1,
            'state' => GameDoor::STATE_CLOSED,
            'turns_remaining' => 2,
            'is_exit' => true,
        ]);

        GameHidingSpot::create([
            'game_id' => $game->id,
            'room' => 1,
            'x' => 3,
            'y' => 5,
        ]);

        GameHiddenTile::create([
            'game_id' => $game->id,
            'room' => 1,
            'x' => 5,
            'y' => 5,
            'content' => GameHiddenTile::CONTENT_REGULAR_SWORD,
            'payload' => ['uses' => 10],
        ]);

        $reserved = [
            [5, 9], // player spawn
            [5, 1], // exit door
            [5, 5], // sword
            [3, 5], // hiding spot
        ];

        $pool = [GameHiddenTile::CONTENT_BOMB, GameHiddenTile::CONTENT_HP_RESTORE];

        for ($i = 0; $i < 3; $i++) {
            [$x, $y] = $this->randomFreeTile($reserved);
            $reserved[] = [$x, $y];

            $content = $pool[array_rand($pool)];
            $payload = $content === GameHiddenTile::CONTENT_HP_RESTORE
                ? ['amount' => mt_rand(1, 5)]
                : null;

            GameHiddenTile::create([
                'game_id' => $game->id,
                'room' => 1,
                'x' => $x,
                'y' => $y,
                'content' => $content,
                'payload' => $payload,
            ]);
        }
    }

    private function randomFreeTile(array $reserved): array
    {
        do {
            $x = mt_rand(self::GRID_MIN, self::GRID_MAX);
            $y = mt_rand(self::GRID_MIN, self::GRID_MAX);
            $conflict = false;
            foreach ($reserved as [$rx, $ry]) {
                if ($rx === $x && $ry === $y) {
                    $conflict = true;
                    break;
                }
            }
        } while ($conflict);

        return [$x, $y];
    }
}
