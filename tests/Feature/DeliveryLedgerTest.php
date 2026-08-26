<?php

use App\Broadcasting\MeteredBroadcaster;
use App\Enums\DeliveryOutcome;
use App\Events\AlertTriggered;
use App\Jobs\DeleteTestTwitchEvent;
use App\Listeners\MarkAlertDeliveryFailed;
use App\Models\EventTemplateMapping;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\AlertMuteService;
use App\Services\BroadcastMeter;
use App\Services\TwitchApiService;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Pusher\Pusher;

uses(RefreshDatabase::class);

/**
 * The delivery ledger, docs/design/event-delivery-ledger-2026-08.md.
 * Request side: a no_target outcome, or the alert_id of the broadcast just
 * queued. Worker side: delivered / no_listener with Reverb's count. Listener:
 * failed, once, after the last retry.
 */
function ledgerUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'token',
        'bot_enabled' => true,
    ]);
}

function ledgerAlert(User $user, array $attributes = []): OverlayTemplate
{
    return OverlayTemplate::factory()->create(array_merge([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        'html' => '<div>[[[event.user_name]]]</div>',
        'css' => '',
        'alert_sound_url' => null,
        'tts_message' => null,
        'chat_message' => null,
        'template_tags' => ['event.user_name'],
    ], $attributes));
}

function ledgerMapping(User $user, OverlayTemplate $alert): void
{
    EventTemplateMapping::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'template_id' => $alert->id,
        'enabled' => true,
        'duration_ms' => 5000,
    ]);
}

function ledgerFollow(User $user): TwitchEvent
{
    $id = 'msg-'.Str::uuid();
    postSignedNotification($id, [
        'broadcaster_user_id' => $user->twitch_id,
        'user_id' => '123',
        'user_name' => 'someone',
    ], $id)->assertOk();

    return TwitchEvent::where('message_id', $id)->firstOrFail();
}

beforeEach(function () {
    Event::fake([AlertTriggered::class]);
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
    });
});

// ── request side, Twitch path ────────────────────────────────────────────────

test('an event with no alert mapping is no_mapping', function () {
    $row = ledgerFollow(ledgerUser());

    expect($row->outcome)->toBe(DeliveryOutcome::NoMapping)
        ->and($row->alert_id)->toBeNull();
});

test('an event for a broadcaster we do not know is unknown_user', function () {
    $id = 'msg-'.Str::uuid();
    postSignedNotification($id, ['broadcaster_user_id' => '999999999', 'user_name' => 'x'], $id)->assertOk();

    $row = TwitchEvent::where('message_id', $id)->firstOrFail();
    expect($row->user_id)->toBeNull()
        ->and($row->outcome)->toBe(DeliveryOutcome::UnknownUser);
});

test('a muted account records muted and queues no broadcast', function () {
    $user = ledgerUser();
    ledgerMapping($user, ledgerAlert($user));
    app(AlertMuteService::class)->setMuted($user, true);

    $row = ledgerFollow($user);

    expect($row->outcome)->toBe(DeliveryOutcome::Muted)->and($row->alert_id)->toBeNull();
    Event::assertNotDispatched(AlertTriggered::class);
});

test('a chat-only alert records chat_only and no alert id', function () {
    $user = ledgerUser();
    ledgerMapping($user, ledgerAlert($user, ['html' => '', 'chat_message' => 'hi [[[event.user_name]]]']));

    $row = ledgerFollow($user);

    expect($row->outcome)->toBe(DeliveryOutcome::ChatOnly)->and($row->alert_id)->toBeNull();
});

test('an alert with overlay work stamps the row with the broadcast alert id and leaves it open', function () {
    $user = ledgerUser();
    ledgerMapping($user, ledgerAlert($user));

    $row = ledgerFollow($user);

    expect($row->alert_id)->not->toBeNull()
        ->and($row->outcome)->toBeNull()
        ->and($row->delivered_at)->toBeNull();
    Event::assertDispatched(AlertTriggered::class, fn (AlertTriggered $e) => $e->alertId === $row->alert_id);
});

// ── request side, external path ──────────────────────────────────────────────

test('an external event with an alert is stamped, one without is no_mapping', function () {
    [$user, $integration] = makeKofiIntegration();
    $user->forceFill(['access_token' => 'token'])->save();

    postKofi($integration->webhook_token, kofiPayload(['kofi_transaction_id' => 'txn-nomap']))->assertOk();
    $noMapping = ExternalEvent::where('message_id', 'txn-nomap')->firstOrFail();
    expect($noMapping->outcome)->toBe(DeliveryOutcome::NoMapping)->and($noMapping->alert_id)->toBeNull();

    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        'html' => '<div>[[[event.from_name]]]</div>', 'css' => '', 'alert_sound_url' => null, 'tts_message' => null,
    ]);
    ExternalEventTemplateMapping::create([
        'user_id' => $user->id, 'service' => 'kofi', 'event_type' => 'donation',
        'overlay_template_id' => $alert->id, 'enabled' => true, 'duration_ms' => 5000,
    ]);

    postKofi($integration->webhook_token, kofiPayload(['kofi_transaction_id' => 'txn-alert']))->assertOk();
    $stamped = ExternalEvent::where('message_id', 'txn-alert')->firstOrFail();
    expect($stamped->alert_id)->not->toBeNull()->and($stamped->outcome)->toBeNull();
});

// ── worker side ──────────────────────────────────────────────────────────────

function ledgerBroadcast(string $alertId, int $connections): void
{
    config()->set('metering.enabled', false);
    $pusher = Mockery::mock(Pusher::class);
    $pusher->shouldReceive('trigger')->once()->andReturn((object) [
        'channels' => (object) ['private-alerts.1' => (object) ($connections > 0 ? ['subscription_count' => $connections] : [])],
    ]);

    (new MeteredBroadcaster(new PusherBroadcaster($pusher), new BroadcastMeter))
        ->broadcast(['private-alerts.1'], 'alert.triggered', ['alert' => ['alert_id' => $alertId]]);
}

test('the worker closes a Twitch row as delivered with the connection count', function () {
    $row = TwitchEvent::create(['event_type' => 'channel.follow', 'event_data' => [], 'twitch_timestamp' => now(), 'alert_id' => (string) Str::uuid()]);

    ledgerBroadcast($row->alert_id, 2);

    $row->refresh();
    expect($row->outcome)->toBe(DeliveryOutcome::Delivered)
        ->and($row->connections)->toBe(2)
        ->and($row->delivered_at)->not->toBeNull();
});

test('the worker closes an external row nobody was listening to as no_listener', function () {
    $user = ledgerUser();
    $row = ExternalEvent::create([
        'user_id' => $user->id, 'service' => 'kofi', 'event_type' => 'donation', 'message_id' => 'txn-nl',
        'raw_payload' => [], 'normalized_payload' => [], 'alert_id' => (string) Str::uuid(),
    ]);

    ledgerBroadcast($row->alert_id, 0);

    $row->refresh();
    expect($row->outcome)->toBe(DeliveryOutcome::NoListener)->and($row->connections)->toBe(0);
});

test('a close for an alert id with no row behind it is a no-op', function () {
    $row = TwitchEvent::create(['event_type' => 'channel.follow', 'event_data' => [], 'twitch_timestamp' => now(), 'alert_id' => (string) Str::uuid()]);

    ledgerBroadcast((string) Str::uuid(), 3);

    expect($row->refresh()->outcome)->toBeNull();
});

test('a broadcast without an alert id touches no row', function () {
    $row = TwitchEvent::create(['event_type' => 'channel.follow', 'event_data' => [], 'twitch_timestamp' => now(), 'alert_id' => (string) Str::uuid()]);
    config()->set('metering.enabled', false);
    $pusher = Mockery::mock(Pusher::class);
    $pusher->shouldReceive('trigger')->once()->andReturn((object) ['channels' => (object) ['private-alerts.1' => (object) ['subscription_count' => 1]]]);

    (new MeteredBroadcaster(new PusherBroadcaster($pusher), new BroadcastMeter))
        ->broadcast(['private-alerts.1'], 'control.updated', ['key' => 'x']);

    expect($row->refresh()->outcome)->toBeNull();
});

// ── final failure ────────────────────────────────────────────────────────────

function failedJobEvent(object $command): JobFailed
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn(['data' => ['command' => serialize($command)]]);

    return new JobFailed('database', $job, new RuntimeException('Pusher error: Payload too large.'));
}

test('an AlertTriggered job that exhausted its retries closes the row as failed', function () {
    $row = TwitchEvent::create(['event_type' => 'channel.follow', 'event_data' => [], 'twitch_timestamp' => now(), 'alert_id' => (string) Str::uuid()]);
    $alert = new AlertTriggered($row->alert_id, '<div/>', '', [], 5000, '1');

    event(failedJobEvent(new BroadcastEvent($alert)));

    expect($row->refresh()->outcome)->toBe(DeliveryOutcome::Failed);
});

test('a failed job of any other kind is ignored', function () {
    $row = TwitchEvent::create(['event_type' => 'channel.follow', 'event_data' => [], 'twitch_timestamp' => now(), 'alert_id' => (string) Str::uuid()]);

    event(failedJobEvent(new DeleteTestTwitchEvent($row->id)));

    expect($row->refresh()->outcome)->toBeNull();
    expect(MarkAlertDeliveryFailed::alertIdFrom(['data' => ['command' => 'not serialised']]))->toBeNull();
});

// ── the vocabulary ───────────────────────────────────────────────────────────

test('the outcome vocabulary is pinned and only the delivery family is scored', function () {
    expect(array_map(fn ($c) => $c->value, DeliveryOutcome::cases()))
        ->toBe(['delivered', 'no_listener', 'failed', 'no_mapping', 'muted', 'chat_only', 'unknown_user']);

    expect(array_map(fn ($c) => $c->isScored(), DeliveryOutcome::cases()))
        ->toBe([true, true, true, false, false, false, false]);
});
