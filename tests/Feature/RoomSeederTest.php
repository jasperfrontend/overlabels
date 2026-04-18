<?php

use App\Models\Game;
use App\Models\GameBlocker;
use App\Models\GameDoor;
use App\Models\GameHidingSpot;
use App\Models\User;
use App\Services\Gamejam\RoomSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function makeSeederGame(): Game
{
    $user = User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'seeder_user'],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);

    return Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 1,
        'current_room' => 1,
        'player_hp' => 5,
        'player_x' => 1,
        'player_y' => 1,
        'weapon_slot_1' => Game::WEAPON_FISTS,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);
}

test('advanceTo(2) places the player at spawn and seeds a flagged exit door', function () {
    $game = makeSeederGame();
    $game->update(['player_hiding_this_round' => true]);

    app(RoomSeeder::class)->advanceTo($game, 2);

    $game->refresh();
    expect($game->current_room)->toBe(2)
        ->and($game->player_x)->toBe(5)
        ->and($game->player_y)->toBe(9)
        ->and($game->player_hiding_this_round)->toBeFalse();

    $exit = GameDoor::where('game_id', $game->id)->where('room', 2)->first();
    expect($exit)->not->toBeNull()
        ->and($exit->is_exit)->toBeTrue()
        ->and($exit->state)->toBe(GameDoor::STATE_CLOSED)
        ->and($exit->turns_remaining)->toBe(3);
});

test('advanceTo(2) seeds two hiding spots', function () {
    $game = makeSeederGame();

    app(RoomSeeder::class)->advanceTo($game, 2);

    $spots = GameHidingSpot::where('game_id', $game->id)->where('room', 2)->count();
    expect($spots)->toBe(2);
});

test('advanceTo(5) seeds four blocker pillars and no hiding spots', function () {
    $game = makeSeederGame();

    app(RoomSeeder::class)->advanceTo($game, 5);

    $blockers = GameBlocker::where('game_id', $game->id)->where('room', 5)->count();
    expect($blockers)->toBe(4);

    $spots = GameHidingSpot::where('game_id', $game->id)->where('room', 5)->count();
    expect($spots)->toBe(0);
});

test('advanceTo preserves weapons and HP across rooms', function () {
    $game = makeSeederGame();
    $game->update([
        'player_hp' => 3,
        'weapon_slot_1' => Game::WEAPON_REGULAR_SWORD,
        'weapon_slot_1_uses' => 4,
        'weapon_slot_2' => Game::WEAPON_DE_SWORD,
        'wears_iron_fists' => true,
    ]);

    app(RoomSeeder::class)->advanceTo($game, 3);

    $game->refresh();
    expect($game->player_hp)->toBe(3)
        ->and($game->weapon_slot_1)->toBe(Game::WEAPON_REGULAR_SWORD)
        ->and($game->weapon_slot_1_uses)->toBe(4)
        ->and($game->weapon_slot_2)->toBe(Game::WEAPON_DE_SWORD)
        ->and($game->wears_iron_fists)->toBeTrue();
});

test('seedRoom1 resets weapons back to bare fists', function () {
    $game = makeSeederGame();
    $game->update([
        'weapon_slot_1' => Game::WEAPON_REGULAR_SWORD,
        'weapon_slot_1_uses' => 4,
        'weapon_slot_2' => Game::WEAPON_DE_SWORD,
        'wears_iron_fists' => true,
    ]);

    app(RoomSeeder::class)->advanceTo($game, 1);

    $game->refresh();
    expect($game->weapon_slot_1)->toBe(Game::WEAPON_FISTS)
        ->and($game->weapon_slot_1_uses)->toBeNull()
        ->and($game->weapon_slot_2)->toBeNull()
        ->and($game->wears_iron_fists)->toBeFalse();
});
