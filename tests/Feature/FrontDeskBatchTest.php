<?php

use App\Enums\DeliveryOutcome;
use App\Events\AlertTriggered;
use App\Http\Controllers\TwitchEventSubController;
use App\Jobs\ForgetTwitchCache;
use App\Jobs\ProcessTwitchEvent;
use App\Models\EventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * The Cloudflare front desk (docs/design/eventsub-front-desk.md) answers
 * Twitch at the edge and hands Laravel the queued notifications in batches.
 * The edge holds no secrets: every message is verified here, with the same
 * per-user / global HMAC as the direct webhook, then stored and queued exactly
 * like a direct notification. Old notifications (an outage backlog) are stored
 * and counted but fire no alert.
 */
const FRONT_DESK_SECRET = 'front-desk-test-secret';
const TWITCH_GLOBAL_SECRET = 'test-webhook-secret';

beforeEach(function () {
    config([
        'services.twitch.front_desk_secret' => FRONT_DESK_SECRET,
        'app.twitch_webhook_secret' => TWITCH_GLOBAL_SECRET,
    ]);
});

/** One queued message as the Worker sends it: Twitch's headers plus the raw body. */
function deskMessage(string $id, array $event, string $type = 'channel.follow', ?string $timestamp = null, string $secret = TWITCH_GLOBAL_SECRET, ?string $rawBody = null): array
{
    $body = $rawBody ?? json_encode(['subscription' => ['id' => 'sub-1', 'type' => $type, 'version' => '2'], 'event' => $event]);
    $timestamp ??= now()->toIso8601ZuluString();

    return [
        'id' => $id,
        'timestamp' => $timestamp,
        'signature' => 'sha256='.hash_hmac('sha256', $id.$timestamp.$body, $secret),
        'body' => $body,
    ];
}

function postBatch(array $messages, ?string $secret = FRONT_DESK_SECRET)
{
    $headers = ['CONTENT_TYPE' => 'application/json'];
    if ($secret !== null) {
        $headers['HTTP_X_FRONT_DESK_SECRET'] = $secret;
    }

    return test()->call('POST', '/api/twitch/webhook/batch', [], [], [], $headers, json_encode(['messages' => $messages]));
}

it('refuses a batch without the right front desk secret', function () {
    Queue::fake();

    postBatch([deskMessage('m-1', ['broadcaster_user_id' => '1'])], null)->assertStatus(401);
    postBatch([deskMessage('m-1', ['broadcaster_user_id' => '1'])], 'wrong')->assertStatus(401);

    config(['services.twitch.front_desk_secret' => '']);
    postBatch([deskMessage('m-1', ['broadcaster_user_id' => '1'])], '')->assertStatus(401);

    expect(TwitchEvent::count())->toBe(0);
});

it('ignores a trailing newline on either copy of the shared secret', function () {
    Queue::fake();
    config(['services.twitch.front_desk_secret' => FRONT_DESK_SECRET."\n"]);

    postBatch([deskMessage('m-nl', ['broadcaster_user_id' => '1', 'user_id' => '42'])])->assertOk();
    postBatch([deskMessage('m-nl2', ['broadcaster_user_id' => '1', 'user_id' => '42'])], FRONT_DESK_SECRET.' ')->assertOk();
});

it('stores every valid message and queues it like a direct notification', function () {
    Queue::fake();
    $user = User::factory()->create();
    $event = ['broadcaster_user_id' => (string) $user->twitch_id, 'user_id' => '42', 'user_login' => 'f', 'user_name' => 'F'];

    postBatch([deskMessage('m-1', $event), deskMessage('m-2', $event)])
        ->assertOk()
        ->assertExactJson(['results' => ['m-1' => 'stored', 'm-2' => 'stored']]);

    expect(TwitchEvent::whereIn('message_id', ['m-1', 'm-2'])->count())->toBe(2);
    Queue::assertPushed(ProcessTwitchEvent::class, 2);
    Queue::assertPushedOn(ProcessTwitchEvent::QUEUE, ProcessTwitchEvent::class);
});

it('verifies against the streamer\'s own webhook secret too', function () {
    Queue::fake();
    $user = User::factory()->create(['webhook_secret' => 'per-user-secret']);
    $event = ['broadcaster_user_id' => (string) $user->twitch_id, 'user_id' => '42'];

    postBatch([deskMessage('m-own', $event, secret: 'per-user-secret')])
        ->assertExactJson(['results' => ['m-own' => 'stored']]);
});

it('marks a redelivered message as a duplicate and stores it once', function () {
    Queue::fake();
    $event = ['broadcaster_user_id' => '1', 'user_id' => '42'];

    postBatch([deskMessage('m-dup', $event)])->assertExactJson(['results' => ['m-dup' => 'stored']]);
    postBatch([deskMessage('m-dup', $event)])->assertExactJson(['results' => ['m-dup' => 'duplicate']]);

    expect(TwitchEvent::where('message_id', 'm-dup')->count())->toBe(1);
});

it('refuses a forged message and still stores the genuine ones beside it', function () {
    Queue::fake();
    $event = ['broadcaster_user_id' => '1', 'user_id' => '42'];

    postBatch([deskMessage('m-forged', $event, secret: 'not-the-secret'), deskMessage('m-real', $event)])
        ->assertExactJson(['results' => ['m-forged' => 'invalid', 'm-real' => 'stored']]);

    expect(TwitchEvent::where('message_id', 'm-forged')->exists())->toBeFalse();
});

it('verifies the exact body bytes, untouched by the request middleware', function () {
    Queue::fake();
    // Laravel's TrimStrings would trim a body read through $request->input();
    // the HMAC is over the bytes Twitch sent, trailing newline included.
    $body = json_encode(['subscription' => ['type' => 'channel.follow'], 'event' => ['broadcaster_user_id' => '1', 'user_id' => '42']])."\n";

    postBatch([deskMessage('m-raw', [], rawBody: $body)])->assertExactJson(['results' => ['m-raw' => 'stored']]);
});

it('refuses a batch of more than 100', function () {
    Queue::fake();
    $messages = collect(range(1, 101))->map(fn ($i) => deskMessage("m-$i", ['broadcaster_user_id' => '1']))->all();

    postBatch($messages)->assertStatus(422);
    expect(TwitchEvent::count())->toBe(0);
});

it('hands the job Twitch\'s own message timestamp, nanoseconds and all', function () {
    Queue::fake();
    $sent = '2026-11-22T21:21:43.3305328Z';

    postBatch([deskMessage('m-ts', ['broadcaster_user_id' => '1', 'user_id' => '42'], timestamp: $sent)]);

    Queue::assertPushed(ProcessTwitchEvent::class, fn (ProcessTwitchEvent $job) => $job->sentAt === $sent);
});

function lateAlertUser(): User
{
    $user = User::factory()->create(['access_token' => 'token']);
    $alert = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'html' => '<div>[[[event.user_name]]]</div>']);
    EventTemplateMapping::create(['user_id' => $user->id, 'event_type' => 'channel.follow', 'template_id' => $alert->id, 'duration_ms' => 5000, 'enabled' => true]);

    return $user;
}

function processWithSentAt(User $user, ?string $sentAt): TwitchEvent
{
    $event = ['broadcaster_user_id' => (string) $user->twitch_id, 'user_id' => '42', 'user_login' => 'f', 'user_name' => 'F'];
    $row = TwitchEvent::create(['user_id' => $user->id, 'event_type' => 'channel.follow', 'event_data' => $event, 'twitch_timestamp' => now(), 'processed' => false]);

    app(TwitchEventSubController::class)->processStoredEvent($row, ['subscription' => ['type' => 'channel.follow'], 'event' => $event], $sentAt);

    return $row->fresh();
}

it('stores a notification older than five minutes but fires no alert for it', function () {
    Queue::fake([ForgetTwitchCache::class]);
    Http::fake(['*' => Http::response(['data' => []])]);
    Event::fake([AlertTriggered::class]);
    $user = lateAlertUser();

    $row = processWithSentAt($user, now()->subMinutes(10)->format('Y-m-d\TH:i:s.u').'1Z');

    Event::assertNotDispatched(AlertTriggered::class);
    expect($row->outcome)->toBe(DeliveryOutcome::Late);
});

it('still alerts for a notification sent within five minutes, or with no timestamp at all', function () {
    Queue::fake([ForgetTwitchCache::class]);
    Http::fake(['*' => Http::response(['data' => []])]);
    Event::fake([AlertTriggered::class]);
    $user = lateAlertUser();

    processWithSentAt($user, now()->subMinutes(2)->toIso8601ZuluString());
    processWithSentAt($user, null);

    Event::assertDispatched(AlertTriggered::class, 2);
});
