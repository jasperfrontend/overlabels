<?php

use App\Models\OverlayAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
});

function makeOverlayToken(User $user): array
{
    $material = OverlayAccessToken::generateToken();

    OverlayAccessToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => $material['hash'],
        'token_prefix' => $material['prefix'],
        'is_active' => true,
        'expires_at' => null,
        'allowed_ips' => null,
        'metadata' => null,
        'abilities' => null,
    ]);

    return [$material['plain']];
}

test('signs auth response for the owner alerts channel', function () {
    $user = User::factory()->create(['twitch_id' => '12345']);
    [$plain] = makeOverlayToken($user);

    $response = $this->postJson('/api/overlay/broadcasting/auth', [
        'slug' => 'overlay-slug',
        'token' => $plain,
        'socket_id' => '1.2',
        'channel_name' => 'private-alerts.12345',
    ]);

    $response->assertOk();

    $expected = hash_hmac('sha256', '1.2:private-alerts.12345', 'test-secret');
    $response->assertJson(['auth' => 'test-key:'.$expected]);
});

test('signs auth response for the owner twitch-events channel', function () {
    $user = User::factory()->create(['twitch_id' => '54321']);
    [$plain] = makeOverlayToken($user);

    $response = $this->postJson('/api/overlay/broadcasting/auth', [
        'slug' => 'overlay-slug',
        'token' => $plain,
        'socket_id' => '99.7',
        'channel_name' => 'private-twitch-events.54321',
    ]);

    $response->assertOk();

    $expected = hash_hmac('sha256', '99.7:private-twitch-events.54321', 'test-secret');
    $response->assertJson(['auth' => 'test-key:'.$expected]);
});

test('rejects request signing a different users alerts channel', function () {
    $owner = User::factory()->create(['twitch_id' => '11111']);
    $victim = User::factory()->create(['twitch_id' => '22222']);
    [$plain] = makeOverlayToken($owner);

    // Owner has a valid token, but tries to subscribe to victim's channel.
    $response = $this->postJson('/api/overlay/broadcasting/auth', [
        'slug' => 'overlay-slug',
        'token' => $plain,
        'socket_id' => '1.2',
        'channel_name' => 'private-alerts.22222',
    ]);

    $response->assertStatus(403);
    $response->assertJsonMissing(['auth']);
});

test('rejects unknown private channel even for valid token holder', function () {
    $user = User::factory()->create(['twitch_id' => '12345']);
    [$plain] = makeOverlayToken($user);

    $response = $this->postJson('/api/overlay/broadcasting/auth', [
        'slug' => 'overlay-slug',
        'token' => $plain,
        'socket_id' => '1.2',
        'channel_name' => 'private-gamejam.12345',
    ]);

    $response->assertStatus(403);
});

test('rejects invalid token', function () {
    $response = $this->postJson('/api/overlay/broadcasting/auth', [
        'slug' => 'overlay-slug',
        'token' => str_repeat('0', 64),
        'socket_id' => '1.2',
        'channel_name' => 'private-alerts.12345',
    ]);

    $response->assertStatus(401);
});

test('rejects token of wrong length', function () {
    $response = $this->postJson('/api/overlay/broadcasting/auth', [
        'slug' => 'overlay-slug',
        'token' => 'short',
        'socket_id' => '1.2',
        'channel_name' => 'private-alerts.12345',
    ]);

    $response->assertStatus(422);
});

test('rejects token with non-hex chars', function () {
    $response = $this->postJson('/api/overlay/broadcasting/auth', [
        'slug' => 'overlay-slug',
        'token' => str_repeat('z', 64),
        'socket_id' => '1.2',
        'channel_name' => 'private-alerts.12345',
    ]);

    $response->assertStatus(401);
});

test('rejects deactivated token', function () {
    $user = User::factory()->create(['twitch_id' => '12345']);
    $material = OverlayAccessToken::generateToken();
    OverlayAccessToken::factory()->create([
        'user_id' => $user->id,
        'token_hash' => $material['hash'],
        'token_prefix' => $material['prefix'],
        'is_active' => false,
        'expires_at' => null,
        'allowed_ips' => null,
        'metadata' => null,
        'abilities' => null,
    ]);

    $response = $this->postJson('/api/overlay/broadcasting/auth', [
        'slug' => 'overlay-slug',
        'token' => $material['plain'],
        'socket_id' => '1.2',
        'channel_name' => 'private-alerts.12345',
    ]);

    $response->assertStatus(401);
});
