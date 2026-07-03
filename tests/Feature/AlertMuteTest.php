<?php

use App\Events\AlertTriggered;
use App\Events\ControlValueUpdated;
use App\Models\BotChatOutbox;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\AlertMuteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

function muteTestUser(): User
{
    return User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
}

function muteTestAlertTemplate(User $user): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
    ]);
}

function muteTestKofiPipeline(User $user, OverlayTemplate $alertTemplate): ExternalIntegration
{
    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['verification_token' => 'mute-tok'])),
    ]);

    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'overlay_template_id' => $alertTemplate->id,
        'enabled' => true,
        'duration_ms' => 5000,
    ]);

    return $integration;
}

function muteTestKofiPayload(): array
{
    return [
        'verification_token' => 'mute-tok',
        'kofi_transaction_id' => 'txn-'.fake()->uuid(),
        'from_name' => 'Bob',
        'message' => 'Hi!',
        'amount' => '5.00',
        'currency' => 'USD',
        'type' => 'Donation',
        'is_subscription_payment' => false,
        'is_first_subscription_payment' => false,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// AlertMuteService
// ─────────────────────────────────────────────────────────────────────────────

test('setMuted provisions a user-scoped source-managed alerts:muted control and broadcasts', function () {
    Event::fake([ControlValueUpdated::class]);
    $user = muteTestUser();
    $service = app(AlertMuteService::class);

    expect($service->isMuted($user))->toBeFalse();

    $service->setMuted($user, true);

    expect($service->isMuted($user))->toBeTrue();
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'alerts',
        'key' => 'muted',
        'type' => 'boolean',
        'value' => '1',
        'overlay_template_id' => null,
        'source_managed' => true,
    ]);

    Event::assertDispatched(ControlValueUpdated::class, function (ControlValueUpdated $event) use ($user) {
        return $event->key === 'alerts:muted'
            && $event->overlaySlug === ''
            && $event->value === '1'
            && $event->broadcasterId === $user->twitch_id;
    });
});

test('setMuted is idempotent and only broadcasts on an actual change', function () {
    $user = muteTestUser();
    $service = app(AlertMuteService::class);
    $service->setMuted($user, true);

    Event::fake([ControlValueUpdated::class]);
    $service->setMuted($user, true);
    Event::assertNotDispatched(ControlValueUpdated::class);

    $service->setMuted($user, false);
    Event::assertDispatched(ControlValueUpdated::class, fn (ControlValueUpdated $e) => $e->value === '0');
    expect($service->isMuted($user))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// Session toggle endpoint
// ─────────────────────────────────────────────────────────────────────────────

test('session mute endpoint toggles the mute state', function () {
    $user = muteTestUser();
    $this->actingAs($user);

    $this->post('/dashboard/events/mute', ['muted' => true])->assertRedirect();
    expect(app(AlertMuteService::class)->isMuted($user))->toBeTrue();

    $this->post('/dashboard/events/mute', ['muted' => false])->assertRedirect();
    expect(app(AlertMuteService::class)->isMuted($user))->toBeFalse();
});

test('session mute endpoint requires auth', function () {
    $this->post('/dashboard/events/mute', ['muted' => true])->assertRedirect();
    expect(OverlayControl::where('source', 'alerts')->where('key', 'muted')->exists())->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// Dispatch-site guards: muted is muted
// ─────────────────────────────────────────────────────────────────────────────

test('a live external event fires no alert and no bot message while muted, but is still recorded', function () {
    $user = muteTestUser();
    $alertTemplate = muteTestAlertTemplate($user);
    $alertTemplate->update(['bot_message_expression' => 'Thanks [[[event.from_name]]]!']);
    $user->update(['bot_enabled' => true]);
    $integration = muteTestKofiPipeline($user, $alertTemplate);

    app(AlertMuteService::class)->setMuted($user, true);

    Event::fake([AlertTriggered::class]);
    $payload = muteTestKofiPayload();

    $this->post("/api/webhooks/kofi/{$integration->webhook_token}", ['data' => json_encode($payload)]);

    Event::assertNotDispatched(AlertTriggered::class);
    expect(BotChatOutbox::where('user_id', $user->id)->count())->toBe(0);

    // The event itself still lands - only alert output is suppressed.
    expect(ExternalEvent::where('user_id', $user->id)->where('service', 'kofi')->count())->toBe(1);
});

test('a live external event fires the alert again after unmuting', function () {
    $user = muteTestUser();
    $alertTemplate = muteTestAlertTemplate($user);
    $integration = muteTestKofiPipeline($user, $alertTemplate);

    app(AlertMuteService::class)->setMuted($user, true);
    app(AlertMuteService::class)->setMuted($user, false);

    Event::fake([AlertTriggered::class]);

    $this->post("/api/webhooks/kofi/{$integration->webhook_token}", ['data' => json_encode(muteTestKofiPayload())]);

    Event::assertDispatched(AlertTriggered::class);
});

test('external event replay is blocked with a warning while muted', function () {
    $user = muteTestUser();
    $externalEvent = ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'message_id' => 'msg-'.fake()->uuid(),
        'raw_payload' => ['from_name' => 'Dave'],
        'normalized_payload' => ['event.from_name' => 'Dave'],
    ]);

    app(AlertMuteService::class)->setMuted($user, true);
    $this->actingAs($user);

    Event::fake([AlertTriggered::class]);

    $this->post("/external-events/{$externalEvent->id}/replay")
        ->assertRedirect()
        ->assertSessionHas('type', 'warning');

    Event::assertNotDispatched(AlertTriggered::class);
});

test('twitch event replay is blocked with a warning while muted', function () {
    $user = muteTestUser();
    $twitchEvent = TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.cheer',
        'event_data' => ['user_name' => 'Cheerer', 'bits' => 100],
        'twitch_timestamp' => now(),
        'processed' => true,
    ]);

    app(AlertMuteService::class)->setMuted($user, true);
    $this->actingAs($user);

    Event::fake([AlertTriggered::class]);

    $this->post("/events/{$twitchEvent->id}/replay")
        ->assertRedirect()
        ->assertSessionHas('type', 'warning');

    Event::assertNotDispatched(AlertTriggered::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Reserved key + preset provisioning
// ─────────────────────────────────────────────────────────────────────────────

test('alerts is a reserved bare control key', function () {
    $user = muteTestUser();
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'slug' => 'static-'.fake()->unique()->lexify('????????'),
    ]);
    $this->actingAs($user);

    $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'alerts',
        'type' => 'text',
    ])->assertStatus(422);
});

test('the alerts:muted preset can be added from the control picker', function () {
    $user = muteTestUser();
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'slug' => 'static-'.fake()->unique()->lexify('????????'),
    ]);
    $this->actingAs($user);

    $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'muted',
        'type' => 'boolean',
        'source' => 'alerts',
    ])->assertStatus(201);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'alerts',
        'key' => 'muted',
        'overlay_template_id' => null,
        'source_managed' => true,
        'value' => '0',
    ]);
});
