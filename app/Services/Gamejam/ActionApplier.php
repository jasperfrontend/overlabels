<?php

namespace App\Services\Gamejam;

use App\Models\Game;
use App\Models\GameDoor;
use App\Models\GameHiddenTile;

class ActionApplier
{
    public function apply(Game $game, ?string $action): void
    {
        if ($action === null) {
            return;
        }

        if (str_starts_with($action, 'p:')) {
            $this->move($game, substr($action, 2));

            return;
        }

        if ($action === 'h') {
            $this->hide($game);

            return;
        }

        // a, a:1, a:2 - no-op for room 1 (no zombies yet)
    }

    private function move(Game $game, string $direction): void
    {
        $delta = match ($direction) {
            'up' => [0, -1],
            'down' => [0, 1],
            'left' => [-1, 0],
            'right' => [1, 0],
            default => null,
        };

        if ($delta === null) {
            return;
        }

        $targetX = $game->player_x + $delta[0];
        $targetY = $game->player_y + $delta[1];

        if ($targetX < RoomSeeder::GRID_MIN || $targetX > RoomSeeder::GRID_MAX
            || $targetY < RoomSeeder::GRID_MIN || $targetY > RoomSeeder::GRID_MAX) {
            return;
        }

        $door = $game->doors->first(fn (GameDoor $d) => $d->room === $game->current_room
            && $d->x === $targetX
            && $d->y === $targetY);

        if ($door) {
            if ($door->state !== GameDoor::STATE_OPEN) {
                $this->progressDoor($door);

                return;
            }

            $game->update(['player_x' => $targetX, 'player_y' => $targetY]);

            if ($this->isExitDoor($game, $targetX, $targetY)) {
                $game->update(['status' => Game::STATUS_WON]);
            }

            return;
        }

        $game->update(['player_x' => $targetX, 'player_y' => $targetY]);

        $tile = $game->hiddenTiles->first(fn (GameHiddenTile $t) => $t->room === $game->current_room
            && $t->x === $targetX
            && $t->y === $targetY
            && $t->revealed_at_round === null);

        if ($tile) {
            $this->revealTile($game, $tile);
        }
    }

    private function progressDoor(GameDoor $door): void
    {
        $newTurns = ($door->turns_remaining ?? 1) - 1;
        if ($newTurns <= 0) {
            $door->update([
                'state' => GameDoor::STATE_OPEN,
                'turns_remaining' => null,
            ]);
        } else {
            $door->update([
                'state' => GameDoor::STATE_OPENING,
                'turns_remaining' => $newTurns,
            ]);
        }
    }

    private function isExitDoor(Game $game, int $x, int $y): bool
    {
        // Room 1 exit is top-middle; treat any door on the top row as an exit for now.
        return $y === 1;
    }

    private function revealTile(Game $game, GameHiddenTile $tile): void
    {
        $tile->update(['revealed_at_round' => $game->current_round]);

        match ($tile->content) {
            GameHiddenTile::CONTENT_REGULAR_SWORD => $game->update([
                'weapon_slot_1' => Game::WEAPON_REGULAR_SWORD,
                'weapon_slot_1_uses' => $tile->payload['uses'] ?? 10,
            ]),
            GameHiddenTile::CONTENT_DE_SWORD => $game->update([
                'weapon_slot_2' => Game::WEAPON_DE_SWORD,
            ]),
            GameHiddenTile::CONTENT_IRON_FISTS => $game->update([
                'wears_iron_fists' => true,
            ]),
            GameHiddenTile::CONTENT_BOMB => $this->applyBomb($game),
            GameHiddenTile::CONTENT_HP_RESTORE => $game->update([
                'player_hp' => $game->player_hp + ($tile->payload['amount'] ?? 1),
            ]),
            default => null,
        };
    }

    private function applyBomb(Game $game): void
    {
        $newHp = $game->player_hp - 1;
        $game->update(['player_hp' => $newHp]);
        if ($newHp <= 0) {
            $game->update(['status' => Game::STATUS_LOST]);
        }
    }

    private function hide(Game $game): void
    {
        $onSpot = $game->hidingSpots->contains(
            fn ($s) => $s->room === $game->current_room
                && $s->x === $game->player_x
                && $s->y === $game->player_y
        );

        if ($onSpot) {
            $game->update(['player_hiding_this_round' => true]);
        }
    }
}
