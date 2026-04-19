<?php

namespace App\Services\Gamejam;

use App\Models\Game;
use App\Models\GameBlocker;
use App\Models\GameDoor;
use App\Models\GameHiddenTile;
use App\Models\GameHidingSpot;
use App\Models\GameZombie;

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
        $this->seedZombieAtDistance($game, 1, hp: 3, damage: 1, kind: GameZombie::KIND_REGULAR, minDistFromSpawn: 3, reserved: $reserved);
    }

    public function seedRoom2(Game $game): void
    {
        $this->baseRoom($game, 2, exitTurns: 3);

        $reserved = [self::SPAWN, self::EXIT];

        $this->seedHidingSpot($game, 2, [2, 4], $reserved);
        $this->seedHidingSpot($game, 2, [8, 6], $reserved);
        $this->seedHiddenTile($game, 2, [5, 5], GameHiddenTile::CONTENT_DE_SWORD, null, $reserved);
        $this->seedRandomHiddenPool($game, 2, bombs: 2, heals: 1, reserved: $reserved);
        $this->seedZombieAtDistance($game, 2, hp: 4, damage: 2, kind: GameZombie::KIND_REGULAR, minDistFromSpawn: 3, reserved: $reserved);
    }

    public function seedRoom3(Game $game): void
    {
        $this->baseRoom($game, 3, exitTurns: 3);

        $reserved = [self::SPAWN, self::EXIT];

        $this->seedHidingSpot($game, 3, [4, 5], $reserved);
        $this->seedHiddenTile($game, 3, [6, 5], GameHiddenTile::CONTENT_IRON_FISTS, null, $reserved);
        $this->seedRandomHiddenPool($game, 3, bombs: 3, heals: 1, reserved: $reserved);
        $this->seedZombieAtDistance($game, 3, hp: 6, damage: 3, kind: GameZombie::KIND_REGULAR, minDistFromSpawn: 4, reserved: $reserved);
    }

    public function seedRoom4(Game $game): void
    {
        $this->baseRoom($game, 4, exitTurns: 4);

        $reserved = [self::SPAWN, self::EXIT];

        $this->seedHidingSpot($game, 4, [5, 5], $reserved);
        $this->seedRandomHiddenPool($game, 4, bombs: 3, heals: 1, reserved: $reserved);
        // Room 4: guaranteed 4 regular zombies spread across the room.
        for ($i = 0; $i < 4; $i++) {
            $this->seedZombieAtDistance($game, 4, hp: 8, damage: 4, kind: GameZombie::KIND_REGULAR, minDistFromSpawn: 4, reserved: $reserved);
        }
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

        // Fixed corner HP-restore tiles for the boss fight per the GDD.
        foreach ([[1, 1], [9, 1], [1, 9], [9, 9]] as $corner) {
            $this->seedHiddenTile(
                $game,
                5,
                $corner,
                GameHiddenTile::CONTENT_HP_RESTORE,
                ['amount' => 5],
                $reserved,
            );
        }

        $this->seedRandomHiddenPool($game, 5, bombs: 2, heals: 0, reserved: $reserved);

        // Final boss at the centre of the room.
        $this->seedZombie(
            $game,
            room: 5,
            x: 5,
            y: 5,
            hp: 30,
            damage: 4,
            kind: GameZombie::KIND_BOSS,
            reserved: $reserved,
        );
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

    private function seedZombie(Game $game, int $room, int $x, int $y, int $hp, int $damage, string $kind, array &$reserved): void
    {
        GameZombie::create([
            'game_id' => $game->id,
            'room' => $room,
            'x' => $x,
            'y' => $y,
            'prev_x' => $x,
            'prev_y' => $y,
            'facing' => GameZombie::FACING_RIGHT,
            'hp' => $hp,
            'max_hp' => $hp,
            'damage' => $damage,
            'kind' => $kind,
            'brain_state' => GameZombie::STATE_DRIFTING,
            'active' => true,
        ]);

        $reserved[] = [$x, $y];
    }

    private function seedZombieAtDistance(Game $game, int $room, int $hp, int $damage, string $kind, int $minDistFromSpawn, array &$reserved): void
    {
        [$sx, $sy] = self::SPAWN;
        $candidates = [];
        for ($x = self::GRID_MIN; $x <= self::GRID_MAX; $x++) {
            for ($y = self::GRID_MIN; $y <= self::GRID_MAX; $y++) {
                if (abs($x - $sx) + abs($y - $sy) < $minDistFromSpawn) {
                    continue;
                }
                $taken = false;
                foreach ($reserved as [$rx, $ry]) {
                    if ($rx === $x && $ry === $y) {
                        $taken = true;
                        break;
                    }
                }
                if (! $taken) {
                    $candidates[] = [$x, $y];
                }
            }
        }

        if (empty($candidates)) {
            // Fall back to any free tile so a seed never silently no-ops.
            [$x, $y] = $this->randomFreeTile($reserved);
            $this->seedZombie($game, $room, $x, $y, $hp, $damage, $kind, $reserved);

            return;
        }

        [$x, $y] = $candidates[array_rand($candidates)];
        $this->seedZombie($game, $room, $x, $y, $hp, $damage, $kind, $reserved);
    }
}
