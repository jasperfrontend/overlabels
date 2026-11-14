<?php

use App\Events\AlertTriggered;
use App\Models\EventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * The overlay queues alerts so they take turns, but a `*.progress` alert (poll,
 * prediction, hype train, goal, charity) is one ongoing thing told again and
 * again: it must update the alert on screen instead of queueing a copy. The
 * overlay can only tell those apart by the event type, and `data` is pruned
 * to the tags the alert template uses, so the type rides the broadcast.
 */
function postAlertTypeNotification(string $type, array $event): void
{
    config(['app.twitch_webhook_secret' => 'test-webhook-secret']);
    $messageId = 'msg-'.fake()->uuid();
    $body = json_encode(['subscription' => ['id' => 'sub-1', 'type' => $type, 'version' => '1'], 'event' => $event]);
    $timestamp = now()->toIso8601String();

    test()->call('POST', '/api/twitch/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_ID' => $messageId,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TYPE' => 'notification',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TIMESTAMP' => $timestamp,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_SIGNATURE' => 'sha256='.hash_hmac('sha256', $messageId.$timestamp.$body, 'test-webhook-secret'),
    ], $body)->assertOk();
}

beforeEach(function () {
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldIgnoreMissing();
    });
});

test('a Twitch alert broadcast names the event that fired it', function (string $type, array $event) {
    Event::fake([AlertTriggered::class]);
    $user = User::factory()->create(['access_token' => 'token']);
    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'type' => 'alert',
        'html' => '<div>[[[event.title]]]</div>',
        'template_tags' => ['event.title'],
    ]);
    EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => $type,
        'template_id' => $alert->id,
        'duration_ms' => 6000,
        'enabled' => true,
    ]);

    postAlertTypeNotification($type, ['broadcaster_user_id' => $user->twitch_id] + $event);

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $sent) use ($type) {
        $payload = $sent->broadcastWith()['alert'];

        // The template only uses event.title, so data alone could not say.
        return $payload['event_type'] === $type && ! array_key_exists('event.type', $payload['data']);
    });
})->with([
    'a progress update' => ['channel.poll.progress', ['title' => 'Next game?']],
    'a one-off event' => ['channel.cheer', ['user_name' => 'someone', 'bits' => 100, 'title' => 'x']],
]);

test('an alert built without an event type broadcasts null, not a missing key', function () {
    $sent = new AlertTriggered('id', '<b></b>', '', [], 5000, '123');

    expect($sent->broadcastWith()['alert'])->toHaveKey('event_type')
        ->and($sent->broadcastWith()['alert']['event_type'])->toBeNull();
});
