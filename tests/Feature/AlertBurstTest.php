<?php

use App\Enums\DeliveryOutcome;
use App\Events\AlertTriggered;
use App\Events\TwitchEventReceived;
use App\Jobs\FlushAlertBurst;
use App\Models\EventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\Alerts\AlertBursts;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Many of the same Twitch event at once is one alert (#343). The first event
 * is held for a flush; events of its kind arriving before the flush join it;
 * at the flush one event plays alone and two or more play one alert carrying
 * burst_count, and the burst stays open for KEEP_OPEN_SECONDS. See AlertBursts.
 *
 * The test queue is sync, which would run the delayed flush at once, so the
 * flush job is faked and run by hand: that is the "second later".
 */
function burstPost(string $type, array $event, ?string $timestamp = null): void
{
    config(['app.twitch_webhook_secret' => 'test-webhook-secret']);
    $messageId = 'msg-'.fake()->uuid();
    $body = json_encode(['subscription' => ['id' => 'sub-1', 'type' => $type, 'version' => '1'], 'event' => $event]);
    $timestamp ??= now()->toIso8601String();

    test()->call('POST', '/api/twitch/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_ID' => $messageId,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TYPE' => 'notification',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TIMESTAMP' => $timestamp,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_SIGNATURE' => 'sha256='.hash_hmac('sha256', $messageId.$timestamp.$body, 'test-webhook-secret'),
    ], $body)->assertOk();
}

function burstUser(array $types): User
{
    $user = User::factory()->create(['access_token' => 'token']);
    foreach ($types as $type) {
        $alert = OverlayTemplate::factory()->create([
            'owner_id' => $user->id,
            'type' => 'alert',
            'html' => '<div>[[[event.user_name]]] [[[event.burst_count]]]</div>',
            'template_tags' => ['event.user_name', 'event.burst_count'],
        ]);
        EventTemplateMapping::create([
            'user_id' => $user->id, 'event_type' => $type, 'template_id' => $alert->id, 'duration_ms' => 6000, 'enabled' => true,
        ]);
    }

    return $user;
}

function burstFollow(User $user, string $who): void
{
    burstPost('channel.follow', [
        'broadcaster_user_id' => $user->twitch_id, 'user_id' => (string) fake()->unique()->randomNumber(7), 'user_name' => $who,
    ]);
}

function burstCheer(User $user, string $userId, int $bits = 100): void
{
    burstPost('channel.cheer', [
        'broadcaster_user_id' => $user->twitch_id, 'user_id' => $userId, 'user_name' => 'Cheerer'.$userId, 'is_anonymous' => false, 'bits' => $bits, 'message' => 'Cheer100',
    ]);
}

/** Run every flush queued so far, as the worker would a second later. */
function burstFlushAll(): void
{
    $jobs = Queue::pushed(FlushAlertBurst::class);
    Queue::fake([FlushAlertBurst::class]);
    foreach ($jobs as $job) {
        app()->call([$job, 'handle']);
    }
}

/** @return list<array{type: string, who: mixed, count: mixed}> every alert broadcast, in order */
function burstAlerts(): array
{
    return Event::dispatched(AlertTriggered::class)->map(function ($e) {
        $alert = $e[0]->broadcastWith()['alert'];

        return [
            'type' => $alert['event_type'],
            'who' => $alert['data']['event.user_name'] ?? null,
            'count' => $alert['data']['event.burst_count'] ?? null,
        ];
    })->values()->all();
}

beforeEach(function () {
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldIgnoreMissing();
    });
    Event::fake([AlertTriggered::class, TwitchEventReceived::class]);
    Queue::fake([FlushAlertBurst::class]);
});

test('a lone follow waits for its flush, then plays its own alert with no burst count', function () {
    $user = burstUser(['channel.follow']);

    burstFollow($user, 'Alice');

    expect(burstAlerts())->toBe([]);
    Queue::assertPushed(FlushAlertBurst::class, 1);

    burstFlushAll();

    expect(burstAlerts())->toBe([['type' => 'channel.follow', 'who' => 'Alice', 'count' => null]]);
});

test('three follows before the flush are one alert carrying the count', function () {
    $user = burstUser(['channel.follow']);

    burstFollow($user, 'Alice');
    burstFollow($user, 'Bob');
    burstFollow($user, 'Carol');
    Queue::assertPushed(FlushAlertBurst::class, 1);
    burstFlushAll();

    $rows = TwitchEvent::where('event_type', 'channel.follow')->orderBy('id')->get();

    expect(burstAlerts())->toBe([['type' => 'channel.follow', 'who' => 'Alice', 'count' => 3]])
        ->and($rows)->toHaveCount(3)
        ->and($rows[1]->outcome)->toBe(DeliveryOutcome::BurstAbsorbed)
        ->and($rows[2]->outcome)->toBe(DeliveryOutcome::BurstAbsorbed);
});

test('every follow in a burst is still broadcast to the overlay as an event', function () {
    $user = burstUser(['channel.follow']);

    burstFollow($user, 'Alice');
    burstFollow($user, 'Bob');
    burstFlushAll();

    Event::assertDispatched(TwitchEventReceived::class, 2);
});

test('an open burst keeps absorbing within the keep-open gap and closes after it', function () {
    $user = burstUser(['channel.follow']);

    burstFollow($user, 'Alice');
    burstFollow($user, 'Bob');
    burstFlushAll();

    $this->travel(AlertBursts::KEEP_OPEN_SECONDS - 1)->seconds();
    burstFollow($user, 'Late to the party');
    Queue::assertNothingPushed();

    $this->travel(AlertBursts::KEEP_OPEN_SECONDS + 1)->seconds();
    burstFollow($user, 'Next wave');
    Queue::assertPushed(FlushAlertBurst::class, 1);
    burstFlushAll();

    expect(burstAlerts())->toBe([
        ['type' => 'channel.follow', 'who' => 'Alice', 'count' => 2],
        ['type' => 'channel.follow', 'who' => 'Next wave', 'count' => null],
    ])->and(TwitchEvent::where('outcome', DeliveryOutcome::BurstAbsorbed)->count())->toBe(2);
});

test('one streamer\'s burst never holds another streamer\'s events', function () {
    $raided = burstUser(['channel.follow']);
    $other = burstUser(['channel.follow']);

    burstFollow($raided, 'Raider1');
    burstFollow($raided, 'Raider2');
    burstFollow($other, 'Quiet follower');

    Queue::assertPushed(FlushAlertBurst::class, 2);
    burstFlushAll();

    expect(collect(burstAlerts())->pluck('who')->sort()->values()->all())->toBe(['Quiet follower', 'Raider1']);
});

test('follows and subs are separate bursts', function () {
    $user = burstUser(['channel.follow', 'channel.subscribe']);

    burstFollow($user, 'Follower');
    burstPost('channel.subscribe', ['broadcaster_user_id' => $user->twitch_id, 'user_id' => '501', 'user_name' => 'Subber', 'tier' => '1000', 'is_gift' => false]);
    burstFlushAll();

    expect(collect(burstAlerts())->pluck('type')->sort()->values()->all())->toBe(['channel.follow', 'channel.subscribe']);
});

test('gifted subs never burst: they stay with the gift bomb handling', function () {
    $user = burstUser(['channel.subscribe']);

    foreach (['R1', 'R2'] as $who) {
        burstPost('channel.subscribe', ['broadcaster_user_id' => $user->twitch_id, 'user_id' => (string) fake()->unique()->randomNumber(6), 'user_name' => $who, 'tier' => '1000', 'is_gift' => true]);
    }

    Queue::assertNothingPushed();
    expect(burstAlerts())->toHaveCount(2);
});

test('cheers from different viewers burst, a repeat cheer from one viewer alerts on its own', function () {
    $user = burstUser(['channel.cheer']);

    burstCheer($user, '111');
    burstCheer($user, '222');
    burstCheer($user, '111');

    expect(burstAlerts())->toBe([['type' => 'channel.cheer', 'who' => 'Cheerer111', 'count' => null]]);

    burstFlushAll();

    expect(burstAlerts())->toBe([
        ['type' => 'channel.cheer', 'who' => 'Cheerer111', 'count' => null],
        ['type' => 'channel.cheer', 'who' => 'Cheerer111', 'count' => 2],
    ]);
});

test('with no alert mapped, an event is not held and still says no_mapping', function () {
    $user = burstUser([]);

    burstFollow($user, 'Alice');
    burstFollow($user, 'Bob');

    Queue::assertNothingPushed();
    expect(TwitchEvent::pluck('outcome')->all())->each->toBe(DeliveryOutcome::NoMapping);
});

test('a late notification never opens a burst', function () {
    $user = burstUser(['channel.follow']);

    burstPost('channel.follow', ['broadcaster_user_id' => $user->twitch_id, 'user_id' => '77', 'user_name' => 'Old'], now()->subMinutes(10)->toIso8601String());

    Queue::assertNothingPushed();
    expect(TwitchEvent::first()->outcome)->toBe(DeliveryOutcome::Late);
});

test('a flush that fails clears its burst, so the kind does not absorb for an hour', function () {
    $user = burstUser(['channel.follow']);

    burstFollow($user, 'Alice');
    $job = Queue::pushed(FlushAlertBurst::class)->first();
    $job->failed(new RuntimeException('worker died'));

    burstFollow($user, 'Bob');

    Queue::assertPushed(FlushAlertBurst::class, 2);
});

test('the burst kind is per reward for channel point redemptions', function () {
    expect(AlertBursts::kindOf('channel.channel_points_custom_reward_redemption.add', ['reward' => ['id' => 'abc']]))
        ->toBe('channel.channel_points_custom_reward_redemption.add:abc')
        ->and(AlertBursts::kindOf('channel.raid', []))->toBeNull()
        ->and(AlertBursts::kindOf('channel.subscription.gift', []))->toBeNull()
        ->and(AlertBursts::kindOf('channel.subscribe', ['is_gift' => true]))->toBeNull()
        ->and(AlertBursts::kindOf('channel.subscribe', ['is_gift' => false]))->toBe('channel.subscribe');
});
