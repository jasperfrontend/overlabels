<?php

use App\Events\ControlValueUpdated;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeKofiIntegration(string $verificationToken = 'test-token'): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    // Pass credentials pre-encrypted directly to the factory so they're persisted in one write.
    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['verification_token' => $verificationToken])),
    ]);

    return [$user, $integration];
}

function kofiPayload(array $overrides = []): array
{
    return array_merge([
        'verification_token' => 'test-token',
        'kofi_transaction_id' => 'txn-' . fake()->uuid(),
        'from_name' => 'Alice',
        'message' => 'Hello!',
        'amount' => '5.00',
        'currency' => 'USD',
        'type' => 'Donation',
        'is_subscription_payment' => false,
        'is_first_subscription_payment' => false,
    ], $overrides);
}

function postKofi(string $webhookToken, array $payload): \Illuminate\Testing\TestResponse
{
    // Ko-fi sends form-encoded body with a `data` JSON string field
    return test()->post(
        "/api/webhooks/kofi/{$webhookToken}",
        ['data' => json_encode($payload)],
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Service validation
// ──────────────────────────────────────────────────────────────────────────────

test('returns 404 for unknown service', function () {
    $this->postJson('/api/webhooks/unknown-service/some-token', [])
        ->assertStatus(404);
});

test('returns 404 when webhook_token does not match any integration', function () {
    $this->postJson('/api/webhooks/kofi/00000000-0000-0000-0000-000000000000', [])
        ->assertStatus(404);
});

// ──────────────────────────────────────────────────────────────────────────────
// Verification
// ──────────────────────────────────────────────────────────────────────────────

test('returns 403 when verification token does not match', function () {
    [, $integration] = makeKofiIntegration('correct-token');

    postKofi($integration->webhook_token, kofiPayload(['verification_token' => 'wrong-token']))
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// Happy path
// ──────────────────────────────────────────────────────────────────────────────

test('returns 200 and stores event for valid donation payload', function () {
    [$user, $integration] = makeKofiIntegration('test-token');

    $transactionId = 'txn-' . fake()->uuid();
    $payload = kofiPayload(['kofi_transaction_id' => $transactionId]);

    postKofi($integration->webhook_token, $payload)
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'message_id' => $transactionId,
    ]);
});

test('updates last_received_at on successful webhook', function () {
    [, $integration] = makeKofiIntegration();

    postKofi($integration->webhook_token, kofiPayload())->assertStatus(200);

    $integration->refresh();
    expect($integration->last_received_at)->not()->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Deduplication
// ──────────────────────────────────────────────────────────────────────────────

test('returns 409 for duplicate message_id', function () {
    [, $integration] = makeKofiIntegration();
    $payload = kofiPayload(['kofi_transaction_id' => 'dup-txn-001']);

    postKofi($integration->webhook_token, $payload)->assertStatus(200);
    postKofi($integration->webhook_token, $payload)->assertStatus(409);
});

// ──────────────────────────────────────────────────────────────────────────────
// Control updates
// ──────────────────────────────────────────────────────────────────────────────

test('increments kofis_received control on donation', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeKofiIntegration();

    // Pre-provision the controls
    OverlayControl::provisionServiceControl($user, 'kofi', [
        'key' => 'kofis_received', 'type' => 'counter', 'label' => 'Donations', 'value' => '0',
    ]);

    postKofi($integration->webhook_token, kofiPayload())->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'kofi',
        'key' => 'kofis_received',
        'value' => '1',
    ]);

    Event::assertDispatched(ControlValueUpdated::class);
});

test('ignored event type does not update controls', function () {
    Event::fake([ControlValueUpdated::class]);

    [, $integration] = makeKofiIntegration();

    postKofi($integration->webhook_token, kofiPayload(['type' => 'Commission']))->assertStatus(200);

    Event::assertNotDispatched(ControlValueUpdated::class);
});
