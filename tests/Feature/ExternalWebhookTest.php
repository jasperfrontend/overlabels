<?php

use App\Events\ControlValueUpdated;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

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
        'kofi_transaction_id' => 'txn-'.fake()->uuid(),
        'from_name' => 'Alice',
        'message' => 'Hello!',
        'amount' => '5.00',
        'currency' => 'USD',
        'type' => 'Donation',
        'is_subscription_payment' => false,
        'is_first_subscription_payment' => false,
    ], $overrides);
}

function postKofi(string $webhookToken, array $payload): TestResponse
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
    $this->postJson('/api/webhooks/unknown-service/some-token')
        ->assertStatus(404);
});

test('returns 404 when webhook_token does not match any integration', function () {
    $this->postJson('/api/webhooks/kofi/00000000-0000-0000-0000-000000000000')
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
    [$user, $integration] = makeKofiIntegration();

    $transactionId = 'txn-'.fake()->uuid();
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

test('returns 200 with duplicate status for duplicate message_id', function () {
    [, $integration] = makeKofiIntegration();
    $payload = kofiPayload(['kofi_transaction_id' => 'dup-txn-001']);

    postKofi($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'ok']);
    postKofi($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'duplicate']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Control updates
// ──────────────────────────────────────────────────────────────────────────────

test('increments donations_received control on donation', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeKofiIntegration();

    // Pre-provision the controls
    OverlayControl::provisionServiceControl($user, 'kofi', [
        'key' => 'donations_received', 'type' => 'counter', 'label' => 'Donations', 'value' => '0',
    ]);

    postKofi($integration->webhook_token, kofiPayload())->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'kofi',
        'key' => 'donations_received',
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

// ──────────────────────────────────────────────────────────────────────────────
// Fourthwall - HMAC-signed JSON, different verification model from Ko-fi
// ──────────────────────────────────────────────────────────────────────────────

function makeFourthwallIntegration(string $appHmac = 'fw-app-hmac-secret'): array
{
    config(['services.fourthwall.hmac' => $appHmac]);

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'fourthwall',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode([
            'access_token' => 'fw-access',
            'refresh_token' => 'fw-refresh',
            'expires_at' => now()->addHour()->toIso8601String(),
            'webhook_id' => 'wh_abc',
        ])),
    ]);

    return [$user, $integration];
}

function fourthwallSamplePayload(string $donationId = 'don_Kpcjx4HIQ1e4bTIOjX9CsA'): array
{
    return [
        'id' => '00aa4abd-5778-4199-8161-0b49b2f212e5',
        'webhookId' => '00aa4abd-5778-4199-8161-0b49b2f212e5',
        'shopId' => 'sh_test',
        'type' => 'DONATION',
        'apiVersion' => 'V1',
        'createdAt' => '2020-08-13T09:05:36.939+00:00',
        'testMode' => false,
        'data' => [
            'id' => $donationId,
            'shopId' => 'sh_test',
            'status' => 'OPEN',
            'email' => 'supporter@fourthwall.com',
            'amounts' => ['total' => ['value' => 10, 'currency' => 'USD']],
            'createdAt' => '2020-08-13T09:05:36.939Z',
            'updatedAt' => '2020-08-13T09:05:36.939Z',
            'username' => 'Johnny123',
            'message' => 'Sample message',
        ],
    ];
}

function postFourthwall(string $webhookToken, array $payload, string $secret = 'fw-app-hmac-secret'): TestResponse
{
    $body = json_encode($payload);
    $signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

    return test()->call(
        'POST',
        "/api/webhooks/fourthwall/{$webhookToken}",
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_FOURTHWALL_HMAC_APPS_SHA256' => $signature,
        ],
        $body,
    );
}

test('fourthwall webhook returns 200 and stores event on valid HMAC', function () {
    [$user, $integration] = makeFourthwallIntegration();

    $donationId = 'don_'.fake()->uuid();
    $payload = fourthwallSamplePayload($donationId);

    postFourthwall($integration->webhook_token, $payload)
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'fourthwall',
        'event_type' => 'donation',
        'message_id' => $donationId,
    ]);
});

test('fourthwall webhook returns 403 when HMAC signature is wrong', function () {
    [, $integration] = makeFourthwallIntegration('real-secret');

    // Sign with the wrong secret
    postFourthwall($integration->webhook_token, fourthwallSamplePayload(), 'wrong-secret')
        ->assertStatus(403);
});

test('fourthwall webhook deduplicates on data.id across retries', function () {
    [, $integration] = makeFourthwallIntegration();
    $payload = fourthwallSamplePayload('don_dup_001');

    postFourthwall($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'ok']);
    postFourthwall($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'duplicate']);
});

test('fourthwall webhook increments donations_received control', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeFourthwallIntegration();

    OverlayControl::provisionServiceControl($user, 'fourthwall', [
        'key' => 'donations_received', 'type' => 'counter', 'label' => 'Donations', 'value' => '0',
    ]);

    postFourthwall($integration->webhook_token, fourthwallSamplePayload())->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'fourthwall',
        'key' => 'donations_received',
        'value' => '1',
    ]);

    Event::assertDispatched(ControlValueUpdated::class);
});

test('fourthwall webhook ignores unsupported event types without updating controls', function () {
    Event::fake([ControlValueUpdated::class]);

    [, $integration] = makeFourthwallIntegration();

    $payload = fourthwallSamplePayload();
    $payload['type'] = 'ORDER_PLACED';

    postFourthwall($integration->webhook_token, $payload)
        ->assertStatus(200)
        ->assertJson(['status' => 'ignored']);

    Event::assertNotDispatched(ControlValueUpdated::class);
});
