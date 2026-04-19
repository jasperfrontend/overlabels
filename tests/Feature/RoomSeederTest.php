<?php

use App\Models\Game;
use App\Models\GameBlocker;
use App\Models\GameDoor;
use App\Models\GameHiddenTile;
use App\Models\GameHidingSpot;
use App\Models\GameZombie;
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

test('seedRoom1 spawns exactly one regular zombie with 3 HP and 1 damage at least 3 tiles from spawn', function () {
    $game = makeSeederGame();

    app(RoomSeeder::class)->advanceTo($game, 1);

    $zombies = GameZombie::where('game_id', $game->id)->where('room', 1)->get();
    expect($zombies)->toHaveCount(1);

    $z = $zombies->first();
    expect($z->hp)->toBe(3)
        ->and($z->max_hp)->toBe(3)
        ->and($z->damage)->toBe(1)
        ->and($z->kind)->toBe(GameZombie::KIND_REGULAR)
        ->and($z->brain_state)->toBe(GameZombie::STATE_DRIFTING)
        ->and($z->facing)->toBe(GameZombie::FACING_RIGHT)
        ->and($z->active)->toBeTrue()
        ->and($z->prev_x)->toBe($z->x)
        ->and($z->prev_y)->toBe($z->y)
        ->and(abs($z->x - 5) + abs($z->y - 9))->toBeGreaterThanOrEqual(3);
});

test('seedRoom2 spawns one regular zombie with 4 HP and 2 damage', function () {
    $game = makeSeederGame();

    app(RoomSeeder::class)->advanceTo($game, 2);

    $zombies = GameZombie::where('game_id', $game->id)->where('room', 2)->get();
    expect($zombies)->toHaveCount(1);
    expect($zombies->first()->hp)->toBe(4)
        ->and($zombies->first()->damage)->toBe(2);
});

test('seedRoom3 spawns one regular zombie with 6 HP and 3 damage', function () {
    $game = makeSeederGame();

    app(RoomSeeder::class)->advanceTo($game, 3);

    $zombies = GameZombie::where('game_id', $game->id)->where('room', 3)->get();
    expect($zombies)->toHaveCount(1);
    expect($zombies->first()->hp)->toBe(6)
        ->and($zombies->first()->damage)->toBe(3);
});

test('seedRoom4 spawns four regular zombies with 8 HP and 4 damage', function () {
    $game = makeSeederGame();

    app(RoomSeeder::class)->advanceTo($game, 4);

    $zombies = GameZombie::where('game_id', $game->id)->where('room', 4)->get();
    expect($zombies)->toHaveCount(4);
    foreach ($zombies as $z) {
        expect($z->hp)->toBe(8)
            ->and($z->damage)->toBe(4)
            ->and($z->kind)->toBe(GameZombie::KIND_REGULAR);
    }
});

test('seedRoom5 spawns a single boss with 30 HP and 4 damage at the centre and 4 corner HP restores', function () {
    $game = makeSeederGame();

    app(RoomSeeder::class)->advanceTo($game, 5);

    $zombies = GameZombie::where('game_id', $game->id)->where('room', 5)->get();
    expect($zombies)->toHaveCount(1);

    $boss = $zombies->first();
    expect($boss->kind)->toBe(GameZombie::KIND_BOSS)
        ->and($boss->hp)->toBe(30)
        ->and($boss->damage)->toBe(4)
        ->and($boss->x)->toBe(5)
        ->and($boss->y)->toBe(5);

    $corners = GameHiddenTile::where('game_id', $game->id)
        ->where('room', 5)
        ->where('content', GameHiddenTile::CONTENT_HP_RESTORE)
        ->get()
        ->map(fn ($t) => [$t->x, $t->y])
        ->sort()
        ->values()
        ->all();

    expect($corners)->toContain([1, 1])
        ->and($corners)->toContain([9, 1])
        ->and($corners)->toContain([1, 9])
        ->and($corners)->toContain([9, 9]);
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
