<?php

use App\Models\Game;
use App\Models\GameDoor;
use App\Models\GameHiddenTile;
use App\Models\GameHidingSpot;
use App\Models\GameZombie;
use App\Models\User;
use App\Services\Gamejam\ActionApplier;
use App\Services\Gamejam\GameLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function makeLoggedGame(): Game
{
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);

    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 1,
        'current_room' => 1,
        'player_hp' => 20,
        'player_x' => 5,
        'player_y' => 5,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    $game->refresh();

    return $game;
}

function logTypes(Game $game): array
{
    return collect($game->fresh()->log ?? [])->pluck('type')->all();
}

test('reveals are logged with content type and weapon pickup', function () {
    $game = makeLoggedGame();

    GameHiddenTile::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => $game->player_x + 1,
        'y' => $game->player_y,
        'content' => GameHiddenTile::CONTENT_DE_SWORD,
    ]);

    $game->load('hiddenTiles', 'doors', 'hidingSpots', 'blockers', 'zombies');

    (new ActionApplier)->apply($game, 'p:right');

    expect(logTypes($game))->toContain('hidden_reveal', 'weapon_pickup');
});

test('attacking a zombie logs player_attack and zombie_killed on kill', function () {
    $game = makeLoggedGame();
    $game->update(['weapon_slot_2' => Game::WEAPON_DE_SWORD]);

    GameZombie::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => $game->player_x + 1,
        'y' => $game->player_y,
        'prev_x' => $game->player_x + 1,
        'prev_y' => $game->player_y,
        'facing' => GameZombie::FACING_LEFT,
        'hp' => 3,
        'max_hp' => 3,
        'damage' => 1,
        'kind' => GameZombie::KIND_REGULAR,
        'brain_state' => GameZombie::STATE_DRIFTING,
        'active' => true,
    ]);

    $game->load('hiddenTiles', 'doors', 'hidingSpots', 'blockers', 'zombies');

    (new ActionApplier)->apply($game, 'a:2');

    $types = logTypes($game);
    expect($types)->toContain('player_attack', 'zombie_killed');
});

test('door takes damage even when a boss is alive elsewhere in the room', function () {
    $game = makeLoggedGame();
    $game->update(['weapon_slot_1' => Game::WEAPON_REGULAR_SWORD, 'weapon_slot_1_uses' => 10]);

    GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => $game->player_x + 1,
        'y' => $game->player_y,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 5,
        'is_exit' => true,
    ]);

    GameZombie::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => $game->player_x + 5,
        'y' => $game->player_y + 5,
        'prev_x' => $game->player_x + 5,
        'prev_y' => $game->player_y + 5,
        'facing' => GameZombie::FACING_LEFT,
        'hp' => 30,
        'max_hp' => 30,
        'damage' => 5,
        'kind' => GameZombie::KIND_BOSS,
        'brain_state' => GameZombie::STATE_DRIFTING,
        'active' => true,
    ]);

    $game->load('hiddenTiles', 'doors', 'hidingSpots', 'blockers', 'zombies');

    (new ActionApplier)->apply($game, 'a');

    expect(logTypes($game))->toContain('door_damage');
});

test('hide action emits a hide entry', function () {
    $game = makeLoggedGame();

    GameHidingSpot::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => $game->player_x + 2,
        'y' => $game->player_y,
    ]);

    $game->load('hiddenTiles', 'doors', 'hidingSpots', 'blockers', 'zombies');

    (new ActionApplier)->apply($game, 'h');

    expect(logTypes($game))->toContain('hide');
});

test('the live log is capped while recap keeps the full history', function () {
    $game = makeLoggedGame();

    GameHidingSpot::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => $game->player_x + 1,
        'y' => $game->player_y,
    ]);

    $game->load('hiddenTiles', 'doors', 'hidingSpots', 'blockers', 'zombies');
    $applier = new ActionApplier;

    $total = GameLog::LIVE_LOG_LIMIT + 20;
    for ($i = 0; $i < $total; $i++) {
        $applier->apply($game, 'h');
    }

    $game->refresh();
    // The live ticker stays bounded so the broadcast snapshot never exceeds
    // Reverb's per-message limit; recap keeps every entry for the post-mortem.
    expect(count($game->log))->toBe(GameLog::LIVE_LOG_LIMIT)
        ->and(count($game->recap))->toBe($total);
});

test('the live log keeps the newest entries and rolls the oldest off', function () {
    $game = makeLoggedGame();

    $total = GameLog::LIVE_LOG_LIMIT + 5;
    for ($i = 0; $i < $total; $i++) {
        GameLog::append($game, 'tick', ['seq' => $i]);
    }

    $game->refresh();

    $logSeqs = array_column(array_column($game->log, 'data'), 'seq');
    $recapSeqs = array_column(array_column($game->recap, 'data'), 'seq');

    // Oldest five (0-4) rolled off the live window; recap retains everything.
    expect($logSeqs)->toBe(range(5, $total - 1))
        ->and($recapSeqs)->toBe(range(0, $total - 1));
});
