<?php

use App\Events\GameStateChanged;
use App\Jobs\ResolveGameRound;
use App\Models\BotChatOutbox;
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

test('decrements blocks_remaining for active joiners who did not vote', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 4,
        'player_hp' => 5,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    $voter = GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '1',
        'username' => 'Voter',
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
        'current_vote' => 'p:left',
        'last_vote_round' => 4,
        'blocks_remaining' => 3,
    ]);

    $slacker = GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '2',
        'username' => 'Slacker',
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
        'current_vote' => null,
        'last_vote_round' => 2,
        'blocks_remaining' => 3,
    ]);

    (new ResolveGameRound($game->id, 4))->handle();

    expect($voter->fresh()->blocks_remaining)->toBe(3)
        ->and($slacker->fresh()->blocks_remaining)->toBe(2)
        ->and($slacker->fresh()->status)->toBe(GameJoiner::STATUS_ACTIVE);
});

test('decrements blocks for active joiners who have never voted', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 2,
        'player_hp' => 5,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    $lurker = GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '1',
        'username' => 'Lurker',
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
        'current_vote' => null,
        'last_vote_round' => null,
        'blocks_remaining' => 3,
    ]);

    (new ResolveGameRound($game->id, 2))->handle();

    expect($lurker->fresh()->blocks_remaining)->toBe(2);
});

test('flips joiner to inactive and refunds HP when blocks hit zero', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 3,
        'player_hp' => 5,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    $quitter = GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '1',
        'username' => 'Quitter',
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
        'current_vote' => null,
        'last_vote_round' => 1,
        'blocks_remaining' => 1,
        'hp_contributed' => true,
    ]);

    (new ResolveGameRound($game->id, 3))->handle();

    $quitter->refresh();
    expect($quitter->status)->toBe(GameJoiner::STATUS_INACTIVE)
        ->and($quitter->blocks_remaining)->toBe(0)
        ->and($quitter->hp_contributed)->toBeFalse()
        ->and($game->fresh()->player_hp)->toBe(4);
});

test('floors player HP at 1 when mass leave would otherwise kill', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 3,
        'player_hp' => 2,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    foreach (['1', '2', '3'] as $uid) {
        GameJoiner::create([
            'game_id' => $game->id,
            'twitch_user_id' => $uid,
            'username' => "user_{$uid}",
            'status' => GameJoiner::STATUS_ACTIVE,
            'joined_round' => 1,
            'current_vote' => null,
            'last_vote_round' => 1,
            'blocks_remaining' => 1,
            'hp_contributed' => true,
        ]);
    }

    (new ResolveGameRound($game->id, 3))->handle();

    expect($game->fresh()->player_hp)->toBe(1);
});

test('does not decrement blocks for pending joiners being promoted', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 2,
        'player_hp' => 5,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    $newbie = GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '1',
        'username' => 'Newbie',
        'status' => GameJoiner::STATUS_PENDING,
        'joined_round' => 2,
        'blocks_remaining' => 3,
    ]);

    (new ResolveGameRound($game->id, 2))->handle();

    $newbie->refresh();
    expect($newbie->status)->toBe(GameJoiner::STATUS_ACTIVE)
        ->and($newbie->blocks_remaining)->toBe(3);
});

test('enqueues bot mention for joiners flipped to inactive', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 3,
        'player_hp' => 5,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    foreach (['alice', 'bob'] as $i => $username) {
        GameJoiner::create([
            'game_id' => $game->id,
            'twitch_user_id' => (string) ($i + 1),
            'username' => $username,
            'status' => GameJoiner::STATUS_ACTIVE,
            'joined_round' => 1,
            'current_vote' => null,
            'last_vote_round' => 1,
            'blocks_remaining' => 1,
            'hp_contributed' => true,
        ]);
    }

    // A slacker with blocks > 1 should NOT be mentioned yet.
    GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '3',
        'username' => 'still_in',
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
        'current_vote' => null,
        'last_vote_round' => 2,
        'blocks_remaining' => 3,
    ]);

    (new ResolveGameRound($game->id, 3))->handle();

    $row = BotChatOutbox::where('user_id', $user->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->message)->toBe('@alice, @bob you became inactive due to lack of input. Type !join if you want to play again next round!')
        ->and($row->sent_at)->toBeNull();
});

test('does not enqueue mention when nobody flipped to inactive', function () {
    Bus::fake();

    $user = makeResolverUser();
    $game = Game::create([
        'user_id' => $user->id,
        'status' => Game::STATUS_RUNNING,
        'current_round' => 2,
        'player_hp' => 5,
        'round_duration_seconds' => 30,
        'round_started_at' => now(),
    ]);

    GameJoiner::create([
        'game_id' => $game->id,
        'twitch_user_id' => '1',
        'username' => 'lurker',
        'status' => GameJoiner::STATUS_ACTIVE,
        'joined_round' => 1,
        'current_vote' => null,
        'last_vote_round' => null,
        'blocks_remaining' => 3,
    ]);

    (new ResolveGameRound($game->id, 2))->handle();

    expect(BotChatOutbox::count())->toBe(0);
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
