<?php

use App\Events\EventSubSetupCompleted;
use App\Events\EventSubSetupProgress;
use App\Jobs\FinalizeEventSubSetup;
use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\User;
use App\Services\TwitchEventSubService;
use App\Services\TwitchScopeService;
use App\Services\UserEventSubManager;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('progress event broadcasts synchronously, never via the queue', function () {
    // The dispatching code runs inside the setup job ON the queue worker. A
    // queued broadcast would wait in line behind that very job, so all 27
    // progress updates would arrive at once, after the fact.
    expect(is_a(EventSubSetupProgress::class, ShouldBroadcastNow::class, true))->toBeTrue();
});

test('setup broadcasts one progress update per supported event, counting up to the total', function () {
    Event::fake([EventSubSetupProgress::class]);

    $user = User::factory()->create([
        'twitch_id' => '12345',
        'access_token' => 'user-token',
        'twitch_scopes' => TwitchScopeService::REQUIRED_SCOPES,
    ]);

    $this->mock(TwitchEventSubService::class, function ($mock) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
        $mock->shouldReceive('createSubscription')->andReturnUsing(fn ($token, $payload) => [
            'data' => [[
                'id' => 'sub-'.$payload['type'],
                'status' => 'webhook_callback_verification_pending',
                'created_at' => now()->toIso8601String(),
            ]],
        ]);
        $mock->shouldReceive('getSubscriptions')->andReturn(['data' => []]);
    });

    app(UserEventSubManager::class)->setupUserSubscriptions($user);

    $total = count(UserEventSubManager::SUPPORTED_EVENTS);
    $events = Event::dispatched(EventSubSetupProgress::class);

    expect($events)->toHaveCount($total);
    expect($events->map(fn (array $args) => $args[0]->processed)->all())->toBe(range(1, $total));

    $last = $events->last()[0];
    expect($last->total)->toBe($total)
        ->and($last->connected)->toBe($total)
        ->and($last->phase)->toBe('connecting');
});

test('setup job announces the verifying phase when the creates are done', function () {
    Event::fake([EventSubSetupProgress::class, EventSubSetupCompleted::class]);
    Bus::fake([FinalizeEventSubSetup::class]);

    $user = User::factory()->create(['twitch_id' => '12345']);

    $manager = Mockery::mock(UserEventSubManager::class);
    $manager->shouldReceive('setupUserSubscriptions')
        ->once()
        ->andReturn([
            'created' => ['channel.follow'],
            'failed' => [],
            'existing' => ['stream.online'],
            'skipped_missing_scope' => [],
        ]);

    (new SetupUserEventSubSubscriptions($user))->handle($manager);

    Event::assertDispatched(EventSubSetupProgress::class, function (EventSubSetupProgress $event) {
        return $event->phase === 'verifying'
            && $event->processed === $event->total
            && $event->connected === 2;
    });
});
