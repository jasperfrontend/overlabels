<?php

use App\Models\Game;
use App\Models\GameJoiner;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
});

function makeGamejamUser(string $login = 'streamer_a'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function gamejamPost(array $body, string $login = 'streamer_a'): TestResponse
{
    return test()->postJson(
        "/api/internal/bot/gamejam/action/{$login}",
        $body,
        ['X-Internal-Secret' => 'test-bot-secret'],
    );
}

function baseBody(array $overrides = []): array
{
    return array_merge([
        'twitch_user_id' => '42',
        'username' => 'Girly456',
        'action' => 'join',
    ], $overrides);
}

// ──────────────────────────────────────────────────────────────────────────────
// Auth & routing
// ──────────────────────────────────────────────────────────────────────────────

test('rejects requests without the internal secret', function () {
    $this->postJson('/api/internal/bot/gamejam/action/streamer_a', baseBody())
        ->assertStatus(403);
});

test('404 silently when channel has no active game', function () {
    makeGamejamUser();

    gamejamPost(baseBody())
        ->assertStatus(404)
        ->assertExactJson(['reply' => null]);
});

test('404 when the login does not belong to any opted-in user', function () {
    gamejamPost(baseBody(), 'nobody')
        ->assertStatus(404);
});

// ──────────────────────────────────────────────────────────────────────────────
// Validation
// ──────────────────────────────────────────────────────────────────────────────

test('rejects an unknown action', function () {
    makeGamejamUser();

    gamejamPost(baseBody(['action' => 'explode']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('vote_move requires a direction', function () {
    $user = makeGamejamUser();
    Game::create(['user_id' => $user->id, 'status' => Game::STATUS_RUNNING]);

    gamejamPost(baseBody(['action' => 'vote_move']))
        ->assertStatus(422);
});

test('rejects an unknown direction', function () {
    makeGamejamUser();

    gamejamPost(baseBody(['action' => 'vote_move', 'direction' => 'diagonal']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['direction']);
});

test('rejects an out-of-range weapon slot', function () {
    makeGamejamUser();

    gamejamPost(baseBody(['action' => 'vote_attack', 'weapon_slot' => 3]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['weapon_slot']);
});

// ──────────────────────────────────────────────────────────────────────────────
// !join
// ──────────────────────────────────────────────────────────────────────────────

test('join creates a pending joiner and bumps HP', function () {
    $user = makeGamejamUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 4,
        'player_hp' => 7,
    ]);

    $response = gamejamPost(baseBody(['action' => 'join', 'twitch_user_id' => '42', 'username' => 'Girly456']));

    $response->assertOk()
        ->assertJson(['accepted' => true])
        ->assertJsonPath('reply', 'joined the raid - HP pool now 8');

    $joiner = GameJoiner::where('game_id', $game->id)->firstOrFail();
    expect($joiner->status)->toBe(GameJoiner::STATUS_PENDING)
        ->and($joiner->joined_round)->toBe(4)
        ->and($joiner->blocks_remaining)->toBe(3)
        ->and($joiner->hp_contributed)->toBeTrue()
        ->and($game->fresh()->player_hp)->toBe(8);
});

test('joining twice is a silent noop', function () {
    $user = makeGamejamUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 1,
        'player_hp' => 5,
    ]);

    gamejamPost(baseBody(['action' => 'join']))->assertOk();
    $after_first = $game->fresh()->player_hp;

    gamejamPost(baseBody(['action' => 'join']))
        ->assertOk()
        ->assertJson(['accepted' => false]);

    expect($game->fresh()->player_hp)->toBe($after_first)
        ->and(GameJoiner::where('game_id', $game->id)->count())->toBe(1);
});

test('inactive joiner re-joining resets their roster entry', function () {
    $user = makeGamejamUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 10,
        'player_hp' => 3,
    ]);
    $joiner = GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '42',
        'username' => 'Girly456',
        'status' => GameJoiner::STATUS_INACTIVE,
        'joined_round' => 1,
        'blocks_remaining' => 0,
        'hp_contributed' => false,
    ]);

    gamejamPost(baseBody(['action' => 'join']))->assertOk()->assertJson(['accepted' => true]);

    $joiner->refresh();
    expect($joiner->status)->toBe(GameJoiner::STATUS_PENDING)
        ->and($joiner->joined_round)->toBe(10)
        ->and($joiner->blocks_remaining)->toBe(3)
        ->and($joiner->hp_contributed)->toBeTrue()
        ->and($game->fresh()->player_hp)->toBe(4);
});

// ──────────────────────────────────────────────────────────────────────────────
// Vote upsert
// ──────────────────────────────────────────────────────────────────────────────

test('active joiner can vote_move and the vote is stored as a string', function () {
    $user = makeGamejamUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 5,
        'player_hp' => 1,
    ]);
    $joiner = GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '42',
        'username' => 'Girly456',
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 2,
        'blocks_remaining' => 1,
    ]);

    gamejamPost(baseBody(['action' => 'vote_move', 'direction' => 'left']))
        ->assertOk()
        ->assertJson(['accepted' => true, 'reply' => null]);

    $joiner->refresh();
    expect($joiner->current_vote)->toBe('p:left')
        ->and($joiner->last_vote_round)->toBe(5)
        ->and($joiner->blocks_remaining)->toBe(3);
});

test('vote_move with steps=1 stores the vote without a step suffix', function () {
    $user = makeGamejamUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 2,
    ]);
    $joiner = GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '42',
        'username' => 'Girly456',
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
    ]);

    gamejamPost(baseBody(['action' => 'vote_move', 'direction' => 'up', 'steps' => 1]))
        ->assertOk();

    expect($joiner->fresh()->current_vote)->toBe('p:up');
});

test('vote_move with steps>=2 stores the vote with a step suffix', function () {
    $user = makeGamejamUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 2,
    ]);
    $joiner = GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '42',
        'username' => 'Girly456',
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
    ]);

    gamejamPost(baseBody(['action' => 'vote_move', 'direction' => 'right', 'steps' => 3]))
        ->assertOk();

    expect($joiner->fresh()->current_vote)->toBe('p:right:3');
});

test('vote_move rejects steps outside the 1-3 range with a friendly reply', function () {
    $user = makeGamejamUser();
    Game::create(['user_id' => $user->id, 'status' => Game::STATUS_RUNNING]);

    gamejamPost(baseBody(['action' => 'vote_move', 'direction' => 'up', 'steps' => 4]))
        ->assertStatus(422)
        ->assertExactJson(['reply' => 'steps must be between 1 and 3']);

    gamejamPost(baseBody(['action' => 'vote_move', 'direction' => 'up', 'steps' => 0]))
        ->assertStatus(422)
        ->assertExactJson(['reply' => 'steps must be between 1 and 3']);
});

test('vote_hide stores h with no args', function () {
    $user = makeGamejamUser();
    $game = Game::create(['user_id' => $user->id, 'status' => Game::STATUS_RUNNING, 'current_round' => 3]);
    GameJoiner::create([
        'game_id' => $game->id, 'twitch_user_id' => '42', 'username' => 'Girly456',
        'status' => GameJoiner::STATUS_ACTIVE, 'joined_round' => 1,
    ]);

    gamejamPost(baseBody(['action' => 'vote_hide']))->assertOk();

    expect(GameJoiner::first()->current_vote)->toBe('h');
});

test('vote_stay stores s so it participates in the tally', function () {
    $user = makeGamejamUser();
    $game = Game::create(['user_id' => $user->id, 'status' => Game::STATUS_RUNNING, 'current_round' => 3]);
    GameJoiner::create([
        'game_id' => $game->id, 'twitch_user_id' => '42', 'username' => 'Girly456',
        'status' => GameJoiner::STATUS_ACTIVE, 'joined_round' => 1,
    ]);

    gamejamPost(baseBody(['action' => 'vote_stay']))->assertOk();

    expect(GameJoiner::first()->current_vote)->toBe('s');
});

test('vote_attack without a slot stores a; with a slot stores a:n', function () {
    $user = makeGamejamUser();
    $game = Game::create(['user_id' => $user->id, 'status' => Game::STATUS_RUNNING, 'current_round' => 3]);
    GameJoiner::create([
        'game_id' => $game->id, 'twitch_user_id' => '42', 'username' => 'Girly456',
        'status' => GameJoiner::STATUS_ACTIVE, 'joined_round' => 1,
    ]);

    gamejamPost(baseBody(['action' => 'vote_attack']))->assertOk();
    expect(GameJoiner::first()->current_vote)->toBe('a');

    gamejamPost(baseBody(['action' => 'vote_attack', 'weapon_slot' => 2]))->assertOk();
    expect(GameJoiner::first()->current_vote)->toBe('a:2');
});

test('409 when a non-joiner tries to vote', function () {
    $user = makeGamejamUser();
    Game::create(['user_id' => $user->id, 'status' => Game::STATUS_RUNNING, 'current_round' => 2]);

    gamejamPost(baseBody(['action' => 'vote_hide']))
        ->assertStatus(409)
        ->assertJsonPath('reply', 'type !join first');
});

test('409 when a pending joiner tries to vote the round they joined', function () {
    $user = makeGamejamUser();
    $game = Game::create(['user_id' => $user->id, 'status' => Game::STATUS_RUNNING, 'current_round' => 4]);
    GameJoiner::create([
        'game_id' => $game->id, 'twitch_user_id' => '42', 'username' => 'Girly456',
        'status' => GameJoiner::STATUS_PENDING, 'joined_round' => 4,
    ]);

    gamejamPost(baseBody(['action' => 'vote_hide']))
        ->assertStatus(409)
        ->assertJsonPath('reply', 'you can vote from next round');
});

test('409 when an inactive joiner tries to vote', function () {
    $user = makeGamejamUser();
    $game = Game::create(['user_id' => $user->id, 'status' => Game::STATUS_RUNNING, 'current_round' => 9]);
    GameJoiner::create([
        'game_id' => $game->id, 'twitch_user_id' => '42', 'username' => 'Girly456',
        'status' => GameJoiner::STATUS_INACTIVE, 'joined_round' => 1, 'blocks_remaining' => 0,
    ]);

    gamejamPost(baseBody(['action' => 'vote_hide']))
        ->assertStatus(409)
        ->assertJsonPath('reply', 'you went inactive - type !join to rejoin');
});

test('pending joiner can vote once the round number advances', function () {
    $user = makeGamejamUser();
    $game = Game::create(['user_id' => $user->id, 'status' => Game::STATUS_RUNNING, 'current_round' => 5]);
    GameJoiner::create([
        'game_id' => $game->id, 'twitch_user_id' => '42', 'username' => 'Girly456',
        'status' => GameJoiner::STATUS_PENDING, 'joined_round' => 4,
    ]);

    gamejamPost(baseBody(['action' => 'vote_hide']))->assertOk();
    expect(GameJoiner::first()->current_vote)->toBe('h');
});
