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
            $parts = explode(':', substr($action, 2));
            $direction = $parts[0];
            $steps = isset($parts[1]) ? max(1, (int) $parts[1]) : 1;
            $this->move($game, $direction, $steps);

            return;
        }

        if ($action === 'h') {
            $this->hide($game);

            return;
        }

        if ($action === 'a' || str_starts_with($action, 'a:')) {
            $slot = null;
            if (str_starts_with($action, 'a:')) {
                $slot = (int) substr($action, 2);
            }
            $this->attack($game, $slot);
        }
    }

    private function move(Game $game, string $direction, int $steps = 1): void
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

        for ($i = 0; $i < $steps; $i++) {
            if (! $this->stepOnce($game, $delta)) {
                return;
            }
        }
    }

    /**
     * Advance one tile. Returns true if the next step should be attempted,
     * false if the momentum is spent (wall, closed door, game end).
     */
    private function stepOnce(Game $game, array $delta): bool
    {
        $targetX = $game->player_x + $delta[0];
        $targetY = $game->player_y + $delta[1];

        if ($targetX < RoomSeeder::GRID_MIN || $targetX > RoomSeeder::GRID_MAX
            || $targetY < RoomSeeder::GRID_MIN || $targetY > RoomSeeder::GRID_MAX) {
            return false;
        }

        $door = $game->doors->first(fn (GameDoor $d) => $d->room === $game->current_room
            && $d->x === $targetX
            && $d->y === $targetY);

        if ($door && $door->state !== GameDoor::STATE_OPEN) {
            return false;
        }

        $game->update(['player_x' => $targetX, 'player_y' => $targetY]);

        if ($door && $this->isExitDoor($game, $targetX, $targetY)) {
            $game->update(['status' => Game::STATUS_WON]);

            return false;
        }

        if ($door) {
            return true;
        }

        $tile = $game->hiddenTiles->first(fn (GameHiddenTile $t) => $t->room === $game->current_room
            && $t->x === $targetX
            && $t->y === $targetY
            && $t->revealed_at_round === null);

        if ($tile) {
            $this->revealTile($game, $tile);
        }

        return $game->status === Game::STATUS_RUNNING;
    }

    private function attack(Game $game, ?int $slot): void
    {
        $weapon = $this->resolveAttackWeapon($game, $slot);
        if ($weapon === null) {
            return;
        }

        $doorsHit = $game->doors->filter(function (GameDoor $d) use ($game) {
            if ($d->room !== $game->current_room) {
                return false;
            }
            if ($d->state === GameDoor::STATE_OPEN) {
                return false;
            }
            $dx = abs($d->x - $game->player_x);
            $dy = abs($d->y - $game->player_y);

            return $dx <= 1 && $dy <= 1 && ! ($dx === 0 && $dy === 0);
        });

        if ($doorsHit->isEmpty()) {
            return;
        }

        $damage = $weapon === Game::WEAPON_DE_SWORD ? 2 : 1;

        foreach ($doorsHit as $door) {
            $this->damageDoor($door, $damage);
        }

        $this->applyAttackCost($game, $weapon);
    }

    private function resolveAttackWeapon(Game $game, ?int $slot): ?string
    {
        if ($slot === 2) {
            return $game->weapon_slot_2;
        }

        return $game->weapon_slot_1 ?? Game::WEAPON_FISTS;
    }

    private function damageDoor(GameDoor $door, int $damage): void
    {
        $newTurns = ($door->turns_remaining ?? 1) - $damage;
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

    private function applyAttackCost(Game $game, string $weapon): void
    {
        if ($weapon === Game::WEAPON_REGULAR_SWORD) {
            $uses = max(0, ($game->weapon_slot_1_uses ?? 0) - 1);
            if ($uses === 0) {
                $game->update([
                    'weapon_slot_1' => Game::WEAPON_FISTS,
                    'weapon_slot_1_uses' => null,
                ]);
            } else {
                $game->update(['weapon_slot_1_uses' => $uses]);
            }

            return;
        }

        if ($weapon === Game::WEAPON_FISTS && ! $game->wears_iron_fists) {
            $newHp = $game->player_hp - 1;
            $game->update(['player_hp' => $newHp]);
            if ($newHp <= 0) {
                $game->update(['status' => Game::STATUS_LOST]);
            }
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
        $spots = $game->hidingSpots->where('room', $game->current_room);
        if ($spots->isEmpty()) {
            return;
        }

        $nearest = $spots->sortBy(fn ($s) => abs($s->x - $game->player_x) + abs($s->y - $game->player_y))
            ->first();

        $game->update([
            'player_x' => $nearest->x,
            'player_y' => $nearest->y,
            'player_hiding_this_round' => true,
        ]);
    }
}
