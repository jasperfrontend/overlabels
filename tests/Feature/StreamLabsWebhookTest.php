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

function makeStreamLabsIntegration(string $listenerSecret = 'test-secret'): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'streamlabs',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode([
            'access_token' => 'fake-access-token',
            'socket_token' => 'fake-socket-token',
            'listener_secret' => $listenerSecret,
        ])),
    ]);

    return [$user, $integration];
}

function streamLabsDonationPayload(array $overrides = []): array
{
    $messageOverrides = $overrides['message_overrides'] ?? [];
    unset($overrides['message_overrides']);

    return array_merge([
        'type' => 'donation',
        'event_id' => 'evt_'.fake()->uuid(),
        'message' => [
            array_merge([
                'id' => fake()->randomNumber(8),
                'name' => 'TestDonor',
                'amount' => '5.00',
                'formatted_amount' => '$5.00',
                'message' => 'Hello!',
                'currency' => 'USD',
                'from' => 'TestDonor',
                '_id' => fake()->md5(),
            ], $messageOverrides),
        ],
    ], $overrides);
}

function postStreamLabs(string $webhookToken, array $payload, string $secret = 'test-secret'): \Illuminate\Testing\TestResponse
{
    return test()->postJson(
        "/api/webhooks/streamlabs/{$webhookToken}",
        $payload,
        ['X-Listener-Secret' => $secret],
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Service validation
// ──────────────────────────────────────────────────────────────────────────────

test('returns 404 when webhook_token does not match any streamlabs integration', function () {
    postStreamLabs('00000000-0000-0000-0000-000000000000', streamLabsDonationPayload())
        ->assertStatus(404);
});

// ──────────────────────────────────────────────────────────────────────────────
// Verification
// ──────────────────────────────────────────────────────────────────────────────

test('returns 403 when listener secret does not match', function () {
    [, $integration] = makeStreamLabsIntegration('correct-secret');

    postStreamLabs($integration->webhook_token, streamLabsDonationPayload(), 'wrong-secret')
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// Happy path
// ──────────────────────────────────────────────────────────────────────────────

test('returns 200 and stores event for valid donation payload', function () {
    [$user, $integration] = makeStreamLabsIntegration();

    $eventId = 'evt_'.fake()->uuid();
    $payload = streamLabsDonationPayload(['event_id' => $eventId]);

    postStreamLabs($integration->webhook_token, $payload)
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'streamlabs',
        'event_type' => 'donation',
        'message_id' => $eventId,
    ]);
});

test('updates last_received_at on successful webhook', function () {
    [, $integration] = makeStreamLabsIntegration();

    postStreamLabs($integration->webhook_token, streamLabsDonationPayload())
        ->assertStatus(200);

    $integration->refresh();
    expect($integration->last_received_at)->not()->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Deduplication
// ──────────────────────────────────────────────────────────────────────────────

test('returns 200 with duplicate status for duplicate event_id', function () {
    [, $integration] = makeStreamLabsIntegration();
    $payload = streamLabsDonationPayload(['event_id' => 'evt_duplicate_001']);

    postStreamLabs($integration->webhook_token, $payload)->assertJson(['status' => 'ok']);
    postStreamLabs($integration->webhook_token, $payload)->assertJson(['status' => 'duplicate']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Control updates
// ──────────────────────────────────────────────────────────────────────────────

test('increments donations_received control on donation', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeStreamLabsIntegration();

    OverlayControl::provisionServiceControl($user, 'streamlabs', [
        'key' => 'donations_received', 'type' => 'counter', 'label' => 'Donations', 'value' => '0',
    ]);

    postStreamLabs($integration->webhook_token, streamLabsDonationPayload())
        ->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'streamlabs',
        'key' => 'donations_received',
        'value' => '1',
    ]);

    Event::assertDispatched(ControlValueUpdated::class);
});

test('sets latest_donor_name on donation', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeStreamLabsIntegration();

    OverlayControl::provisionServiceControl($user, 'streamlabs', [
        'key' => 'latest_donor_name', 'type' => 'text', 'label' => 'Latest Donor', 'value' => '',
    ]);

    $payload = streamLabsDonationPayload(['message_overrides' => ['from' => 'GenerousDonor']]);
    postStreamLabs($integration->webhook_token, $payload)->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'streamlabs',
        'key' => 'latest_donor_name',
        'value' => 'GenerousDonor',
    ]);
});

test('accumulates total_received across multiple donations', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeStreamLabsIntegration();

    OverlayControl::provisionServiceControl($user, 'streamlabs', [
        'key' => 'total_received', 'type' => 'number', 'label' => 'Total', 'value' => '0',
    ]);

    postStreamLabs($integration->webhook_token, streamLabsDonationPayload([
        'message_overrides' => ['amount' => '10.00'],
    ]))->assertStatus(200);

    postStreamLabs($integration->webhook_token, streamLabsDonationPayload([
        'message_overrides' => ['amount' => '5.50'],
    ]))->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'streamlabs',
        'key' => 'total_received',
        'value' => '15.5',
    ]);
});

test('non-donation event type is ignored', function () {
    Event::fake([ControlValueUpdated::class]);

    [, $integration] = makeStreamLabsIntegration();

    postStreamLabs($integration->webhook_token, ['type' => 'follow', 'message' => [['name' => 'Someone']]])
        ->assertStatus(200)
        ->assertJson(['status' => 'ignored']);

    Event::assertNotDispatched(ControlValueUpdated::class);
});
