<?php

use App\Events\AlertTriggered;
use App\Models\EventTemplateMapping;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeAlertAndStaticTemplates(): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $alert = OverlayTemplate::factory()->create([
        'owner_id'   => $user->id,
        'fork_of_id' => null,
        'type'       => 'alert',
        'slug'       => 'alert-'.fake()->unique()->lexify('????????'),
    ]);

    $static1 = OverlayTemplate::factory()->create([
        'owner_id'   => $user->id,
        'fork_of_id' => null,
        'type'       => 'static',
        'slug'       => 'static-'.fake()->unique()->lexify('????????'),
    ]);

    $static2 = OverlayTemplate::factory()->create([
        'owner_id'   => $user->id,
        'fork_of_id' => null,
        'type'       => 'static',
        'slug'       => 'static-'.fake()->unique()->lexify('????????'),
    ]);

    return [$user, $alert, $static1, $static2];
}

// ──────────────────────────────────────────────────────────────────────────────
// updateTargetOverlays
// ──────────────────────────────────────────────────────────────────────────────

test('updateTargetOverlays saves selected static overlay IDs', function () {
    [$user, $alert, $static1, $static2] = makeAlertAndStaticTemplates();
    $this->actingAs($user);

    $response = $this->put("/templates/{$alert->id}/target-overlays", [
        'overlay_ids' => [$static1->id],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('alert_template_static_overlays', [
        'alert_template_id' => $alert->id,
        'static_overlay_id' => $static1->id,
    ]);
    $this->assertDatabaseMissing('alert_template_static_overlays', [
        'alert_template_id' => $alert->id,
        'static_overlay_id' => $static2->id,
    ]);
});

test('updateTargetOverlays clears selection when overlay_ids is empty', function () {
    [$user, $alert, $static1] = makeAlertAndStaticTemplates();
    // Pre-populate pivot
    $alert->targetStaticOverlays()->attach($static1->id);
    $this->actingAs($user);

    $response = $this->put("/templates/{$alert->id}/target-overlays", [
        'overlay_ids' => [],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseMissing('alert_template_static_overlays', [
        'alert_template_id' => $alert->id,
    ]);
});

test('updateTargetOverlays returns 403 when user does not own template', function () {
    [$user, $alert, $static1] = makeAlertAndStaticTemplates();
    $other = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $this->actingAs($other);

    $response = $this->put("/templates/{$alert->id}/target-overlays", [
        'overlay_ids' => [$static1->id],
    ]);

    $response->assertStatus(403);
});

test('updateTargetOverlays returns 422 when template is not type=alert', function () {
    [$user, $alert, $static1, $static2] = makeAlertAndStaticTemplates();
    $this->actingAs($user);

    // Try to target from the static overlay (wrong type)
    $response = $this->put("/templates/{$static1->id}/target-overlays", [
        'overlay_ids' => [],
    ]);

    $response->assertStatus(422);
});

test('updateTargetOverlays rejects IDs that are not static type', function () {
    [$user, $alert, $static1] = makeAlertAndStaticTemplates();

    // Create another alert template — should not be selectable as a "target"
    $anotherAlert = OverlayTemplate::factory()->create([
        'owner_id'   => $user->id,
        'fork_of_id' => null,
        'type'       => 'alert',
        'slug'       => 'other-alert-'.fake()->unique()->lexify('????????'),
    ]);
    $this->actingAs($user);

    $response = $this->put("/templates/{$alert->id}/target-overlays", [
        'overlay_ids' => [$anotherAlert->id],
    ]);

    $response->assertStatus(422);
});

test('updateTargetOverlays rejects IDs owned by another user', function () {
    [$user, $alert, $static1] = makeAlertAndStaticTemplates();
    $other = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $otherStatic = OverlayTemplate::factory()->create([
        'owner_id'   => $other->id,
        'fork_of_id' => null,
        'type'       => 'static',
        'slug'       => 'other-static-'.fake()->unique()->lexify('????????'),
    ]);
    $this->actingAs($user);

    $response = $this->put("/templates/{$alert->id}/target-overlays", [
        'overlay_ids' => [$otherStatic->id],
    ]);

    $response->assertStatus(422);
});

// ──────────────────────────────────────────────────────────────────────────────
// ExternalAlertService — target_overlay_slugs in broadcast
// ──────────────────────────────────────────────────────────────────────────────

test('ExternalAlertService dispatches AlertTriggered with target slugs when set', function () {
    Event::fake([AlertTriggered::class]);

    [$user, $alertTemplate, $static1, $static2] = makeAlertAndStaticTemplates();

    // Pre-attach static1 as a target
    $alertTemplate->targetStaticOverlays()->attach($static1->id);

    // Create external event + mapping
    $integration = ExternalIntegration::factory()->create([
        'user_id'     => $user->id,
        'service'     => 'kofi',
        'enabled'     => true,
        'credentials' => Crypt::encryptString(json_encode(['verification_token' => 'tok'])),
    ]);

    ExternalEventTemplateMapping::create([
        'user_id'             => $user->id,
        'service'             => 'kofi',
        'event_type'          => 'donation',
        'overlay_template_id' => $alertTemplate->id,
        'enabled'             => true,
        'duration_ms'         => 5000,
        'transition_in'       => 'fade',
        'transition_out'      => 'fade',
    ]);

    // Post a Ko-fi webhook to trigger the pipeline
    $payload = [
        'verification_token'            => 'tok',
        'kofi_transaction_id'           => 'txn-'.fake()->uuid(),
        'from_name'                     => 'Bob',
        'message'                       => 'Hi!',
        'amount'                        => '5.00',
        'currency'                      => 'USD',
        'type'                          => 'Donation',
        'is_subscription_payment'       => false,
        'is_first_subscription_payment' => false,
    ];

    $this->post("/api/webhooks/kofi/{$integration->webhook_token}", ['data' => json_encode($payload)]);

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $event) use ($static1) {
        return $event->targetOverlaySlugs === [$static1->slug];
    });
});

test('ExternalAlertService dispatches AlertTriggered with null target slugs when none set', function () {
    Event::fake([AlertTriggered::class]);

    [$user, $alertTemplate, $static1, $static2] = makeAlertAndStaticTemplates();
    // No targets attached

    $integration = ExternalIntegration::factory()->create([
        'user_id'     => $user->id,
        'service'     => 'kofi',
        'enabled'     => true,
        'credentials' => Crypt::encryptString(json_encode(['verification_token' => 'tok2'])),
    ]);

    ExternalEventTemplateMapping::create([
        'user_id'             => $user->id,
        'service'             => 'kofi',
        'event_type'          => 'donation',
        'overlay_template_id' => $alertTemplate->id,
        'enabled'             => true,
        'duration_ms'         => 5000,
        'transition_in'       => 'fade',
        'transition_out'      => 'fade',
    ]);

    $payload = [
        'verification_token'            => 'tok2',
        'kofi_transaction_id'           => 'txn-'.fake()->uuid(),
        'from_name'                     => 'Carol',
        'message'                       => '',
        'amount'                        => '3.00',
        'currency'                      => 'USD',
        'type'                          => 'Donation',
        'is_subscription_payment'       => false,
        'is_first_subscription_payment' => false,
    ];

    $this->post("/api/webhooks/kofi/{$integration->webhook_token}", ['data' => json_encode($payload)]);

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $event) {
        return $event->targetOverlaySlugs === null;
    });
});

// ──────────────────────────────────────────────────────────────────────────────
// ExternalEventController::replay
// ──────────────────────────────────────────────────────────────────────────────

test('external event replay includes target_overlay_slugs when configured', function () {
    Event::fake([AlertTriggered::class]);

    [$user, $alertTemplate, $static1] = makeAlertAndStaticTemplates();
    $alertTemplate->targetStaticOverlays()->attach($static1->id);

    ExternalEventTemplateMapping::create([
        'user_id'             => $user->id,
        'service'             => 'kofi',
        'event_type'          => 'donation',
        'overlay_template_id' => $alertTemplate->id,
        'enabled'             => true,
        'duration_ms'         => 5000,
        'transition_in'       => 'fade',
        'transition_out'      => 'fade',
    ]);

    $externalEvent = ExternalEvent::create([
        'user_id'            => $user->id,
        'service'            => 'kofi',
        'event_type'         => 'donation',
        'message_id'         => 'msg-'.fake()->uuid(),
        'raw_payload'        => ['from_name' => 'Dave'],
        'normalized_payload' => ['event.from_name' => 'Dave'],
    ]);

    $this->actingAs($user);

    $this->post("/external-events/{$externalEvent->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $event) use ($static1) {
        return $event->targetOverlaySlugs === [$static1->slug];
    });
});

test('external event replay has null target slugs when none configured', function () {
    Event::fake([AlertTriggered::class]);

    [$user, $alertTemplate, $static1] = makeAlertAndStaticTemplates();
    // No targets

    ExternalEventTemplateMapping::create([
        'user_id'             => $user->id,
        'service'             => 'kofi',
        'event_type'          => 'donation',
        'overlay_template_id' => $alertTemplate->id,
        'enabled'             => true,
        'duration_ms'         => 5000,
        'transition_in'       => 'fade',
        'transition_out'      => 'fade',
    ]);

    $externalEvent = ExternalEvent::create([
        'user_id'            => $user->id,
        'service'            => 'kofi',
        'event_type'         => 'donation',
        'message_id'         => 'msg-'.fake()->uuid(),
        'raw_payload'        => ['from_name' => 'Eve'],
        'normalized_payload' => ['event.from_name' => 'Eve'],
    ]);

    $this->actingAs($user);

    $this->post("/external-events/{$externalEvent->id}/replay");

    Event::assertDispatched(AlertTriggered::class, function (AlertTriggered $event) {
        return $event->targetOverlaySlugs === null;
    });
});
