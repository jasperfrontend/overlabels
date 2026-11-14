<?php

use App\Events\AlertTriggered;
use App\Jobs\ProcessTwitchEvent;
use App\Models\EventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * The webhook answers Twitch before doing the slow work. Measured on prod
 * 2026-11-14: a follow cost ~1.4 s of server time before the 200, and 30 at
 * once took 17 s on the web role's few PHP threads - Twitch revokes
 * subscriptions that keep failing to answer. Now the request verifies, scrubs
 * and stores the row (the duplicate guard), queues ProcessTwitchEvent on its
 * own queue, and answers; a dedicated single worker does the rest in order.
 */
function postAnswersFirstNotification(string $type, array $event, string $messageId): void
{
    config(['app.twitch_webhook_secret' => 'test-webhook-secret']);
    $body = json_encode(['subscription' => ['id' => 'sub-1', 'type' => $type, 'version' => '2'], 'event' => $event]);
    $timestamp = now()->toIso8601String();

    test()->call('POST', '/api/twitch/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_ID' => $messageId,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TYPE' => 'notification',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TIMESTAMP' => $timestamp,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_SIGNATURE' => 'sha256='.hash_hmac('sha256', $messageId.$timestamp.$body, 'test-webhook-secret'),
    ], $body)->assertOk();
}

function answersFirstUser(): User
{
    $user = User::factory()->create(['access_token' => 'token']);
    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id, 'type' => 'alert', 'html' => '<div>[[[event.user_name]]]</div>', 'template_tags' => ['event.user_name'],
    ]);
    EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.follow', 'template_id' => $alert->id, 'duration_ms' => 5000, 'enabled' => true,
    ]);

    return $user;
}

test('the webhook stores the event and queues the work instead of doing it', function () {
    Queue::fake();
    Event::fake([AlertTriggered::class]);
    $twitch = $this->mock(TwitchApiService::class);
    $user = answersFirstUser();

    postAnswersFirstNotification('channel.follow', ['broadcaster_user_id' => $user->twitch_id, 'user_id' => '42', 'user_name' => 'follower'], 'msg-1');

    // Stored, so a redelivery is already a duplicate...
    expect(TwitchEvent::where('message_id', 'msg-1')->count())->toBe(1);
    // ...and nothing slow ran before the answer: no Helix call, no alert.
    $twitch->shouldNotHaveReceived('enrichEventWithUserAvatars');
    $twitch->shouldNotHaveReceived('getExtendedUserData');
    Event::assertNotDispatched(AlertTriggered::class);
    Queue::assertPushedOn(ProcessTwitchEvent::QUEUE, ProcessTwitchEvent::class, fn ($job) => $job->twitchEventId === TwitchEvent::where('message_id', 'msg-1')->value('id'));
});

test('a redelivered notification queues no second job', function () {
    Queue::fake();
    $this->mock(TwitchApiService::class);
    $user = answersFirstUser();
    $event = ['broadcaster_user_id' => $user->twitch_id, 'user_id' => '42', 'user_name' => 'follower'];

    postAnswersFirstNotification('channel.follow', $event, 'msg-same');
    postAnswersFirstNotification('channel.follow', $event, 'msg-same');

    Queue::assertPushed(ProcessTwitchEvent::class, 1);
});

test('the queued job does the rest: enrichment onto the row, then the alert', function () {
    Event::fake([AlertTriggered::class]);
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event + ['user_avatar' => 'https://example.test/a.png']);
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldIgnoreMissing();
    });
    $user = answersFirstUser();

    // The test queue runs jobs inline, which is the worker doing its job.
    postAnswersFirstNotification('channel.follow', ['broadcaster_user_id' => $user->twitch_id, 'user_id' => '42', 'user_name' => 'follower'], 'msg-2');

    expect(TwitchEvent::where('message_id', 'msg-2')->first()->event_data['user_avatar'] ?? null)->toBe('https://example.test/a.png');
    Event::assertDispatched(AlertTriggered::class, 1);
});

test('prod runs exactly one worker on the twitch-events queue', function () {
    // Without it nothing consumes the queue and every alert stops; with two,
    // a gift's recipients can be handled before the gift and escape absorbing.
    $deploy = file_get_contents(base_path('config/deploy.yml'));

    expect(substr_count($deploy, '--queue='.ProcessTwitchEvent::QUEUE))->toBe(1)
        ->and($deploy)->not->toMatch('/--queue='.ProcessTwitchEvent::QUEUE.'.*--(processes|workers)/');
});
