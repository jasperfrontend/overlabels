<?php

use App\Models\ExternalEvent;
use App\Models\OverlayAccessToken;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\AlertMuteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function eventFeedUser(): User
{
    return User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
}

function eventFeedToken(User $user, ?string $abilities = null): string
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'event-feed-test',
        'is_active' => true,
        'abilities' => $abilities,
    ]);

    return $plain;
}

function eventFeedSeedEvents(User $user): void
{
    TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'event_data' => ['user_name' => 'NewFollower'],
        'twitch_timestamp' => now(),
        'processed' => true,
    ]);

    ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'message_id' => 'msg-'.fake()->uuid(),
        'raw_payload' => ['from_name' => 'Donor'],
        'normalized_payload' => ['event.from_name' => 'Donor', 'event.amount' => '5.00'],
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET /api/events
// ─────────────────────────────────────────────────────────────────────────────

test('returns the owner events for a valid token', function () {
    $user = eventFeedUser();
    $token = eventFeedToken($user);
    eventFeedSeedEvents($user);

    $resp = $this->getJson("/api/events?token={$token}")->assertOk();

    expect($resp->json('events.total'))->toBe(2)
        ->and($resp->json('alerts_muted'))->toBeFalse()
        ->and($resp->json('twitch_id'))->toBe($user->twitch_id)
        ->and($resp->json('facets.sources'))->toContain('twitch', 'kofi');
});

test('never returns another user\'s events', function () {
    $owner = eventFeedUser();
    $other = eventFeedUser();
    eventFeedSeedEvents($other);
    $token = eventFeedToken($owner);

    $resp = $this->getJson("/api/events?token={$token}")->assertOk();

    expect($resp->json('events.total'))->toBe(0);
});

test('applies source and range filters', function () {
    $user = eventFeedUser();
    $token = eventFeedToken($user);
    eventFeedSeedEvents($user);

    $resp = $this->getJson("/api/events?token={$token}&source=kofi")->assertOk();
    expect($resp->json('events.total'))->toBe(1)
        ->and($resp->json('events.data.0.source'))->toBe('kofi');
});

test('refuses a missing, malformed, or unknown token with 401', function () {
    $user = eventFeedUser();
    eventFeedToken($user);

    $this->getJson('/api/events')->assertStatus(401);
    $this->getJson('/api/events?token=tooshort')->assertStatus(401);
    $this->getJson('/api/events?token='.str_repeat('b', 64))->assertStatus(401);
});

test('requires the read ability when the token has abilities set', function () {
    $user = eventFeedUser();
    $writeOnly = eventFeedToken($user, 'write');
    $readable = eventFeedToken($user, 'read');
    $unrestricted = eventFeedToken($user);

    $this->getJson("/api/events?token={$writeOnly}")->assertStatus(403);
    $this->getJson("/api/events?token={$readable}")->assertOk();
    $this->getJson("/api/events?token={$unrestricted}")->assertOk();
});

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/events/mute
// ─────────────────────────────────────────────────────────────────────────────

test('mutes and unmutes with a write-able token', function () {
    $user = eventFeedUser();
    $token = eventFeedToken($user, 'read,write');

    $this->postJson('/api/events/mute', ['token' => $token, 'muted' => true])
        ->assertOk()
        ->assertJsonPath('alerts_muted', true);

    expect(app(AlertMuteService::class)->isMuted($user))->toBeTrue();

    $this->postJson('/api/events/mute', ['token' => $token, 'muted' => false])
        ->assertOk()
        ->assertJsonPath('alerts_muted', false);

    expect(app(AlertMuteService::class)->isMuted($user))->toBeFalse();
});

test('a token without the write ability cannot mute', function () {
    $user = eventFeedUser();
    $readOnly = eventFeedToken($user, 'read');

    $this->postJson('/api/events/mute', ['token' => $readOnly, 'muted' => true])
        ->assertStatus(403);

    expect(app(AlertMuteService::class)->isMuted($user))->toBeFalse();
});

test('a token with no abilities set can mute (unrestricted, backward compatible)', function () {
    $user = eventFeedUser();
    $token = eventFeedToken($user);

    $this->postJson('/api/events/mute', ['token' => $token, 'muted' => true])
        ->assertOk()
        ->assertJsonPath('alerts_muted', true);
});

test('mute requires a valid token and a boolean muted flag', function () {
    $user = eventFeedUser();
    $token = eventFeedToken($user);

    $this->postJson('/api/events/mute', ['muted' => true])->assertStatus(401);
    $this->postJson('/api/events/mute', ['token' => str_repeat('c', 64), 'muted' => true])->assertStatus(401);
    $this->postJson('/api/events/mute', ['token' => $token])->assertStatus(422);
});

// ─────────────────────────────────────────────────────────────────────────────
// Feed shell
// ─────────────────────────────────────────────────────────────────────────────

test('the events feed shell is served without authentication', function () {
    $this->get('/events/feed')->assertOk();
});
