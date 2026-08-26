<?php

use App\Events\TwitchEventReceived;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * Twitch retries a notification on any non-2xx or timeout, with the same
 * Twitch-Eventsub-Message-Id each time. twitch_events never stored it, so a
 * redelivery was a second row and a second alert - and, for anything counting
 * events, a second success.
 */
function postSignedNotification(string $messageId, array $event, ?string $header = null): TestResponse
{
    config(['app.twitch_webhook_secret' => 'test-webhook-secret']);

    $body = json_encode([
        'subscription' => ['id' => 'sub-1', 'type' => 'channel.follow', 'version' => '2'],
        'event' => $event,
    ]);
    $timestamp = now()->toIso8601String();
    $signature = 'sha256='.hash_hmac('sha256', $messageId.$timestamp.$body, 'test-webhook-secret');

    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TYPE' => 'notification',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TIMESTAMP' => $timestamp,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_SIGNATURE' => $signature,
    ];
    if ($header !== null) {
        $server['HTTP_TWITCH_EVENTSUB_MESSAGE_ID'] = $header;
    }

    return test()->call('POST', '/api/twitch/webhook', [], [], [], $server, $body);
}

beforeEach(function () {
    // Keep the avatar enrichment off Helix; return the event untouched.
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
    });
});

test('a redelivered notification is stored and broadcast once', function () {
    Event::fake([TwitchEventReceived::class]);
    $user = User::factory()->create(['access_token' => 'token']);
    $event = ['broadcaster_user_id' => $user->twitch_id, 'user_id' => '123', 'user_name' => 'someone'];

    postSignedNotification('msg-same', $event, 'msg-same')->assertOk();
    postSignedNotification('msg-same', $event, 'msg-same')->assertOk();

    expect(TwitchEvent::count())->toBe(1)
        ->and(TwitchEvent::first()->message_id)->toBe('msg-same');
    Event::assertDispatchedTimes(TwitchEventReceived::class, 1);
});

test('two notifications with different message ids are two rows', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    $event = ['broadcaster_user_id' => $user->twitch_id, 'user_id' => '123', 'user_name' => 'someone'];

    postSignedNotification('msg-a', $event, 'msg-a')->assertOk();
    postSignedNotification('msg-b', $event, 'msg-b')->assertOk();

    expect(TwitchEvent::count())->toBe(2);
});

test('rows without a message id never collide with each other', function () {
    // Synthetic events (testCheer, seeds) carry no id. Nullable-unique must
    // let any number of them through.
    TwitchEvent::create(['event_type' => 'channel.cheer', 'event_data' => [], 'twitch_timestamp' => now()]);
    TwitchEvent::create(['event_type' => 'channel.cheer', 'event_data' => [], 'twitch_timestamp' => now()]);

    expect(TwitchEvent::whereNull('message_id')->count())->toBe(2);
});
