<?php

namespace App\Services\Gamejam;

use App\Models\Game;
use App\Models\GameZombie;
use Illuminate\Support\Collection;

class ZombieTurnResolver
{
    /**
     * Advance all zombies in the current room one turn: update LoS-derived
     * brain state, step them, then apply adjacency damage to the player.
     * Must be called inside the ResolveGameRound transaction, after
     * ActionApplier has resolved the player's vote.
     *
     * @param  array<int>  $bumpedZombieIds  ids of zombies that already hit the
     *                                       player via a movement bump this tick;
     *                                       they skip the adjacency attack so
     *                                       bumps don't double-hit.
     */
    public function resolve(Game $game, array $bumpedZombieIds = []): void
    {
        if ($game->status !== Game::STATUS_RUNNING) {
            return;
        }

        $zombies = $game->zombies()
            ->where('room', $game->current_room)
            ->where('active', true)
            ->get();

        if ($zombies->isEmpty()) {
            return;
        }

        foreach ($zombies as $z) {
            $z->prev_x = $z->x;
            $z->prev_y = $z->y;
        }

        $blockers = $game->blockers->where('room', $game->current_room)->values();
        $hidingSpots = $game->hidingSpots->where('room', $game->current_room)->values();

        foreach ($zombies as $z) {
            $hasLos = $this->hasLineOfSight(
                $z->x,
                $z->y,
                $game->player_x,
                $game->player_y,
                $blockers,
                $hidingSpots,
                $game->player_hiding_this_round,
            );

            if ($hasLos) {
                $z->brain_state = GameZombie::STATE_CHASING;
                $this->chaseStep($z, $game, $zombies, $blockers);
            } else {
                $z->brain_state = GameZombie::STATE_DRIFTING;
                $this->driftStep($z, $game, $zombies, $blockers);
            }

            $z->save();
        }

        $totalDamage = 0;
        $anyZombieSeesHidingPlayer = false;

        foreach ($zombies as $z) {
            if (in_array($z->id, $bumpedZombieIds, true)) {
                continue;
            }

            $dx = abs($z->x - $game->player_x);
            $dy = abs($z->y - $game->player_y);
            $adjacent = ($dx === 1 && $dy === 0) || ($dx === 0 && $dy === 1);

            if (! $adjacent) {
                // Non-adjacent zombies can't hit, but they can still spoil the
                // "safe hide" HP restore if they still have LoS to the player.
                if ($game->player_hiding_this_round && $this->hasLineOfSight(
                    $z->x,
                    $z->y,
                    $game->player_x,
                    $game->player_y,
                    $blockers,
                    $hidingSpots,
                    false,
                )) {
                    $anyZombieSeesHidingPlayer = true;
                }

                continue;
            }

            $damage = $z->damage;
            if ($game->player_hiding_this_round) {
                // Adjacent = LoS trivially open, per GDD damage doubles.
                $damage *= 2;
                $anyZombieSeesHidingPlayer = true;
            }
            $totalDamage += $damage;
        }

        $newHp = $game->player_hp - $totalDamage;

        if ($game->player_hiding_this_round && ! $anyZombieSeesHidingPlayer && $totalDamage === 0) {
            $newHp += 1;
        }

        if ($newHp <= 0) {
            $game->update(['player_hp' => 0, 'status' => Game::STATUS_LOST]);
        } else {
            $game->update(['player_hp' => $newHp]);
        }
    }

    /**
     * Pure-function LoS check from (x0,y0) to (x1,y1). Blockers are opaque.
     * Hiding spots are opaque only when the player is hiding on them (per the
     * handoff rule), modelled here for completeness even though the endpoint
     * exclusion means it rarely fires in the single-player scenario.
     */
    public function hasLineOfSight(
        int $x0,
        int $y0,
        int $x1,
        int $y1,
        Collection $blockers,
        Collection $hidingSpots,
        bool $playerHiding,
    ): bool {
        $points = $this->lineTiles($x0, $y0, $x1, $y1);
        if (count($points) <= 2) {
            return true;
        }

        $interior = array_slice($points, 1, count($points) - 2);
        foreach ($interior as [$px, $py]) {
            foreach ($blockers as $b) {
                if ($b->x === $px && $b->y === $py) {
                    return false;
                }
            }
            if ($playerHiding) {
                foreach ($hidingSpots as $s) {
                    if ($s->x === $px && $s->y === $py && $s->x === $x1 && $s->y === $y1) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function chaseStep(GameZombie $z, Game $game, Collection $zombies, Collection $blockers): void
    {
        $dx = $game->player_x - $z->x;
        $dy = $game->player_y - $z->y;

        $tries = [];
        if (abs($dx) >= abs($dy)) {
            if ($dx !== 0) {
                $tries[] = $dx > 0 ? GameZombie::FACING_RIGHT : GameZombie::FACING_LEFT;
            }
            if ($dy !== 0) {
                $tries[] = $dy > 0 ? GameZombie::FACING_DOWN : GameZombie::FACING_UP;
            }
        } else {
            if ($dy !== 0) {
                $tries[] = $dy > 0 ? GameZombie::FACING_DOWN : GameZombie::FACING_UP;
            }
            if ($dx !== 0) {
                $tries[] = $dx > 0 ? GameZombie::FACING_RIGHT : GameZombie::FACING_LEFT;
            }
        }

        foreach ($tries as $facing) {
            [$tx, $ty] = $this->step($z->x, $z->y, $facing);
            if ($this->walkable($tx, $ty, $z, $game, $zombies, $blockers)) {
                $z->x = $tx;
                $z->y = $ty;
                $z->facing = $facing;

                return;
            }
        }
    }

    private function driftStep(GameZombie $z, Game $game, Collection $zombies, Collection $blockers): void
    {
        $facing = $z->facing;
        for ($i = 0; $i < 4; $i++) {
            [$tx, $ty] = $this->step($z->x, $z->y, $facing);
            if ($this->walkable($tx, $ty, $z, $game, $zombies, $blockers)) {
                $z->x = $tx;
                $z->y = $ty;
                $z->facing = $facing;

                return;
            }
            $facing = $this->rotateRight($facing);
        }
    }

    private function step(int $x, int $y, string $facing): array
    {
        return match ($facing) {
            GameZombie::FACING_UP => [$x, $y - 1],
            GameZombie::FACING_DOWN => [$x, $y + 1],
            GameZombie::FACING_LEFT => [$x - 1, $y],
            GameZombie::FACING_RIGHT => [$x + 1, $y],
            default => [$x, $y],
        };
    }

    private function rotateRight(string $facing): string
    {
        return match ($facing) {
            GameZombie::FACING_UP => GameZombie::FACING_RIGHT,
            GameZombie::FACING_RIGHT => GameZombie::FACING_DOWN,
            GameZombie::FACING_DOWN => GameZombie::FACING_LEFT,
            GameZombie::FACING_LEFT => GameZombie::FACING_UP,
            default => GameZombie::FACING_RIGHT,
        };
    }

    private function walkable(int $x, int $y, GameZombie $self, Game $game, Collection $zombies, Collection $blockers): bool
    {
        if ($x < RoomSeeder::GRID_MIN || $x > RoomSeeder::GRID_MAX
            || $y < RoomSeeder::GRID_MIN || $y > RoomSeeder::GRID_MAX) {
            return false;
        }

        foreach ($blockers as $b) {
            if ($b->x === $x && $b->y === $y) {
                return false;
            }
        }

        if ($game->player_x === $x && $game->player_y === $y) {
            return false;
        }

        foreach ($zombies as $other) {
            if ($other->id === $self->id) {
                continue;
            }
            if ($other->x === $x && $other->y === $y) {
                return false;
            }
        }

        return true;
    }

    private function lineTiles(int $x0, int $y0, int $x1, int $y1): array
    {
        $points = [];
        $dx = abs($x1 - $x0);
        $dy = abs($y1 - $y0);
        $x = $x0;
        $y = $y0;
        $sx = $x0 < $x1 ? 1 : -1;
        $sy = $y0 < $y1 ? 1 : -1;
        $err = $dx - $dy;

        while (true) {
            $points[] = [$x, $y];
            if ($x === $x1 && $y === $y1) {
                break;
            }
            $e2 = 2 * $err;
            if ($e2 > -$dy) {
                $err -= $dy;
                $x += $sx;
            }
            if ($e2 < $dx) {
                $err += $dx;
                $y += $sy;
            }
        }

        return $points;
    }
}
