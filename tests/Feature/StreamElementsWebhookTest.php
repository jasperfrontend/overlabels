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

function makeStreamElementsIntegrationForWebhook(string $listenerSecret = 'test-secret'): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'streamelements',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode([
            'jwt_token' => 'fake.jwt.token',
            'listener_secret' => $listenerSecret,
        ])),
    ]);

    return [$user, $integration];
}

function streamElementsTipPayload(array $overrides = []): array
{
    $dataOverrides = $overrides['data_overrides'] ?? [];
    unset($overrides['data_overrides']);

    return array_merge([
        '_id' => fake()->md5(),
        'channel' => fake()->md5(),
        'type' => 'tip',
        'provider' => 'paypal',
        'flagged' => false,
        'data' => array_merge([
            'username' => 'TestTipper',
            'displayName' => 'TestTipper',
            'amount' => 5.00,
            'message' => 'Hello!',
            'currency' => 'USD',
            'tipId' => fake()->md5(),
        ], $dataOverrides),
    ], $overrides);
}

function postStreamElements(string $webhookToken, array $payload, string $secret = 'test-secret'): TestResponse
{
    return test()->postJson(
        "/api/webhooks/streamelements/{$webhookToken}",
        $payload,
        ['X-Listener-Secret' => $secret],
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Service validation
// ──────────────────────────────────────────────────────────────────────────────

test('returns 404 when webhook_token does not match any streamelements integration', function () {
    postStreamElements('00000000-0000-0000-0000-000000000000', streamElementsTipPayload())
        ->assertStatus(404);
});

// ──────────────────────────────────────────────────────────────────────────────
// Verification
// ──────────────────────────────────────────────────────────────────────────────

test('returns 403 when listener secret does not match', function () {
    [, $integration] = makeStreamElementsIntegrationForWebhook('correct-secret');

    postStreamElements($integration->webhook_token, streamElementsTipPayload(), 'wrong-secret')
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// Happy path
// ──────────────────────────────────────────────────────────────────────────────

test('returns 200 and stores event for valid tip payload', function () {
    [$user, $integration] = makeStreamElementsIntegrationForWebhook();

    $eventId = fake()->md5();
    $payload = streamElementsTipPayload(['_id' => $eventId]);

    postStreamElements($integration->webhook_token, $payload)
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'streamelements',
        'event_type' => 'tip',
        'message_id' => $eventId,
    ]);
});

test('updates last_received_at on successful webhook', function () {
    [, $integration] = makeStreamElementsIntegrationForWebhook();

    postStreamElements($integration->webhook_token, streamElementsTipPayload())
        ->assertStatus(200);

    $integration->refresh();
    expect($integration->last_received_at)->not()->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Deduplication
// ──────────────────────────────────────────────────────────────────────────────

test('returns duplicate status for duplicate _id', function () {
    [, $integration] = makeStreamElementsIntegrationForWebhook();
    $payload = streamElementsTipPayload(['_id' => 'tip_duplicate_001']);

    postStreamElements($integration->webhook_token, $payload)->assertJson(['status' => 'ok']);
    postStreamElements($integration->webhook_token, $payload)->assertJson(['status' => 'duplicate']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Control updates
// ──────────────────────────────────────────────────────────────────────────────

test('increments tips_received control on tip', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeStreamElementsIntegrationForWebhook();

    OverlayControl::provisionServiceControl($user, 'streamelements', [
        'key' => 'tips_received', 'type' => 'counter', 'label' => 'Tips', 'value' => '0',
    ]);

    postStreamElements($integration->webhook_token, streamElementsTipPayload())
        ->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'streamelements',
        'key' => 'tips_received',
        'value' => '1',
    ]);

    Event::assertDispatched(ControlValueUpdated::class);
});

test('sets latest_tipper_name on tip', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeStreamElementsIntegrationForWebhook();

    OverlayControl::provisionServiceControl($user, 'streamelements', [
        'key' => 'latest_tipper_name', 'type' => 'text', 'label' => 'Latest Tipper', 'value' => '',
    ]);

    $payload = streamElementsTipPayload(['data_overrides' => ['displayName' => 'GenerousTipper']]);
    postStreamElements($integration->webhook_token, $payload)->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'streamelements',
        'key' => 'latest_tipper_name',
        'value' => 'GenerousTipper',
    ]);
});

test('accumulates total_tips_received across multiple tips', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeStreamElementsIntegrationForWebhook();

    OverlayControl::provisionServiceControl($user, 'streamelements', [
        'key' => 'total_tips_received', 'type' => 'number', 'label' => 'Total', 'value' => '0',
    ]);

    postStreamElements($integration->webhook_token, streamElementsTipPayload([
        'data_overrides' => ['amount' => 10.00],
    ]))->assertStatus(200);

    postStreamElements($integration->webhook_token, streamElementsTipPayload([
        'data_overrides' => ['amount' => 5.50],
    ]))->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'streamelements',
        'key' => 'total_tips_received',
        'value' => '15.5',
    ]);
});

test('non-tip event type is ignored', function () {
    Event::fake([ControlValueUpdated::class]);

    [, $integration] = makeStreamElementsIntegrationForWebhook();

    postStreamElements($integration->webhook_token, ['type' => 'follow', 'data' => ['displayName' => 'Someone']])
        ->assertStatus(200)
        ->assertJson(['status' => 'ignored']);

    Event::assertNotDispatched(ControlValueUpdated::class);
});
