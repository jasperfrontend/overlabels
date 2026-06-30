<?php

use App\Events\ControlValuesBatchUpdated;
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
    Event::fake([ControlValuesBatchUpdated::class]);

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

    Event::assertDispatched(ControlValuesBatchUpdated::class);
});

test('ignored event type does not update controls', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [, $integration] = makeKofiIntegration();

    postKofi($integration->webhook_token, kofiPayload(['type' => 'Commission']))->assertStatus(200);

    Event::assertNotDispatched(ControlValuesBatchUpdated::class);
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
    Event::fake([ControlValuesBatchUpdated::class]);

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

    Event::assertDispatched(ControlValuesBatchUpdated::class);
});

test('fourthwall webhook ignores unsupported event types without updating controls', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [, $integration] = makeFourthwallIntegration();

    $payload = fourthwallSamplePayload();
    $payload['type'] = 'ORDER_PLACED';

    postFourthwall($integration->webhook_token, $payload)
        ->assertStatus(200)
        ->assertJson(['status' => 'ignored']);

    Event::assertNotDispatched(ControlValuesBatchUpdated::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Throne - Ed25519-signed JSON. Signature rides in headers over "{ts}.{rawBody}",
// verified against Throne's single GLOBAL public key (config, not per-integration).
// ──────────────────────────────────────────────────────────────────────────────

// Wrap a raw 32-byte Ed25519 public key in the same SubjectPublicKeyInfo PEM that
// Throne publishes, so the driver's "strip armor, take the last 32 bytes" extraction
// is exercised exactly as it is in production.
function throneSpkiPem(string $rawPublicKey): string
{
    $der = hex2bin('302a300506032b6570032100').$rawPublicKey;

    return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----';
}

// Generate a throwaway keypair, pin its public key as Throne's global key, and
// return the secret key so the test can sign arbitrary payloads with it.
function makeThroneIntegration(): array
{
    $keypair = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($keypair);
    config(['services.throne.public_key' => throneSpkiPem(sodium_crypto_sign_publickey($keypair))]);

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'throne',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode([])),
    ]);

    return [$user, $integration, $secret];
}

function thronePayload(array $dataOverrides = [], string $eventType = 'gift_purchased', ?string $eventId = null): array
{
    return [
        'contract_version' => '1',
        'event_id' => $eventId ?? fake()->uuid(),
        'event_type' => $eventType,
        'data' => array_merge([
            'creator_id' => 'creator_x',
            'creator_username' => 'jasperdiscovers',
            'gifter_username' => 'marie_123',
            'message' => 'Keep doing what you are doing!',
            'item_name' => 'AirPods Max',
            'item_thumbnail_url' => 'https://example.com/airpods.jpg',
            'is_surprise_gift' => false,
            'price' => 10000,
            'currency' => 'USD',
        ], $dataOverrides),
    ];
}

// Signs "{ts}.{body}" with the given secret key, matching Throne's scheme. A caller
// may pass an explicit $timestamp/$signatureHex to simulate tampering/replay.
function postThrone(string $webhookToken, array $payload, string $secretKey, ?string $timestamp = null, ?string $signatureHex = null): TestResponse
{
    $body = json_encode($payload);
    $ts = $timestamp ?? (string) time();
    $sig = $signatureHex ?? bin2hex(sodium_crypto_sign_detached($ts.'.'.$body, $secretKey));

    return test()->call('POST', "/api/webhooks/throne/{$webhookToken}", [], [], [], [
        'CONTENT_TYPE' => 'application/json; charset=utf-8',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE_TIMESTAMP' => $ts,
        'HTTP_X_SIGNATURE_ED25519' => $sig,
    ], $body);
}

// --- Verification --------------------------------------------------------------

test('throne verifies a real captured request against the pinned production public key', function () {
    // No config override here: this exercises the real key shipped in config/services.php
    // against a genuine Throne "Test webhook" delivery (body + headers captured verbatim).
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'throne',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode([])),
    ]);

    $body = '{"contract_version":"1","event_id":"655c3194-59c7-41a6-99c5-537c5c865e79","event_type":"gift_purchased","data":{"creator_id":"mhC7x9dmBoQ4Wk8OvfaZGH31wxP2","creator_username":"jasperdiscovers","gifter_username":"marie_123","message":"Keep doing what you\'re doing!","item_name":"AirPods Max","item_thumbnail_url":"https://m.media-amazon.com/images/I/81jqUPkIVRL._AC_SX522_.jpg","is_surprise_gift":false,"price":10000,"currency":"USD"}}';

    test()->call('POST', "/api/webhooks/throne/{$integration->webhook_token}", [], [], [], [
        'CONTENT_TYPE' => 'application/json; charset=utf-8',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE_TIMESTAMP' => '1782855097',
        'HTTP_X_SIGNATURE_ED25519' => '78df2cfbcb52df12d2a98b2a0aad2b87e3ee45f7e2495bb4d22c37dcb789228f8d06676cda608ff9127d60eb944dcdca1cacff570e74a72447609880cd4e9e09',
    ], $body)
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'throne',
        'event_type' => 'donation',
        'message_id' => '655c3194-59c7-41a6-99c5-537c5c865e79',
    ]);
});

test('throne returns 200 for a valid signed gift_purchased', function () {
    [$user, $integration, $secret] = makeThroneIntegration();

    $eventId = fake()->uuid();

    postThrone($integration->webhook_token, thronePayload(eventId: $eventId), $secret)
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'throne',
        'event_type' => 'donation',
        'message_id' => $eventId,
    ]);
});

test('throne returns 403 when signed by the wrong key', function () {
    [, $integration] = makeThroneIntegration();

    $wrongSecret = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());

    postThrone($integration->webhook_token, thronePayload(), $wrongSecret)->assertStatus(403);
});

test('throne returns 403 when the body is tampered after signing', function () {
    [, $integration, $secret] = makeThroneIntegration();

    $ts = (string) time();
    $body = json_encode(thronePayload());
    $sig = bin2hex(sodium_crypto_sign_detached($ts.'.'.$body, $secret));

    // Same signature/timestamp, different body.
    postThrone($integration->webhook_token, thronePayload(['gifter_username' => 'mallory_99']), $secret, $ts, $sig)
        ->assertStatus(403);
});

test('throne returns 403 when the timestamp is missing or non-numeric', function () {
    [, $integration, $secret] = makeThroneIntegration();

    $body = json_encode(thronePayload());

    // Non-numeric timestamp (also makes the signed message mismatch).
    test()->call('POST', "/api/webhooks/throne/{$integration->webhook_token}", [], [], [], [
        'CONTENT_TYPE' => 'application/json; charset=utf-8',
        'HTTP_X_SIGNATURE_TIMESTAMP' => 'not-a-number',
        'HTTP_X_SIGNATURE_ED25519' => bin2hex(sodium_crypto_sign_detached('not-a-number.'.$body, $secret)),
    ], $body)->assertStatus(403);

    // Missing timestamp header entirely.
    test()->call('POST', "/api/webhooks/throne/{$integration->webhook_token}", [], [], [], [
        'CONTENT_TYPE' => 'application/json; charset=utf-8',
        'HTTP_X_SIGNATURE_ED25519' => str_repeat('ab', 64),
    ], $body)->assertStatus(403);
});

test('throne returns 403 for a malformed signature header', function () {
    [, $integration] = makeThroneIntegration();

    $body = json_encode(thronePayload());

    // Too short / not 128 hex chars.
    test()->call('POST', "/api/webhooks/throne/{$integration->webhook_token}", [], [], [], [
        'CONTENT_TYPE' => 'application/json; charset=utf-8',
        'HTTP_X_SIGNATURE_TIMESTAMP' => (string) time(),
        'HTTP_X_SIGNATURE_ED25519' => 'deadbeef',
    ], $body)->assertStatus(403);
});

// --- Dedup / bookkeeping -------------------------------------------------------

test('throne deduplicates on event_id across retries', function () {
    [, $integration, $secret] = makeThroneIntegration();

    $payload = thronePayload(eventId: 'evt-dup-001');

    postThrone($integration->webhook_token, $payload, $secret)->assertStatus(200)->assertJson(['status' => 'ok']);
    postThrone($integration->webhook_token, $payload, $secret)->assertStatus(200)->assertJson(['status' => 'duplicate']);
});

test('throne updates last_received_at on success', function () {
    [, $integration, $secret] = makeThroneIntegration();

    postThrone($integration->webhook_token, thronePayload(), $secret)->assertStatus(200);

    $integration->refresh();
    expect($integration->last_received_at)->not()->toBeNull();
});

// --- Control updates -----------------------------------------------------------

test('throne increments donations_received and sets the item name', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [$user, $integration, $secret] = makeThroneIntegration();

    OverlayControl::provisionServiceControl($user, 'throne', [
        'key' => 'donations_received', 'type' => 'counter', 'label' => 'Gifts', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'throne', [
        'key' => 'latest_item_name', 'type' => 'text', 'label' => 'Latest Item', 'value' => '',
    ]);

    postThrone($integration->webhook_token, thronePayload(), $secret)->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id, 'source' => 'throne', 'key' => 'donations_received', 'value' => '1',
    ]);
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id, 'source' => 'throne', 'key' => 'latest_item_name', 'value' => 'AirPods Max',
    ]);

    Event::assertDispatched(ControlValuesBatchUpdated::class);
});

test('throne gift_crowdfunded bumps the counter but preserves the latest donor', function () {
    [$user, $integration, $secret] = makeThroneIntegration();

    OverlayControl::provisionServiceControl($user, 'throne', [
        'key' => 'donations_received', 'type' => 'counter', 'label' => 'Gifts', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'throne', [
        'key' => 'latest_donor_name', 'type' => 'text', 'label' => 'Latest Donor', 'value' => 'marie_123',
    ]);

    // A crowdfunded completion carries no gifter and no message.
    $payload = thronePayload(eventType: 'gift_crowdfunded');
    unset($payload['data']['gifter_username'], $payload['data']['message']);

    postThrone($integration->webhook_token, $payload, $secret)->assertStatus(200);

    // Counter advanced...
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id, 'source' => 'throne', 'key' => 'donations_received', 'value' => '1',
    ]);
    // ...but the last real gifter was NOT blanked.
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id, 'source' => 'throne', 'key' => 'latest_donor_name', 'value' => 'marie_123',
    ]);
});

test('throne contribution_purchased reads the amount field, not price', function () {
    [$user, $integration, $secret] = makeThroneIntegration();

    OverlayControl::provisionServiceControl($user, 'throne', [
        'key' => 'latest_donation_amount', 'type' => 'number', 'label' => 'Latest Amount', 'value' => '0',
    ]);

    $payload = thronePayload(eventType: 'contribution_purchased');
    unset($payload['data']['price']);
    $payload['data']['amount'] = 2500;

    postThrone($integration->webhook_token, $payload, $secret)->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id, 'source' => 'throne', 'key' => 'latest_donation_amount', 'value' => '25.00',
    ]);
});

test('throne ignores unsupported event types without storing an event', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [, $integration, $secret] = makeThroneIntegration();

    postThrone($integration->webhook_token, thronePayload(eventType: 'something_else'), $secret)
        ->assertStatus(200)
        ->assertJson(['status' => 'ignored']);

    Event::assertNotDispatched(ControlValuesBatchUpdated::class);
});
