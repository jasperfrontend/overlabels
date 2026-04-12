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

function makeGpsIntegration(string $token = 'test-gps-token', array $settings = []): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'gpslogger',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['token' => $token])),
        'settings' => $settings,
    ]);

    return [$user, $integration];
}

function gpsPayload(array $overrides = []): array
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

function postGps(string $webhookToken, array $payload, string $token = 'test-gps-token'): TestResponse
{
    return test()->postJson(
        "/api/webhooks/gpslogger/{$webhookToken}",
        $payload,
        ['X-GPSLogger-Token' => $token],
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Verification
// ──────────────────────────────────────────────────────────────────────────────

test('returns 403 when token does not match', function () {
    [, $integration] = makeGpsIntegration('correct-token');

    postGps($integration->webhook_token, gpsPayload(), 'wrong-token')
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// Happy path
// ──────────────────────────────────────────────────────────────────────────────

test('returns 200 and stores event for valid GPS payload', function () {
    [$user, $integration] = makeGpsIntegration();
    $ts = time();

    postGps($integration->webhook_token, gpsPayload(['timestamp' => $ts, 'serial' => '1']))
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('external_events', [
        'user_id' => $user->id,
        'service' => 'gpslogger',
        'event_type' => 'location_update',
        'message_id' => "gps_{$ts}_1",
    ]);
});

test('updates last_received_at on successful webhook', function () {
    [, $integration] = makeGpsIntegration();

    postGps($integration->webhook_token, gpsPayload())->assertStatus(200);

    $integration->refresh();
    expect($integration->last_received_at)->not()->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Deduplication
// ──────────────────────────────────────────────────────────────────────────────

test('returns 200 with duplicate status for duplicate timestamp+serial', function () {
    [, $integration] = makeGpsIntegration();
    $payload = gpsPayload(['timestamp' => 1234567890, 'serial' => '42']);

    postGps($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'ok']);
    postGps($integration->webhook_token, $payload)->assertStatus(200)->assertJson(['status' => 'duplicate']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Control updates
// ──────────────────────────────────────────────────────────────────────────────

test('updates speed, lat, lng controls on location update', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $integration] = makeGpsIntegration();

    // Pre-provision controls
    foreach (['gps_speed', 'gps_lat', 'gps_lng', 'gps_distance'] as $key) {
        OverlayControl::provisionServiceControl($user, 'gpslogger', [
            'key' => $key,
            'type' => in_array($key, ['gps_speed', 'gps_distance']) ? 'number' : 'text',
            'label' => $key,
            'value' => '0',
        ]);
    }

    postGps($integration->webhook_token, gpsPayload([
        'latitude' => 52.3676,
        'longitude' => 4.9041,
        'speed' => 13.89,
    ]))->assertStatus(200);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gpslogger',
        'key' => 'gps_lat',
        'value' => '52.3676',
    ]);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gpslogger',
        'key' => 'gps_lng',
        'value' => '4.9041',
    ]);

    // Speed: 13.89 m/s * 3.6 = 50.0 km/h
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gpslogger',
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

    [$user, $integration] = makeGpsIntegration();

    OverlayControl::provisionServiceControl($user, 'gpslogger', [
        'key' => 'gps_distance', 'type' => 'number', 'label' => 'Distance', 'value' => '0',
    ]);

    postGps($integration->webhook_token, gpsPayload([
        'latitude' => 52.3676,
        'longitude' => 4.9041,
        'timestamp' => time(),
        'serial' => '1',
    ]))->assertStatus(200);

    // Distance should remain 0 (no previous position)
    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gpslogger',
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
    [$user, $integration] = makeGpsIntegration('test-gps-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
    ]);

    OverlayControl::provisionServiceControl($user, 'gpslogger', [
        'key' => 'gps_distance', 'type' => 'number', 'label' => 'Distance', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'gpslogger', [
        'key' => 'gps_speed', 'type' => 'number', 'label' => 'Speed', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'gpslogger', [
        'key' => 'gps_lat', 'type' => 'text', 'label' => 'Lat', 'value' => '',
    ]);
    OverlayControl::provisionServiceControl($user, 'gpslogger', [
        'key' => 'gps_lng', 'type' => 'text', 'label' => 'Lng', 'value' => '',
    ]);

    // Move to a point ~1.1 km away (Rotterdam direction)
    postGps($integration->webhook_token, gpsPayload([
        'latitude' => 52.3776,
        'longitude' => 4.9041,
        'timestamp' => time(),
        'serial' => '2',
    ]))->assertStatus(200);

    $distanceControl = OverlayControl::where('user_id', $user->id)
        ->where('source', 'gpslogger')
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

    [$user, $integration] = makeGpsIntegration('test-gps-token', [
        'speed_unit' => 'mph',
    ]);

    OverlayControl::provisionServiceControl($user, 'gpslogger', [
        'key' => 'gps_speed', 'type' => 'number', 'label' => 'Speed', 'value' => '0',
    ]);
    OverlayControl::provisionServiceControl($user, 'gpslogger', [
        'key' => 'gps_lat', 'type' => 'text', 'label' => 'Lat', 'value' => '',
    ]);
    OverlayControl::provisionServiceControl($user, 'gpslogger', [
        'key' => 'gps_lng', 'type' => 'text', 'label' => 'Lng', 'value' => '',
    ]);

    // 13.89 m/s = 50 km/h = ~31.1 mph
    postGps($integration->webhook_token, gpsPayload([
        'speed' => 13.89,
        'timestamp' => time(),
        'serial' => '1',
    ]))->assertStatus(200);

    $speedControl = OverlayControl::where('user_id', $user->id)
        ->where('source', 'gpslogger')
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

    [$user, $integration] = makeGpsIntegration('test-gps-token', [
        'last_lat' => 52.3676,
        'last_lng' => 4.9041,
    ]);

    OverlayControl::provisionServiceControl($user, 'gpslogger', [
        'key' => 'gps_distance', 'type' => 'number', 'label' => 'Distance', 'value' => '42.5',
    ]);

    $this->actingAs($user)
        ->post('/settings/integrations/gpslogger/reset-distance')
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('overlay_controls', [
        'user_id' => $user->id,
        'source' => 'gpslogger',
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

test('connect creates integration and auto-provisions controls', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $this->actingAs($user)
        ->post('/settings/integrations/gpslogger', [
            'token' => 'my-secret-token',
            'speed_unit' => 'kmh',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('external_integrations', [
        'user_id' => $user->id,
        'service' => 'gpslogger',
        'enabled' => true,
    ]);

    // 4 controls should be auto-provisioned
    $controlCount = OverlayControl::where('user_id', $user->id)
        ->where('source', 'gpslogger')
        ->where('source_managed', true)
        ->count();

    expect($controlCount)->toBe(4);
});

test('disconnect removes integration and controls', function () {
    [$user, $integration] = makeGpsIntegration();

    OverlayControl::provisionServiceControl($user, 'gpslogger', [
        'key' => 'gps_speed', 'type' => 'number', 'label' => 'Speed', 'value' => '0',
    ]);

    $this->actingAs($user)
        ->delete('/settings/integrations/gpslogger')
        ->assertRedirect();

    $this->assertDatabaseMissing('external_integrations', [
        'user_id' => $user->id,
        'service' => 'gpslogger',
    ]);

    $controlCount = OverlayControl::where('user_id', $user->id)
        ->where('source', 'gpslogger')
        ->count();

    expect($controlCount)->toBe(0);
});
