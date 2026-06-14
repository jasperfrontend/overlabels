<?php

use App\Events\ControlValuesBatchUpdated;
use App\Models\ExternalEvent;
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

const BMAC_SECRET = 'bmac-test-webhook-secret';

function makeBmacIntegration(string $secret = BMAC_SECRET, bool $testMode = false): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'bmac',
        'enabled' => true,
        'test_mode' => $testMode,
        'credentials' => Crypt::encryptString(json_encode(['webhook_secret' => $secret])),
    ]);

    return [$user, $integration];
}

function bmacDonationPayload(int $id = 58): array
{
    return [
        'type' => 'donation.created',
        'live_mode' => true,
        'attempt' => 1,
        'created' => 1777806464,
        'event_id' => 1,
        'data' => [
            'id' => $id,
            'amount' => 5,
            'object' => 'payment',
            'status' => 'succeeded',
            'message' => 'John bought you a coffee',
            'currency' => 'USD',
            'refunded' => 'false',
            'created_at' => 1676544557,
            'note_hidden' => 'true',
            'support_note' => 'Thanks!',
            'support_type' => 'Supporter',
            'supporter_name' => 'John',
            'transaction_id' => 'pi_3Mc51bJEtINljGAa0zVykgUE',
            'supporter_id' => 2345,
            'supporter_email' => 'john@example.com',
            'total_amount_charged' => '5.45',
            'coffee_count' => 1,
            'coffee_price' => 5,
        ],
    ];
}

function postBmac(string $webhookToken, array $payload, string $secret = BMAC_SECRET): TestResponse
{
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret);

    return test()->call(
        'POST',
        "/api/webhooks/bmac/{$webhookToken}",
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE_SHA256' => $signature,
        ],
        $body,
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Routing / verification
// ──────────────────────────────────────────────────────────────────────────────

test('returns 404 when webhook_token does not match any bmac integration', function () {
    test()->postJson('/api/webhooks/bmac/00000000-0000-0000-0000-000000000000')
        ->assertStatus(404);
});

test('returns 403 when HMAC signature is wrong', function () {
    [, $integration] = makeBmacIntegration('correct-secret');

    postBmac($integration->webhook_token, bmacDonationPayload(), 'wrong-secret')
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// Happy path: stores event, strips PII, persists email metadata
// ──────────────────────────────────────────────────────────────────────────────

test('stores event with PII stripped from raw_payload and email captured into private metadata', function () {
    [$user, $integration] = makeBmacIntegration();

    postBmac($integration->webhook_token, bmacDonationPayload(58))
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $event = ExternalEvent::where('service', 'bmac')->where('user_id', $user->id)->first();
    expect($event)->not()->toBeNull();
    expect($event->message_id)->toBe('bmac:donation.created:58');
    expect($event->event_type)->toBe('donation');

    // Raw payload must not leak email, address, or gross-charged total
    $raw = $event->raw_payload;
    expect($raw['data'])->not()->toHaveKey('supporter_email');
    expect($raw['data'])->not()->toHaveKey('total_amount_charged');
    expect($raw['data'])->not()->toHaveKey('shipping_address');

    // Backend-only metadata holds the plaintext email (encrypted at rest)
    expect($event->private_metadata)->toBe(['supporter_email' => 'john@example.com']);
    expect($event->supporter_email_hash)->toBe(hash('sha256', 'john@example.com'));
});

test('updates last_received_at on successful webhook', function () {
    [, $integration] = makeBmacIntegration();

    postBmac($integration->webhook_token, bmacDonationPayload())->assertStatus(200);

    $integration->refresh();
    expect($integration->last_received_at)->not()->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Deduplication
// ──────────────────────────────────────────────────────────────────────────────

test('returns duplicate status when the same donation event is delivered twice', function () {
    [, $integration] = makeBmacIntegration();

    $payload = bmacDonationPayload(101);

    postBmac($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'ok']);
    postBmac($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'duplicate']);
});

test('test mode allows the same payload to be re-fired without dedup', function () {
    [, $integration] = makeBmacIntegration(BMAC_SECRET, true);

    $payload = bmacDonationPayload(202);

    postBmac($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'ok']);
    postBmac($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'ok']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Refund / unsupported event types are ignored, never stored
// ──────────────────────────────────────────────────────────────────────────────

test('refund event types are ignored without storing an external_events row', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [$user, $integration] = makeBmacIntegration();

    $payload = bmacDonationPayload();
    $payload['type'] = 'donation.refunded';

    postBmac($integration->webhook_token, $payload)
        ->assertStatus(200)
        ->assertJson(['status' => 'ignored']);

    expect(ExternalEvent::where('user_id', $user->id)->count())->toBe(0);
    Event::assertNotDispatched(ControlValuesBatchUpdated::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Control updates: every supported event bumps donations_received
// ──────────────────────────────────────────────────────────────────────────────

test('increments donations_received and broadcasts the update', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [$user, $integration] = makeBmacIntegration();

    OverlayControl::provisionServiceControl($user, 'bmac', [
        'key' => 'donations_received', 'type' => 'counter', 'label' => 'BMAC Donations', 'value' => '0',
    ]);

    postBmac($integration->webhook_token, bmacDonationPayload())->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'bmac',
        'key' => 'donations_received',
        'value' => '1',
    ]);

    Event::assertDispatched(ControlValuesBatchUpdated::class);
});

test('membership.started increments donations_received just like a donation', function () {
    Event::fake([ControlValuesBatchUpdated::class]);

    [$user, $integration] = makeBmacIntegration();

    OverlayControl::provisionServiceControl($user, 'bmac', [
        'key' => 'donations_received', 'type' => 'counter', 'label' => 'BMAC Donations', 'value' => '0',
    ]);

    $payload = [
        'type' => 'membership.started',
        'live_mode' => true,
        'data' => [
            'id' => 16,
            'amount' => 1,
            'currency' => 'USD',
            'support_note' => 'thanks',
            'note_hidden' => true,
            'membership_level_name' => 'Basic',
            'supporter_name' => 'John',
            'psp_id' => 'sub_001',
            'supporter_email' => 'john@example.com',
        ],
    ];

    postBmac($integration->webhook_token, $payload)->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'bmac',
        'key' => 'donations_received',
        'value' => '1',
    ]);
});

test('commission_order.created strips shipping_address from stored raw_payload', function () {
    [$user, $integration] = makeBmacIntegration();

    $payload = [
        'type' => 'commission_order.created',
        'data' => [
            'id' => 63,
            'amount' => 1050,
            'currency' => 'USD',
            'message' => 'commission',
            'supporter_name' => 'John',
            'transaction_id' => 'pi_x',
            'supporter_email' => 'john@example.com',
            'total_amount_charged' => '1080.75',
            'commission' => [
                'name' => 'Illustration & Sketch',
                'shipping_address' => [
                    'zip' => '14624',
                    'city' => 'Rochester',
                    'name' => 'John Doe',
                ],
            ],
        ],
    ];

    postBmac($integration->webhook_token, $payload)->assertStatus(200);

    $event = ExternalEvent::where('user_id', $user->id)->first();
    $raw = $event->raw_payload;

    expect($raw['data'])->not()->toHaveKey('supporter_email');
    expect($raw['data'])->not()->toHaveKey('total_amount_charged');
    expect($raw['data']['commission'])->not()->toHaveKey('shipping_address');
    expect($raw['data']['commission']['name'])->toBe('Illustration & Sketch');
});
