<?php

use App\Jobs\ResolveGameRound;
use App\Models\Game;
use App\Models\GameDoor;
use App\Models\GameHiddenTile;
use App\Models\GameHidingSpot;
use App\Models\GameJoiner;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;

uses(DatabaseTransactions::class);

function makeWorldUser(): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'streamer_b'],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function makeWorldGame(array $overrides = []): Game
{
    return Game::create(array_merge([
        'user_id' => makeWorldUser()->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 1,
        'current_room' => 1,
        'player_hp' => 5,
        'player_x' => 5,
        'player_y' => 9,
        'weapon_slot_1' => Game::WEAPON_FISTS,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ], $overrides));
}

function voter(Game $game, string $vote, string $uid = '1'): GameJoiner
{
    return GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => $uid,
        'username' => "voter_{$uid}",
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
        'current_vote' => $vote,
        'last_vote_round' => $game->current_round,
        'blocks_remaining' => 3,
    ]);
}

test('winning p:up moves the player one tile north', function () {
    Bus::fake();
    $game = makeWorldGame();
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(5)->and($game->player_y)->toBe(8);
});

test('bumping into the grid edge does not move the player', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9]);
    voter($game, 'p:down');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(5)->and($game->player_y)->toBe(9);
});

test('walking onto the regular sword tile equips weapon slot 1', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 6]);
    GameHiddenTile::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 5,
        'content' => GameHiddenTile::CONTENT_REGULAR_SWORD,
        'payload' => ['uses' => 10],
    ]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->weapon_slot_1)->toBe(Game::WEAPON_REGULAR_SWORD)
        ->and($game->weapon_slot_1_uses)->toBe(10)
        ->and($game->hiddenTiles()->first()->revealed_at_round)->toBe(1);
});

test('bumping a closed door stops movement without progressing it', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 2]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    $door->refresh();
    expect($game->player_y)->toBe(2)
        ->and($door->state)->toBe(GameDoor::STATE_CLOSED)
        ->and($door->turns_remaining)->toBe(2);
});

test('bumping an opening door does not advance it', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 2]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_OPENING,
        'turns_remaining' => 1,
    ]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    $door->refresh();
    expect($game->player_y)->toBe(2)
        ->and($door->state)->toBe(GameDoor::STATE_OPENING)
        ->and($door->turns_remaining)->toBe(1);
});

test('walking onto an open exit door wins the game and stops the tick loop', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 2]);
    GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_OPEN,
        'turns_remaining' => null,
    ]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->status)->toBe(Game::STATUS_WON)
        ->and($game->player_y)->toBe(1);

    Bus::assertNotDispatched(ResolveGameRound::class);
});

test('walking onto an hp_restore tile heals the player by payload amount', function () {
    Bus::fake();
    $game = makeWorldGame(['player_hp' => 3, 'player_x' => 5, 'player_y' => 6]);
    GameHiddenTile::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 5,
        'content' => GameHiddenTile::CONTENT_HP_RESTORE,
        'payload' => ['amount' => 4],
    ]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    expect($game->fresh()->player_hp)->toBe(7);
});

test('walking onto a bomb damages the player and can lose the game', function () {
    Bus::fake();
    $game = makeWorldGame(['player_hp' => 1, 'player_x' => 5, 'player_y' => 6]);
    GameHiddenTile::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 5,
        'content' => GameHiddenTile::CONTENT_BOMB,
    ]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->status)->toBe(Game::STATUS_LOST)
        ->and($game->player_hp)->toBe(0);

    Bus::assertNotDispatched(ResolveGameRound::class);
});

test('multi-step winning p:up:3 moves the player three tiles', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9]);
    voter($game, 'p:up:3');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(5)->and($game->player_y)->toBe(6);
});

test('multi-step move stops at the grid edge without wrapping', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 3]);
    voter($game, 'p:up:8');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_y)->toBe(1);
});

test('multi-step move stops at a closed door without progressing it', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 4]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'p:up:5');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    $door->refresh();
    expect($game->player_y)->toBe(2)
        ->and($door->state)->toBe(GameDoor::STATE_CLOSED)
        ->and($door->turns_remaining)->toBe(2);
});

test('multi-step move wins immediately on reaching an open exit door', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 5]);
    GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_OPEN,
        'turns_remaining' => null,
    ]);
    voter($game, 'p:up:8');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->status)->toBe(Game::STATUS_WON)
        ->and($game->player_y)->toBe(1);
});

test('multi-step move stops when a mid-path bomb kills the player', function () {
    Bus::fake();
    $game = makeWorldGame(['player_hp' => 1, 'player_x' => 5, 'player_y' => 9]);
    GameHiddenTile::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 7,
        'content' => GameHiddenTile::CONTENT_BOMB,
    ]);
    voter($game, 'p:up:5');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->status)->toBe(Game::STATUS_LOST)
        ->and($game->player_y)->toBe(7);
});

test('hide is cleared on the following round', function () {
    Bus::fake();
    $game = makeWorldGame(['player_hiding_this_round' => true]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    expect($game->fresh()->player_hiding_this_round)->toBeFalse();
});

test('fist attack on closed door progresses it and takes 1 HP from the player', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 2,
        'player_hp' => 5,
        'weapon_slot_1' => Game::WEAPON_FISTS,
    ]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'a');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    $door->refresh();
    expect($door->state)->toBe(GameDoor::STATE_OPENING)
        ->and($door->turns_remaining)->toBe(1)
        ->and($game->player_hp)->toBe(4);
});

test('fist attack with iron fists progresses the door without self-damage', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 2,
        'player_hp' => 5,
        'weapon_slot_1' => Game::WEAPON_FISTS,
        'wears_iron_fists' => true,
    ]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'a');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    $door->refresh();
    expect($door->state)->toBe(GameDoor::STATE_OPENING)
        ->and($door->turns_remaining)->toBe(1)
        ->and($game->player_hp)->toBe(5);
});

test('fist attack on door can kill the player and end the game', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 2,
        'player_hp' => 1,
        'weapon_slot_1' => Game::WEAPON_FISTS,
    ]);
    GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'a');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->status)->toBe(Game::STATUS_LOST)
        ->and($game->player_hp)->toBe(0);

    Bus::assertNotDispatched(ResolveGameRound::class);
});

test('regular sword attack on door progresses it and consumes 1 use', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 2,
        'weapon_slot_1' => Game::WEAPON_REGULAR_SWORD,
        'weapon_slot_1_uses' => 10,
    ]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'a:1');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    $door->refresh();
    expect($door->state)->toBe(GameDoor::STATE_OPENING)
        ->and($door->turns_remaining)->toBe(1)
        ->and($game->weapon_slot_1)->toBe(Game::WEAPON_REGULAR_SWORD)
        ->and($game->weapon_slot_1_uses)->toBe(9);
});

test('regular sword attack on door breaks the sword when uses hit 0', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 2,
        'weapon_slot_1' => Game::WEAPON_REGULAR_SWORD,
        'weapon_slot_1_uses' => 1,
    ]);
    GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'a:1');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->weapon_slot_1)->toBe(Game::WEAPON_FISTS)
        ->and($game->weapon_slot_1_uses)->toBeNull();
});

test('de-sword attack opens a closed door instantly', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 2,
        'weapon_slot_1' => Game::WEAPON_FISTS,
        'weapon_slot_2' => Game::WEAPON_DE_SWORD,
    ]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'a:2');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    $door->refresh();
    expect($door->state)->toBe(GameDoor::STATE_OPEN)
        ->and($door->turns_remaining)->toBeNull()
        ->and($game->player_hp)->toBe(5);
});

test('attack on slot 2 with empty slot is a no-op', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 2,
        'weapon_slot_1' => Game::WEAPON_FISTS,
        'weapon_slot_2' => null,
    ]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'a:2');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    $door->refresh();
    expect($door->state)->toBe(GameDoor::STATE_CLOSED)
        ->and($door->turns_remaining)->toBe(2)
        ->and($game->player_hp)->toBe(5);
});

test('attack reaches a diagonally adjacent door via AoE', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 4,
        'player_y' => 2,
        'weapon_slot_1' => Game::WEAPON_FISTS,
        'wears_iron_fists' => true,
    ]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'a');

    (new ResolveGameRound($game->id, 1))->handle();

    $door->refresh();
    expect($door->state)->toBe(GameDoor::STATE_OPENING)
        ->and($door->turns_remaining)->toBe(1);
});

test('attack does not reach a door two tiles away', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 3,
        'weapon_slot_1' => Game::WEAPON_FISTS,
    ]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    voter($game, 'a');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    $door->refresh();
    expect($door->state)->toBe(GameDoor::STATE_CLOSED)
        ->and($door->turns_remaining)->toBe(2)
        ->and($game->player_hp)->toBe(5);
});

test('player can win by opening a closed door with attack then walking through next round', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 2,
        'weapon_slot_1' => Game::WEAPON_FISTS,
        'weapon_slot_2' => Game::WEAPON_DE_SWORD,
    ]);
    GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
    ]);
    $joiner = voter($game, 'a:2');

    (new ResolveGameRound($game->id, 1))->handle();

    $joiner->update(['current_vote' => 'p:up', 'last_vote_round' => 2]);

    (new ResolveGameRound($game->id, 2))->handle();

    $game->refresh();
    expect($game->status)->toBe(Game::STATUS_WON)
        ->and($game->player_y)->toBe(1);
});
