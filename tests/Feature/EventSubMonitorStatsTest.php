<?php

use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\User;
use App\Models\UserEventsubSubscription;
use App\Services\TwitchEventSubService;
use App\Services\UserEventSubManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

/**
 * `eventsub:monitor --fix` runs hourly and calls getGlobalStats() first. That
 * method plucked a `count` column it never selected, so every scheduled run
 * threw before it checked a single subscription - on prod, from April to
 * November 2026, as soon as any subscription row existed.
 */
it('counts subscriptions by type', function () {
    $user = User::factory()->create();
    UserEventsubSubscription::factory()->count(2)->create(['user_id' => $user->id, 'event_type' => 'channel.follow', 'status' => 'enabled']);
    UserEventsubSubscription::factory()->create(['user_id' => $user->id, 'event_type' => 'stream.online', 'status' => 'enabled']);

    $stats = app(UserEventSubManager::class)->getGlobalStats();

    expect($stats['subscriptions_by_type']->all())->toEqual([
        'channel.follow' => 2,
        'stream.online' => 1,
    ]);
});

it('runs the monitor to completion when subscriptions exist', function () {
    $user = User::factory()->create();
    UserEventsubSubscription::factory()->create(['user_id' => $user->id, 'event_type' => 'channel.follow', 'status' => 'enabled']);

    $this->artisan('eventsub:monitor')->assertSuccessful();
});

/**
 * A row Twitch no longer lists is marked not_found_on_twitch, and verify then
 * stamps last_verified_at, so it never looks stale again. Nothing recreated it:
 * the event stopped arriving for good while the integrations page still
 * counted the row. The repair must not go through the force-recreate path,
 * whose Twitch-side cleanup deletes every subscription whose condition names
 * the user - for the bot account, that is every streamer's
 * channel.chat.notification subscription.
 */
it('drops not_found_on_twitch rows and re-runs a non-destructive setup', function () {
    Bus::fake([SetupUserEventSubSubscriptions::class]);

    $user = User::factory()->create(['eventsub_connected_at' => now()]);
    $dead = UserEventsubSubscription::factory()->create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'status' => 'not_found_on_twitch',
        'last_verified_at' => now(),
    ]);
    $alive = UserEventsubSubscription::factory()->create([
        'user_id' => $user->id,
        'event_type' => 'stream.online',
        'status' => 'enabled',
        'last_verified_at' => now(),
    ]);

    // Exit code reflects the health score (1 of 2 enabled = poor), not this branch.
    $this->artisan('eventsub:monitor', ['--fix' => true])->run();

    expect(UserEventsubSubscription::find($dead->id))->toBeNull()
        ->and(UserEventsubSubscription::find($alive->id))->not->toBeNull();

    Bus::assertDispatched(SetupUserEventSubSubscriptions::class, function (SetupUserEventSubSubscriptions $job) {
        return (new ReflectionProperty($job, 'forceRecreate'))->getValue($job) === false;
    });
});

it('only reports not_found_on_twitch rows without --fix', function () {
    Bus::fake([SetupUserEventSubSubscriptions::class]);

    $user = User::factory()->create(['eventsub_connected_at' => now()]);
    $dead = UserEventsubSubscription::factory()->create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'status' => 'not_found_on_twitch',
        'last_verified_at' => now(),
    ]);

    $this->artisan('eventsub:monitor')
        ->expectsOutputToContain('no longer on Twitch')
        ->run();

    expect(UserEventsubSubscription::find($dead->id))->not->toBeNull();
    Bus::assertNotDispatched(SetupUserEventSubSubscriptions::class);
});

/**
 * Twitch pages its subscription list at 100 and verify reads one page. The
 * user_id filter matches any id in a condition, so the bot account's list
 * includes every streamer's chat notification subscription and sits at the
 * cap. A row missing from an incomplete list is unknown, not gone.
 */
it('does not mark rows missing when Twitch returned only the first page', function () {
    $user = User::factory()->create(['twitch_id' => '12345']);
    $sub = UserEventsubSubscription::factory()->create([
        'user_id' => $user->id,
        'twitch_subscription_id' => 'on-page-two',
        'event_type' => 'channel.follow',
        'status' => 'enabled',
    ]);

    $this->mock(TwitchEventSubService::class, function ($mock) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('getSubscriptions')->andReturn([
            'data' => [['id' => 'something-else', 'status' => 'enabled']],
            'pagination' => ['cursor' => 'next-page'],
        ]);
    });

    app(UserEventSubManager::class)->verifyUserSubscriptions($user);

    expect($sub->fresh()->status)->toBe('enabled');
});

it('marks a row missing when Twitch returned the complete list', function () {
    $user = User::factory()->create(['twitch_id' => '12345']);
    $sub = UserEventsubSubscription::factory()->create([
        'user_id' => $user->id,
        'twitch_subscription_id' => 'gone',
        'event_type' => 'channel.follow',
        'status' => 'enabled',
    ]);

    $this->mock(TwitchEventSubService::class, function ($mock) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('getSubscriptions')->andReturn([
            'data' => [['id' => 'something-else', 'status' => 'enabled']],
            'pagination' => [],
        ]);
    });

    app(UserEventSubManager::class)->verifyUserSubscriptions($user);

    expect($sub->fresh()->status)->toBe('not_found_on_twitch');
});

/**
 * The cleanup asks Twitch for every subscription matching the user's id, and
 * that filter matches ANY id in a condition. For the bot account the answer
 * includes every streamer's channel.chat.notification subscription (condition
 * user_id = bot), so a reconnect, a forced repair or an erasure of the bot
 * account deleted all of them. Only subscriptions this account is the
 * broadcaster of are its to remove.
 */
it('removes only subscriptions the user is the broadcaster of', function () {
    $bot = User::factory()->create(['twitch_id' => '1130071166']);

    $deleted = [];
    $this->mock(TwitchEventSubService::class, function ($mock) use (&$deleted) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('getSubscriptions')->andReturn(['data' => [
            ['id' => 'bot-own-follow', 'condition' => ['broadcaster_user_id' => '1130071166', 'moderator_user_id' => '1130071166']],
            ['id' => 'bot-own-raid', 'condition' => ['to_broadcaster_user_id' => '1130071166']],
            ['id' => 'streamer-chat-notification', 'condition' => ['broadcaster_user_id' => '73327367', 'user_id' => '1130071166']],
        ]]);
        $mock->shouldReceive('deleteSubscription')->andReturnUsing(function ($token, $id) use (&$deleted) {
            $deleted[] = $id;

            return true;
        });
    });

    app(UserEventSubManager::class)->removeUserSubscriptions($bot);

    expect($deleted)->toEqualCanonicalizing(['bot-own-follow', 'bot-own-raid']);
});

/**
 * The Ghost User has auto-connect on, no connection and no Twitch login. Its
 * setup job throws on every attempt, so the hourly fix pass would queue three
 * failures an hour for it forever and bury real ones in failed_jobs.
 */
it('never queues a setup for a system user', function () {
    Bus::fake([SetupUserEventSubSubscriptions::class]);

    User::factory()->create([
        'twitch_id' => 'GHOST_USER',
        'is_system_user' => true,
        'eventsub_auto_connect' => true,
        'eventsub_connected_at' => null,
    ]);
    $streamer = User::factory()->create([
        'eventsub_auto_connect' => true,
        'eventsub_connected_at' => null,
    ]);

    $this->artisan('eventsub:monitor', ['--fix' => true])->run();

    Bus::assertDispatchedTimes(SetupUserEventSubSubscriptions::class, 1);
    Bus::assertDispatched(SetupUserEventSubSubscriptions::class, function (SetupUserEventSubSubscriptions $job) use ($streamer) {
        return (new ReflectionProperty($job, 'user'))->getValue($job)->is($streamer);
    });
});

/**
 * Report-only runs still verify: a report that cannot see which subscriptions
 * Twitch dropped cannot say what --fix would repair. Verifying reads Twitch
 * and writes statuses here; it never creates or deletes a subscription.
 */
it('verifies stale subscriptions without --fix and repairs nothing', function () {
    Bus::fake([SetupUserEventSubSubscriptions::class]);

    $user = User::factory()->create(['twitch_id' => '12345', 'eventsub_connected_at' => now()]);
    $alive = UserEventsubSubscription::factory()->create([
        'user_id' => $user->id,
        'twitch_subscription_id' => 'alive',
        'event_type' => 'stream.online',
        'status' => 'enabled',
        'last_verified_at' => now()->subDays(3),
    ]);
    $dropped = UserEventsubSubscription::factory()->create([
        'user_id' => $user->id,
        'twitch_subscription_id' => 'dropped',
        'event_type' => 'channel.follow',
        'status' => 'enabled',
        'last_verified_at' => now()->subDays(3),
    ]);

    $this->mock(TwitchEventSubService::class, function ($mock) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('getSubscriptions')->andReturn([
            'data' => [['id' => 'alive', 'status' => 'enabled']],
            'pagination' => [],
        ]);
        $mock->shouldNotReceive('createSubscription');
        $mock->shouldNotReceive('deleteSubscription');
    });

    $this->artisan('eventsub:monitor')->run();

    expect($alive->fresh()->status)->toBe('enabled')
        ->and($dropped->fresh()->status)->toBe('not_found_on_twitch');
    Bus::assertNotDispatched(SetupUserEventSubSubscriptions::class);
});
