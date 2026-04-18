<?php

use App\Events\GameStateChanged;
use App\Jobs\ResolveGameRound;
use App\Models\Game;
use App\Models\GameJoiner;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

function makeResolverUser(): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'streamer_a'],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function makeActiveJoiner(Game $game, string $vote, string $uid): GameJoiner
{
    return GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => $uid,
        'username' => "user_{$uid}",
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
        'current_vote' => $vote,
    ]);
}

test('picks the plurality winner from active joiners', function () {
    Event::fake([GameStateChanged::class]);
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 1,
        'player_hp' => 5,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    makeActiveJoiner($game, 'p:left', '1');
    makeActiveJoiner($game, 'p:left', '2');
    makeActiveJoiner($game, 'p:right', '3');
    makeActiveJoiner($game, 'h', '4');

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->current_round)->toBe(2)
        ->and($game->last_resolved_action)->toBe('p:left')
        ->and($game->last_resolved_tally)->toBe(['p:left' => 2, 'p:right' => 1, 'h' => 1])
        ->and($game->last_resolved_at)->not->toBeNull();
});

test('clears all current votes after resolving', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 1,
        'player_hp' => 1,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    makeActiveJoiner($game, 'p:left', '1');
    makeActiveJoiner($game, 'h', '2');

    (new ResolveGameRound($game->id, 1))->handle();

    expect(GameJoiner::whereNotNull('current_vote')->count())->toBe(0);
});

test('promotes pending joiners whose join round has passed', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 3,
        'player_hp' => 1,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    $pending = GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '99',
        'username' => 'Latecomer',
        'status' => GameJoiner::STATUS_PENDING,
        'joined_round' => 3,
    ]);

    (new ResolveGameRound($game->id, 3))->handle();

    expect($pending->fresh()->status)->toBe(GameJoiner::STATUS_ACTIVE);
});

test('stores null winner when nobody voted', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 1,
        'player_hp' => 1,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '1',
        'username' => 'Silent',
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
    ]);

    (new ResolveGameRound($game->id, 1))->handle();

    $game->refresh();
    expect($game->last_resolved_action)->toBeNull()
        ->and($game->last_resolved_tally)->toBe([])
        ->and($game->current_round)->toBe(2);
});

test('ignores votes from pending and inactive joiners', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 2,
        'player_hp' => 1,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '1',
        'username' => 'Pending',
        'status' => GameJoiner::STATUS_PENDING,
        'joined_round' => 2,
        'current_vote' => 'p:left',
    ]);
    GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '2',
        'username' => 'Inactive',
        'status' => GameJoiner::STATUS_INACTIVE,
        'joined_round' => 1,
        'current_vote' => 'h',
    ]);
    makeActiveJoiner($game, 'p:right', '3');

    (new ResolveGameRound($game->id, 2))->handle();

    $game->refresh();
    expect($game->last_resolved_action)->toBe('p:right')
        ->and($game->last_resolved_tally)->toBe(['p:right' => 1]);
});

test('no-ops when called with a stale round number', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 5,
        'player_hp' => 1,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    (new ResolveGameRound($game->id, 2))->handle();

    expect($game->fresh()->current_round)->toBe(5);
    Bus::assertNothingDispatched();
});

test('no-ops when the game has ended', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_WON,
        'current_round' => 3,
        'player_hp' => 1,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    (new ResolveGameRound($game->id, 3))->handle();

    expect($game->fresh()->current_round)->toBe(3);
    Bus::assertNothingDispatched();
});

test('schedules the next tick after resolving', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 1,
        'player_hp' => 1,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    (new ResolveGameRound($game->id, 1))->handle();

    Bus::assertDispatched(
        ResolveGameRound::class,
        fn ($job) => $job->gameId === $game->id && $job->expectedRound === 2
    );
});
