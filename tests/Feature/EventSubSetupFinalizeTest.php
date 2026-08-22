<?php

use App\Events\EventSubSetupCompleted;
use App\Jobs\FinalizeEventSubSetup;
use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\User;
use App\Models\UserEventsubSubscription;
use App\Services\TwitchEventSubService;
use App\Services\UserEventSubManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('setup job defers the completion broadcast to a delayed finalize job', function () {
    // Broadcasting from the setup job itself is the race: Twitch's challenges
    // for the last-created subscriptions are still in flight one second after
    // the creates, so statuses read at that moment undercount and the page
    // reload shows 22/27. The broadcast must come from FinalizeEventSubSetup
    // after a delay, never directly from here.
    Event::fake([EventSubSetupCompleted::class]);
    Bus::fake([FinalizeEventSubSetup::class]);

    $user = User::factory()->create();

    $manager = Mockery::mock(UserEventSubManager::class);
    $manager->shouldReceive('setupUserSubscriptions')
        ->once()
        ->andReturn([
            'created' => ['channel.follow'],
            'failed' => [],
            'existing' => [],
            'skipped_missing_scope' => [],
        ]);

    (new SetupUserEventSubSubscriptions($user))->handle($manager);

    Event::assertNotDispatched(EventSubSetupCompleted::class);
    Bus::assertDispatched(FinalizeEventSubSetup::class, function (FinalizeEventSubSetup $job) {
        return $job->delay !== null;
    });
});

test('finalize heals a row the too-early verify left stuck at pending', function () {
    // The stuck shape observed in prod: the challenge webhook arrived before
    // the row insert (its update matched nothing), the inline verify then
    // stamped the row pending, and nothing ever revisited it - while Twitch
    // had the subscription enabled the whole time.
    Event::fake([EventSubSetupCompleted::class]);

    $user = User::factory()->create(['twitch_id' => '12345']);
    $sub = UserEventsubSubscription::factory()->create([
        'user_id' => $user->id,
        'twitch_subscription_id' => 'sub-poll-progress',
        'event_type' => 'channel.poll.progress',
        'status' => 'webhook_callback_verification_pending',
    ]);

    $this->mock(TwitchEventSubService::class, function ($mock) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('getSubscriptions')
            ->with('app-token', ['user_id' => '12345'])
            ->andReturn(['data' => [
                ['id' => 'sub-poll-progress', 'status' => 'enabled'],
            ]]);
    });

    $results = [
        'created' => ['channel.poll.progress'],
        'failed' => [],
        'existing' => [],
        'skipped_missing_scope' => [],
    ];

    (new FinalizeEventSubSetup($user, $results))->handle(app(UserEventSubManager::class));

    expect($sub->fresh()->status)->toBe('enabled');

    Event::assertDispatched(EventSubSetupCompleted::class, function (EventSubSetupCompleted $event) {
        return $event->broadcasterId === '12345'
            && $event->created === ['channel.poll.progress']
            && $event->success === true;
    });
});

test('finalize still broadcasts when the re-verify blows up', function () {
    // The results are already known; a failed reconciliation must not leave
    // the settings page waiting forever.
    Event::fake([EventSubSetupCompleted::class]);

    $user = User::factory()->create(['twitch_id' => '12345']);

    $manager = Mockery::mock(UserEventSubManager::class);
    $manager->shouldReceive('verifyUserSubscriptions')
        ->once()
        ->andThrow(new Exception('twitch is down'));

    (new FinalizeEventSubSetup($user, [
        'created' => [],
        'failed' => [],
        'existing' => [],
        'skipped_missing_scope' => [],
    ]))->handle($manager);

    Event::assertDispatched(EventSubSetupCompleted::class);
});
