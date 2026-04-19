<?php

namespace App\Services\Gamejam;

use App\Models\Game;
use App\Models\GameBlocker;
use App\Models\GameDoor;
use App\Models\GameHiddenTile;
use App\Models\GameZombie;

class ActionApplier
{
    /**
     * Zombies that attacked the player via a movement bump this tick. Returned
     * from apply() so the caller can pass them to the zombie-turn resolver,
     * which skips them in the adjacency-attack phase to avoid double-hitting.
     *
     * @var array<int>
     */
    private array $bumpedZombieIds = [];

    /**
     * @return array<int> ids of zombies that hit the player via a bump this tick
     */
    public function apply(Game $game, ?string $action): array
    {
        $this->bumpedZombieIds = [];

        if ($action === null) {
            return $this->bumpedZombieIds;
        }

        if (str_starts_with($action, 'p:')) {
            $parts = explode(':', substr($action, 2));
            $direction = $parts[0];
            $steps = isset($parts[1]) ? max(1, (int) $parts[1]) : 1;
            $this->move($game, $direction, $steps);

            return $this->bumpedZombieIds;
        }

        if ($action === 'h') {
            $this->hide($game);

            return $this->bumpedZombieIds;
        }

        if ($action === 'a' || str_starts_with($action, 'a:')) {
            $slot = null;
            if (str_starts_with($action, 'a:')) {
                $slot = (int) substr($action, 2);
            }
            $this->attack($game, $slot);
        }

        return $this->bumpedZombieIds;
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

        $blocker = $game->blockers->first(fn (GameBlocker $b) => $b->room === $game->current_room
            && $b->x === $targetX
            && $b->y === $targetY);

        if ($blocker) {
            return false;
        }

        $zombie = $game->zombies->first(fn (GameZombie $z) => $z->active
            && $z->room === $game->current_room
            && $z->x === $targetX
            && $z->y === $targetY);

        if ($zombie) {
            $this->applyZombieBump($game, $zombie);

            return false;
        }

        $door = $game->doors->first(fn (GameDoor $d) => $d->room === $game->current_room
            && $d->x === $targetX
            && $d->y === $targetY);

        if ($door && $door->state !== GameDoor::STATE_OPEN) {
            return false;
        }

        $game->update(['player_x' => $targetX, 'player_y' => $targetY]);

        if ($door && $door->is_exit) {
            if ($game->current_room >= 5) {
                $game->update(['status' => Game::STATUS_WON]);
            } else {
                app(RoomSeeder::class)->advanceTo($game, $game->current_room + 1);
            }

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

        if ($this->attackNearestZombie($game, $weapon)) {
            $this->applyAttackCost($game, $weapon);

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

        $doorDamage = $weapon === Game::WEAPON_DE_SWORD ? 2 : 1;

        foreach ($doorsHit as $door) {
            $this->damageDoor($door, $doorDamage);
        }

        $this->applyAttackCost($game, $weapon);
    }

    private function attackNearestZombie(Game $game, string $weapon): bool
    {
        $reach = $weapon === Game::WEAPON_DE_SWORD ? 2 : 1;

        $candidates = $game->zombies
            ->filter(fn (GameZombie $z) => $z->active
                && $z->room === $game->current_room
                && (abs($z->x - $game->player_x) + abs($z->y - $game->player_y)) <= $reach)
            ->sortBy(fn (GameZombie $z) => abs($z->x - $game->player_x) + abs($z->y - $game->player_y))
            ->values();

        $target = $candidates->first();
        if (! $target) {
            return false;
        }

        $damage = match ($weapon) {
            Game::WEAPON_DE_SWORD => 4,
            Game::WEAPON_REGULAR_SWORD => 3,
            default => 2,
        };

        $newHp = max(0, $target->hp - $damage);
        if ($newHp <= 0) {
            $target->update(['hp' => 0, 'active' => false]);
            $this->advancePlayerTowardKill($game, $target);
        } else {
            $target->update(['hp' => $newHp]);
        }

        return true;
    }

    /**
     * On a kill, the player moves 1 tile toward the zombie's former position
     * per the GDD. If adjacent, the player steps onto the zombie's now-empty
     * tile; otherwise they close the gap by one tile on the greater axis.
     * No-op if the intermediate tile is blocked.
     */
    private function advancePlayerTowardKill(Game $game, GameZombie $target): void
    {
        $dx = $target->x - $game->player_x;
        $dy = $target->y - $game->player_y;

        if ($dx === 0 && $dy === 0) {
            return;
        }

        if (abs($dx) >= abs($dy)) {
            $stepX = $dx === 0 ? 0 : ($dx > 0 ? 1 : -1);
            $stepY = 0;
        } else {
            $stepX = 0;
            $stepY = $dy === 0 ? 0 : ($dy > 0 ? 1 : -1);
        }

        $nx = $game->player_x + $stepX;
        $ny = $game->player_y + $stepY;

        if ($nx < RoomSeeder::GRID_MIN || $nx > RoomSeeder::GRID_MAX
            || $ny < RoomSeeder::GRID_MIN || $ny > RoomSeeder::GRID_MAX) {
            return;
        }

        $blockerOnPath = $game->blockers->first(fn (GameBlocker $b) => $b->room === $game->current_room
            && $b->x === $nx
            && $b->y === $ny);
        if ($blockerOnPath) {
            return;
        }

        $otherZombie = $game->zombies->first(fn (GameZombie $z) => $z->active
            && $z->id !== $target->id
            && $z->room === $game->current_room
            && $z->x === $nx
            && $z->y === $ny);
        if ($otherZombie) {
            return;
        }

        $game->update(['player_x' => $nx, 'player_y' => $ny]);
    }

    private function applyZombieBump(Game $game, GameZombie $zombie): void
    {
        $this->bumpedZombieIds[] = $zombie->id;

        $newHp = $game->player_hp - $zombie->damage;
        if ($newHp <= 0) {
            $game->update(['player_hp' => 0, 'status' => Game::STATUS_LOST]);

            return;
        }

        $game->update(['player_hp' => $newHp]);
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
