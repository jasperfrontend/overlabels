<?php

namespace App\Services\Gamejam;

use App\Models\Game;
use App\Models\GameBlocker;
use App\Models\GameDoor;
use App\Models\GameHiddenTile;
use App\Models\GameHidingSpot;

class RoomSeeder
{
    public const int GRID_MIN = 1;

    public const int GRID_MAX = 9;

    private const array SPAWN = [5, 9];

    private const array EXIT = [5, 1];

    public function seedRoom1(Game $game): void
    {
        $game->update([
            'weapon_slot_1' => Game::WEAPON_FISTS,
            'weapon_slot_1_uses' => null,
            'weapon_slot_2' => null,
            'wears_iron_fists' => false,
        ]);

        $this->baseRoom($game, 1, exitTurns: 2);

        $reserved = [self::SPAWN, self::EXIT];

        $this->seedHidingSpot($game, 1, [3, 5], $reserved);
        $this->seedHiddenTile($game, 1, [5, 5], GameHiddenTile::CONTENT_REGULAR_SWORD, ['uses' => 10], $reserved);
        $this->seedRandomHiddenPool($game, 1, bombs: 2, heals: 1, reserved: $reserved);
    }

    public function seedRoom2(Game $game): void
    {
        $this->baseRoom($game, 2, exitTurns: 3);

        $reserved = [self::SPAWN, self::EXIT];

        $this->seedHidingSpot($game, 2, [2, 4], $reserved);
        $this->seedHidingSpot($game, 2, [8, 6], $reserved);
        $this->seedHiddenTile($game, 2, [5, 5], GameHiddenTile::CONTENT_DE_SWORD, null, $reserved);
        $this->seedRandomHiddenPool($game, 2, bombs: 2, heals: 1, reserved: $reserved);
    }

    public function seedRoom3(Game $game): void
    {
        $this->baseRoom($game, 3, exitTurns: 3);

        $reserved = [self::SPAWN, self::EXIT];

        $this->seedHidingSpot($game, 3, [4, 5], $reserved);
        $this->seedHiddenTile($game, 3, [6, 5], GameHiddenTile::CONTENT_IRON_FISTS, null, $reserved);
        $this->seedRandomHiddenPool($game, 3, bombs: 3, heals: 1, reserved: $reserved);
    }

    public function seedRoom4(Game $game): void
    {
        $this->baseRoom($game, 4, exitTurns: 4);

        $reserved = [self::SPAWN, self::EXIT];

        $this->seedHidingSpot($game, 4, [5, 5], $reserved);
        $this->seedRandomHiddenPool($game, 4, bombs: 3, heals: 1, reserved: $reserved);
    }

    public function seedRoom5(Game $game): void
    {
        $this->baseRoom($game, 5, exitTurns: 5);

        $reserved = [self::SPAWN, self::EXIT];

        // Four full-height pillars giving cover corridors for the boss fight.
        $pillars = [[3, 3], [3, 7], [7, 3], [7, 7]];
        foreach ($pillars as $pos) {
            GameBlocker::create([
                'game_id' => $game->id,
                'room' => 5,
                'x' => $pos[0],
                'y' => $pos[1],
            ]);
            $reserved[] = $pos;
        }

        $this->seedRandomHiddenPool($game, 5, bombs: 2, heals: 1, reserved: $reserved);
    }

    public function advanceTo(Game $game, int $room): void
    {
        match ($room) {
            1 => $this->seedRoom1($game),
            2 => $this->seedRoom2($game),
            3 => $this->seedRoom3($game),
            4 => $this->seedRoom4($game),
            5 => $this->seedRoom5($game),
            default => null,
        };
    }

    private function baseRoom(Game $game, int $room, int $exitTurns): void
    {
        $game->update([
            'current_room' => $room,
            'player_x' => self::SPAWN[0],
            'player_y' => self::SPAWN[1],
            'player_hiding_this_round' => false,
        ]);

        GameDoor::create([
            'game_id' => $game->id,
            'room' => $room,
            'x' => self::EXIT[0],
            'y' => self::EXIT[1],
            'state' => GameDoor::STATE_CLOSED,
            'turns_remaining' => $exitTurns,
            'is_exit' => true,
        ]);
    }

    private function seedHidingSpot(Game $game, int $room, array $pos, array &$reserved): void
    {
        GameHidingSpot::create([
            'game_id' => $game->id,
            'room' => $room,
            'x' => $pos[0],
            'y' => $pos[1],
        ]);
        $reserved[] = $pos;
    }

    private function seedHiddenTile(Game $game, int $room, array $pos, string $content, ?array $payload, array &$reserved): void
    {
        GameHiddenTile::create([
            'game_id' => $game->id,
            'room' => $room,
            'x' => $pos[0],
            'y' => $pos[1],
            'content' => $content,
            'payload' => $payload,
        ]);
        $reserved[] = $pos;
    }

    private function seedRandomHiddenPool(Game $game, int $room, int $bombs, int $heals, array $reserved): void
    {
        $plan = array_merge(
            array_fill(0, $bombs, GameHiddenTile::CONTENT_BOMB),
            array_fill(0, $heals, GameHiddenTile::CONTENT_HP_RESTORE),
        );
        shuffle($plan);

        foreach ($plan as $content) {
            [$x, $y] = $this->randomFreeTile($reserved);
            $reserved[] = [$x, $y];

            $payload = $content === GameHiddenTile::CONTENT_HP_RESTORE
                ? ['amount' => mt_rand(1, 5)]
                : null;

            GameHiddenTile::create([
                'game_id' => $game->id,
                'room' => $room,
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
