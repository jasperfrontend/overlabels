<?php

use App\Jobs\ResolveGameRound;
use App\Models\Game;
use App\Models\GameBlocker;
use App\Models\GameDoor;
use App\Models\GameHiddenTile;
use App\Models\GameHidingSpot;
use App\Models\GameJoiner;
use App\Models\GameZombie;
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

function makeZombie(Game $game, array $overrides = []): GameZombie
{
    return GameZombie::create(array_merge([
        'game_id' => $game->id,
        'room' => $game->current_room,
        'x' => 5,
        'y' => 5,
        'prev_x' => 5,
        'prev_y' => 5,
        'facing' => GameZombie::FACING_RIGHT,
        'hp' => 4,
        'max_hp' => 4,
        'damage' => 1,
        'kind' => GameZombie::KIND_REGULAR,
        'brain_state' => GameZombie::STATE_DRIFTING,
        'active' => true,
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

test('walking through an open exit door in rooms 1-4 advances to the next room and reseeds', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 2]);
    GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_OPEN,
        'turns_remaining' => null,
        'is_exit' => true,
    ]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->status)->toBe(Game::STATUS_RUNNING)
        ->and($game->current_room)->toBe(2)
        ->and($game->player_x)->toBe(5)
        ->and($game->player_y)->toBe(9);

    // Room 2 should have its own freshly-seeded exit door.
    $room2Exit = GameDoor::where('game_id', $game->id)
        ->where('room', 2)
        ->where('is_exit', true)
        ->first();
    expect($room2Exit)->not->toBeNull();
});

test('walking through an open exit door in room 5 wins the game and stops the tick loop', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 2, 'current_room' => 5]);
    GameDoor::create([
        'game_id' => $game->id,
        'room' => 5,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_OPEN,
        'turns_remaining' => null,
        'is_exit' => true,
    ]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->status)->toBe(Game::STATUS_WON)
        ->and($game->player_y)->toBe(1);

    Bus::assertNotDispatched(ResolveGameRound::class);
});

test('a blocker tile blocks player movement like a wall', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 5]);
    \App\Models\GameBlocker::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 4,
    ]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(5)->and($game->player_y)->toBe(5);
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

test('multi-step move wins immediately on reaching the room 5 exit door', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 5, 'current_room' => 5]);
    GameDoor::create([
        'game_id' => $game->id,
        'room' => 5,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_OPEN,
        'turns_remaining' => null,
        'is_exit' => true,
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

test('player standing on a hiding spot stays hidden on the next tick without re-voting hide', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 3, 'player_y' => 5, 'player_hiding_this_round' => true]);
    GameHidingSpot::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 3,
        'y' => 5,
    ]);
    voter($game, 's');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(3)
        ->and($game->player_y)->toBe(5)
        ->and($game->player_hiding_this_round)->toBeTrue();
});

test('player standing on a hiding spot stays hidden on the next tick even with no vote', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 3, 'player_y' => 5, 'player_hiding_this_round' => true]);
    GameHidingSpot::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 3,
        'y' => 5,
    ]);

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(3)
        ->and($game->player_y)->toBe(5)
        ->and($game->player_hiding_this_round)->toBeTrue();
});

test('hide teleports the player to the nearest hiding spot and sets the hiding flag', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9]);
    GameHidingSpot::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 3,
        'y' => 5,
    ]);
    voter($game, 'h');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(3)
        ->and($game->player_y)->toBe(5)
        ->and($game->player_hiding_this_round)->toBeTrue();
});

test('hide picks the nearest spot by manhattan distance when multiple exist', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 7, 'player_y' => 7]);
    GameHidingSpot::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 3,
        'y' => 5,
    ]);
    GameHidingSpot::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 8,
        'y' => 8,
    ]);
    voter($game, 'h');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(8)->and($game->player_y)->toBe(8);
});

test('hide with no hiding spots in the current room is a no-op', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9]);
    voter($game, 'h');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(5)
        ->and($game->player_y)->toBe(9)
        ->and($game->player_hiding_this_round)->toBeFalse();
});

test('stay resolves without moving, hiding, or attacking', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 7, 'player_hp' => 4]);
    voter($game, 's');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(5)
        ->and($game->player_y)->toBe(7)
        ->and($game->player_hp)->toBe(4)
        ->and($game->player_hiding_this_round)->toBeFalse();
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
        'current_room' => 5,
        'weapon_slot_1' => Game::WEAPON_FISTS,
        'weapon_slot_2' => Game::WEAPON_DE_SWORD,
    ]);
    GameDoor::create([
        'game_id' => $game->id,
        'room' => 5,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
        'is_exit' => true,
    ]);
    $joiner = voter($game, 'a:2');

    (new ResolveGameRound($game->id, 1))->handle();

    $joiner->update(['current_vote' => 'p:up', 'last_vote_round' => 2]);

    (new ResolveGameRound($game->id, 2))->handle();

    $game->refresh();
    expect($game->status)->toBe(Game::STATUS_WON)
        ->and($game->player_y)->toBe(1);
});

test('bumping into a zombie does not move the player and deals zombie damage', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9, 'player_hp' => 5]);
    $zombie = makeZombie($game, ['x' => 5, 'y' => 8, 'damage' => 2, 'prev_x' => 5, 'prev_y' => 8]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    $zombie->refresh();
    expect($game->player_x)->toBe(5)
        ->and($game->player_y)->toBe(9)
        ->and($game->player_hp)->toBe(3)
        ->and($zombie->active)->toBeTrue();
});

test('bumping into a zombie with lethal damage ends the game', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9, 'player_hp' => 2]);
    makeZombie($game, ['x' => 5, 'y' => 8, 'damage' => 3, 'prev_x' => 5, 'prev_y' => 8]);
    voter($game, 'p:up');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->status)->toBe(Game::STATUS_LOST)
        ->and($game->player_hp)->toBe(0);
});

test('fist attack on adjacent zombie deals 2 damage and costs 1 HP', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9, 'player_hp' => 5, 'weapon_slot_1' => Game::WEAPON_FISTS]);
    $zombie = makeZombie($game, ['x' => 5, 'y' => 8, 'hp' => 6, 'max_hp' => 6, 'damage' => 0, 'prev_x' => 5, 'prev_y' => 8]);
    voter($game, 'a');

    (new ResolveGameRound($game->id, 1))->handle();

    $zombie->refresh();
    $game->refresh();
    expect($zombie->hp)->toBe(4)
        ->and($zombie->active)->toBeTrue()
        ->and($game->player_hp)->toBe(4);
});

test('regular sword attack deals 3 damage and consumes one use', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 9,
        'player_hp' => 5,
        'weapon_slot_1' => Game::WEAPON_REGULAR_SWORD,
        'weapon_slot_1_uses' => 4,
    ]);
    $zombie = makeZombie($game, ['x' => 5, 'y' => 8, 'hp' => 8, 'max_hp' => 8, 'damage' => 0, 'prev_x' => 5, 'prev_y' => 8]);
    voter($game, 'a');

    (new ResolveGameRound($game->id, 1))->handle();

    $zombie->refresh();
    $game->refresh();
    expect($zombie->hp)->toBe(5)
        ->and($zombie->active)->toBeTrue()
        ->and($game->weapon_slot_1)->toBe(Game::WEAPON_REGULAR_SWORD)
        ->and($game->weapon_slot_1_uses)->toBe(3)
        ->and($game->player_hp)->toBe(5);
});

test('de-sword reaches a zombie two tiles away and deals 4 damage', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 9,
        'player_hp' => 5,
        'weapon_slot_1' => Game::WEAPON_FISTS,
        'weapon_slot_2' => Game::WEAPON_DE_SWORD,
    ]);
    $zombie = makeZombie($game, ['x' => 5, 'y' => 7, 'hp' => 8, 'max_hp' => 8, 'damage' => 0, 'prev_x' => 5, 'prev_y' => 7]);
    voter($game, 'a:2');

    (new ResolveGameRound($game->id, 1))->handle();

    $zombie->refresh();
    $game->refresh();
    expect($zombie->hp)->toBe(4)
        ->and($zombie->active)->toBeTrue()
        ->and($game->weapon_slot_2)->toBe(Game::WEAPON_DE_SWORD)
        ->and($game->player_hp)->toBe(5);
});

test('killing a zombie with an adjacent attack advances the player onto its tile', function () {
    Bus::fake();
    $game = makeWorldGame([
        'player_x' => 5,
        'player_y' => 9,
        'player_hp' => 5,
        'weapon_slot_1' => Game::WEAPON_REGULAR_SWORD,
        'weapon_slot_1_uses' => 2,
    ]);
    $zombie = makeZombie($game, ['x' => 5, 'y' => 8, 'hp' => 2, 'max_hp' => 6, 'damage' => 0, 'prev_x' => 5, 'prev_y' => 8]);
    voter($game, 'a');

    (new ResolveGameRound($game->id, 1))->handle();

    $zombie->refresh();
    $game->refresh();
    expect($zombie->hp)->toBe(0)
        ->and($zombie->active)->toBeFalse()
        ->and($game->player_x)->toBe(5)
        ->and($game->player_y)->toBe(8);
});

test('fist attack falls back to the door AoE when no zombie is in reach', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 2, 'player_hp' => 5]);
    $door = GameDoor::create([
        'game_id' => $game->id,
        'room' => 1,
        'x' => 5,
        'y' => 1,
        'state' => GameDoor::STATE_CLOSED,
        'turns_remaining' => 2,
        'is_exit' => true,
    ]);
    voter($game, 'a');

    (new ResolveGameRound($game->id, 1))->handle();

    $door->refresh();
    $game->refresh();
    expect($door->turns_remaining)->toBe(1)
        ->and($door->state)->toBe(GameDoor::STATE_OPENING)
        ->and($game->player_hp)->toBe(4);
});

test('an adjacent zombie attacks the player in the zombie turn phase', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9, 'player_hp' => 5]);
    makeZombie($game, ['x' => 5, 'y' => 8, 'damage' => 1, 'prev_x' => 5, 'prev_y' => 8]);
    voter($game, 'p:left');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(4)
        ->and($game->player_hp)->toBe(4);
});

test('zombie drifts instead of chasing a hiding player in clear line of sight', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9, 'player_hp' => 6]);
    GameHidingSpot::create(['game_id' => $game->id, 'room' => 1, 'x' => 5, 'y' => 8]);
    $zombie = makeZombie($game, [
        'x' => 5,
        'y' => 6,
        'damage' => 2,
        'facing' => GameZombie::FACING_RIGHT,
        'prev_x' => 5,
        'prev_y' => 6,
    ]);
    voter($game, 'h');

    (new ResolveGameRound($game->id, 1))->handle();

    $zombie->refresh();
    $game->refresh();
    expect($zombie->brain_state)->toBe(GameZombie::STATE_DRIFTING)
        ->and($zombie->x)->toBe(6)
        ->and($zombie->y)->toBe(6)
        ->and($game->player_hp)->toBe(6);
});

test('adjacent zombie doubles damage against a hiding player', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9, 'player_hp' => 6]);
    GameHidingSpot::create(['game_id' => $game->id, 'room' => 1, 'x' => 5, 'y' => 8]);
    GameBlocker::create(['game_id' => $game->id, 'room' => 1, 'x' => 3, 'y' => 8]);
    GameBlocker::create(['game_id' => $game->id, 'room' => 1, 'x' => 4, 'y' => 7]);
    GameBlocker::create(['game_id' => $game->id, 'room' => 1, 'x' => 4, 'y' => 9]);
    makeZombie($game, ['x' => 4, 'y' => 8, 'damage' => 2, 'prev_x' => 4, 'prev_y' => 8]);
    voter($game, 'h');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_x)->toBe(5)
        ->and($game->player_y)->toBe(8)
        ->and($game->player_hp)->toBe(2);
});

test('hide fully occluded by a blocker restores 1 HP', function () {
    Bus::fake();
    $game = makeWorldGame(['player_x' => 5, 'player_y' => 9, 'player_hp' => 3]);
    GameHidingSpot::create(['game_id' => $game->id, 'room' => 1, 'x' => 5, 'y' => 8]);
    GameBlocker::create(['game_id' => $game->id, 'room' => 1, 'x' => 5, 'y' => 5]);
    makeZombie($game, ['x' => 5, 'y' => 1, 'damage' => 3, 'prev_x' => 5, 'prev_y' => 1]);
    voter($game, 'h');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->player_y)->toBe(8)
        ->and($game->player_hp)->toBe(4);
});
