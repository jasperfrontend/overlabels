<?php

use App\Events\ListUpdated;
use App\Models\OptionSet;
use App\Models\OverlayAccessToken;
use App\Models\User;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // The test env doesn't set REVERB_* creds; give the broadcasting-auth
    // endpoint a key + secret so it can produce a signature (real signing is
    // verified end-to-end against a live Reverb separately).
    config([
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
    ]);
});

function liveToken(User $user): string
{
    $plain = str_repeat('a', 64);
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'live-test',
        'is_active' => true,
    ]);

    return $plain;
}

// ──────────────────────────────────────────────────────────────────────────────
// The event fans out to the list-scoped channel
// ──────────────────────────────────────────────────────────────────────────────

it('broadcasts on both the alerts firehose and the list-scoped channel', function () {
    $event = new ListUpdated('12345', 'wheel', [], now()->timestamp);

    $names = array_map(fn ($c) => $c->name, $event->broadcastOn());

    expect($names)->toBe([
        'private-alerts.12345',
        'private-lists.12345.wheel',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// The read endpoint tells a consumer how to subscribe
// ──────────────────────────────────────────────────────────────────────────────

it('exposes a realtime block so a consumer can subscribe after bootstrapping', function () {
    $user = User::factory()->create();
    $token = liveToken($user);
    $built = ListItems::freshFromValues(['a'], 1);
    OptionSet::factory()->create(['user_id' => $user->id, 'slug' => 'wheel', 'items' => $built['items'], 'next_item_id' => $built['next_id']]);

    $this->getJson("/api/lists/wheel?token={$token}")
        ->assertOk()
        ->assertJsonPath('realtime.channel', "lists.{$user->twitch_id}.wheel")
        ->assertJsonPath('realtime.event', 'list.updated')
        ->assertJsonPath('realtime.auth_endpoint', url('/api/overlay/broadcasting/auth'))
        ->assertJsonPath('realtime.key', config('broadcasting.connections.reverb.key'));
});

// ──────────────────────────────────────────────────────────────────────────────
// Broadcasting auth: a token signs its own list channel, nothing else
// ──────────────────────────────────────────────────────────────────────────────

function authPost(string $token, string $channel): TestResponse
{
    return test()->postJson('/api/overlay/broadcasting/auth', [
        'slug' => 'wheel',
        'token' => $token,
        'socket_id' => '123.456',
        'channel_name' => $channel,
    ]);
}

it('signs the token owner\'s own list channel', function () {
    $user = User::factory()->create();
    $token = liveToken($user);

    authPost($token, "private-lists.{$user->twitch_id}.wheel")
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('still signs the fixed alerts channel', function () {
    $user = User::factory()->create();
    $token = liveToken($user);

    authPost($token, "private-alerts.{$user->twitch_id}")
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('refuses a list channel under another user\'s id', function () {
    $user = User::factory()->create();
    $token = liveToken($user);

    authPost($token, 'private-lists.99999999.wheel')->assertStatus(403);
});

it('refuses a malformed list channel slug', function () {
    $user = User::factory()->create();
    $token = liveToken($user);

    authPost($token, "private-lists.{$user->twitch_id}.Bad-Slug")->assertStatus(403);
});

it('refuses an unrelated channel namespace', function () {
    $user = User::factory()->create();
    $token = liveToken($user);

    authPost($token, "private-gamejam.{$user->twitch_id}")->assertStatus(403);
});
