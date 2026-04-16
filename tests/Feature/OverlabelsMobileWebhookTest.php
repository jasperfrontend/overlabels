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

function makeMobileIntegration(string $token = 'test-mobile-token', array $settings = []): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'overlabels-mobile',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['token' => $token])),
        'settings' => $settings,
    ]);

    return [$user, $integration];
}

function mobilePayload(array $overrides = []): array
{
    return array_merge([
        'latitude' => 52.3676,
        'longitude' => 4.9041,
        'speed' => 13.89, // ~50 km/h
        'altitude' => 10.5,
        'accuracy' => 5.0,
        'timestamp' => time(),
        'serial' => '1',
    ], $overrides);
}

function postMobile(string $webhookToken, array $payload, string $token = 'test-mobile-token'): TestResponse
{
    return test()->postJson(
        "/api/webhooks/overlabels-mobile/{$webhookToken}",
        $payload,
        ['X-GPSLogger-Token' => $token],
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Verification
// ──────────────────────────────────────────────────────────────────────────────

test('returns 403 when token does not match', function () {
    [, $integration] = makeMobileIntegration('correct-token');

    postMobile($integration->webhook_token, mobilePayload(), 'wrong-token')
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// Happy path
// ──────────────────────────────────────────────────────────────────────────────

test('returns 200 and stores event for valid GPS payload', function () {
    [$user, $integration] = makeMobileIntegration();
    $ts = time();

    postMobile($integration->webhook_token, mobilePayload(['timestamp' => $ts, 'serial' => '1']))
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'overlabels-mobile',
        'event_type' => 'location_update',
        'message_id' => "gps_{$ts}_1",
    ]);
});

test('updates last_received_at on successful webhook', function () {
    [, $integration] = makeMobileIntegration();

    postMobile($integration->webhook_token, mobilePayload())->assertStatus(200);

    $integration->refresh();
    expect($integration->last_received_at)->not()->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Deduplication
// ──────────────────────────────────────────────────────────────────────────────

test('returns 200 with duplicate status for duplicate timestamp+serial', function () {
    [, $integration] = makeMobileIntegration();
    $payload = mobilePayload(['timestamp' => 1234567890, 'serial' => '42']);

    postMobile($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'ok']);
    postMobile($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'duplicate']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Control updates
// ──────────────────────────────────────────────────────────────────────────────

test('updates speed, lat, lng controls on location update', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration();

    // Pre-provision controls
    foreach (['gps_speed', 'gps_lat', 'gps_lng', 'gps_distance'] as $key) {
        OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
            'key' => $key,
            'type' => in_array($key, ['gps_speed', 'gps_distance']) ? 'number' : 'text',
            'label' => $key,
            'value' => '0',
        ]);
    }

    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 52.3676,
        'longitude' => 4.9041,
        'speed' => 13.89,
    ]))->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'overlabels-mobile',
        'key' => 'gps_lat',
        'value' => '52.3676',
    ]);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'overlabels-mobile',
        'key' => 'gps_lng',
        'value' => '4.9041',
    ]);

    // Speed: 13.89 m/s * 3.6 = 50.0 km/h
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'overlabels-mobile',
        'key' => 'gps_speed',
        'value' => '50',
    ]);

    Event::assertDispatched(ControlValueUpdated::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Distance accumulation
// ──────────────────────────────────────────────────────────────────────────────

test('first ping stores position without adding distance', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration();

    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
        'key' => 'gps_distance', 'type' => 'number', 'label' => 'Distance', 'value' => '0',
    ]);

    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 52.3676,
        'longitude' => 4.9041,
        'timestamp' => time(),
        'serial' => '1',
    ]))->assertStatus(200);

    // Distance should remain 0 (no previous position)
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'overlabels-mobile',
        'key' => 'gps_distance',
        'value' => '0',
    ]);

    // But last_lat/last_lng should be stored
    $integration->refresh();
    expect((float) $integration->settings['last_lat'])->toBe(52.3676);
    expect((float) $integration->settings['last_lng'])->toBe(4.9041);
});

test('second ping accumulates haversine distance', function () {
    Event::fake([ControlValueUpdated::class]);

    // Amsterdam (start)
    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
    ]);

    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
        'key' => 'gps_distance', 'type' => 'number', 'label' => 'Distance', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
        'key' => 'gps_speed', 'type' => 'number', 'label' => 'Speed', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
        'key' => 'gps_lat', 'type' => 'text', 'label' => 'Lat', 'value' => '',
    ]);
    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
        'key' => 'gps_lng', 'type' => 'text', 'label' => 'Lng', 'value' => '',
    ]);

    // Move to a point ~1.1 km away (Rotterdam direction)
    postMobile($integration->webhook_token, mobilePayload([
        'latitude' => 52.3776,
        'longitude' => 4.9041,
        'timestamp' => time(),
        'serial' => '2',
    ]))->assertStatus(200);

    $distanceControl = OverlayControl::where('user_id', $user->id)
        ->where('source', 'overlabels-mobile')
        ->where('key', 'gps_distance')
        ->first();

    // ~1.11 km for 0.01 degree latitude change at this position
    expect((float) $distanceControl->value)->toBeGreaterThan(0.5);
    expect((float) $distanceControl->value)->toBeLessThan(2.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Speed unit conversion
// ──────────────────────────────────────────────────────────────────────────────

test('converts speed to mph when speed_unit is mph', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'speed_unit' => 'mph',
    ]);

    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
        'key' => 'gps_speed', 'type' => 'number', 'label' => 'Speed', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
        'key' => 'gps_lat', 'type' => 'text', 'label' => 'Lat', 'value' => '',
    ]);
    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
        'key' => 'gps_lng', 'type' => 'text', 'label' => 'Lng', 'value' => '',
    ]);

    // 13.89 m/s = 50 km/h = ~31.1 mph
    postMobile($integration->webhook_token, mobilePayload([
        'speed' => 13.89,
        'timestamp' => time(),
        'serial' => '1',
    ]))->assertStatus(200);

    $speedControl = OverlayControl::where('user_id', $user->id)
        ->where('source', 'overlabels-mobile')
        ->where('key', 'gps_speed')
        ->first();

    // 50 km/h / 1.609344 = ~31.1 mph
    expect((float) $speedControl->value)->toBeGreaterThan(30.0);
    expect((float) $speedControl->value)->toBeLessThan(32.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Distance reset
// ──────────────────────────────────────────────────────────────────────────────

test('reset distance endpoint clears distance and position', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeMobileIntegration('test-mobile-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
    ]);

    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
        'key' => 'gps_distance', 'type' => 'number', 'label' => 'Distance', 'value' => '42.5',
    ]);

    $this->actingAs($user)
        ->post('/settings/integrations/overlabels-mobile/reset-distance')
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'overlabels-mobile',
        'key' => 'gps_distance',
        'value' => '0',
    ]);

    $integration->refresh();
    expect($integration->settings)->not()->toHaveKey('last_lat');
    expect($integration->settings)->not()->toHaveKey('last_lng');

    Event::assertDispatched(ControlValueUpdated::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Connect / Disconnect
// ──────────────────────────────────────────────────────────────────────────────

test('connect creates integration with auto-generated token and provisions controls', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $this->actingAs($user)
        ->post('/settings/integrations/overlabels-mobile', [
            'speed_unit' => 'kmh',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('external_integrations', [
        'user_id' => $user->id,
        'service' => 'overlabels-mobile',
        'enabled' => true,
    ]);

    // Token should be auto-generated
    $integration = ExternalIntegration::where('user_id', $user->id)
        ->where('service', 'overlabels-mobile')
        ->first();
    $credentials = $integration->getCredentialsDecrypted();
    expect($credentials['token'])->toBeString()->toHaveLength(32);

    // 4 controls should be auto-provisioned
    $controlCount = OverlayControl::where('user_id', $user->id)
        ->where('source', 'overlabels-mobile')
        ->where('source_managed', true)
        ->count();

    expect($controlCount)->toBe(4);
});

test('disconnect removes integration and controls', function () {
    [$user, $integration] = makeMobileIntegration();

    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', [
        'key' => 'gps_speed', 'type' => 'number', 'label' => 'Speed', 'value' => '0',
    ]);

    $this->actingAs($user)
        ->delete('/settings/integrations/overlabels-mobile')
        ->assertRedirect();

    $this->assertDatabaseMissing('external_integrations', [
        'user_id' => $user->id,
        'service' => 'overlabels-mobile',
    ]);

    $controlCount = OverlayControl::where('user_id', $user->id)
        ->where('source', 'overlabels-mobile')
        ->count();

    expect($controlCount)->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Regenerate token
// ──────────────────────────────────────────────────────────────────────────────

test('regenerate token changes the stored token', function () {
    [$user, $integration] = makeMobileIntegration('original-token');

    $this->actingAs($user)
        ->post('/settings/integrations/overlabels-mobile/regenerate-token')
        ->assertRedirect();

    $integration->refresh();
    $credentials = $integration->getCredentialsDecrypted();
    expect($credentials['token'])->not()->toBe('original-token');
    expect($credentials['token'])->toBeString()->toHaveLength(32);
});

// ──────────────────────────────────────────────────────────────────────────────
// Webhook landing page (GET)
// ──────────────────────────────────────────────────────────────────────────────

test('GET webhook URL returns mobile landing page', function () {
    [, $integration] = makeMobileIntegration();

    $this->get("/api/webhooks/overlabels-mobile/{$integration->webhook_token}")
        ->assertStatus(200)
        ->assertSee('Overlabels GPS')
        ->assertSee('overlabels://gps-setup');
});

test('GET webhook URL returns 404 for unknown token', function () {
    $this->get('/api/webhooks/overlabels-mobile/00000000-0000-0000-0000-000000000000')
        ->assertStatus(404);
});
