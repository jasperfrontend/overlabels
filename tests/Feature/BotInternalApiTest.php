<?php

use App\Models\BotToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Channels endpoint
// ──────────────────────────────────────────────────────────────────────────────

test('channels returns 403 without secret', function () {
    $this->getJson('/api/internal/bot/channels')
        ->assertStatus(403);
});

test('channels returns 403 with wrong secret', function () {
    $this->getJson('/api/internal/bot/channels', ['X-Internal-Secret' => 'wrong'])
        ->assertStatus(403);
});

test('channels returns 403 when server secret unset', function () {
    config(['services.twitchbot.listener_secret' => null]);

    $this->getJson('/api/internal/bot/channels', ['X-Internal-Secret' => 'anything'])
        ->assertStatus(403);
});

test('channels returns empty list when no users opted in', function () {
    User::factory()->create([
        'bot_enabled' => false,
        'twitch_data' => ['login' => 'not_opted_in'],
    ]);

    $this->getJson('/api/internal/bot/channels', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->assertExactJson(['channels' => []]);
});

test('channels returns lowercased logins of opted-in users', function () {
    User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'JasperDiscovers'],
    ]);
    User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'another_user'],
    ]);
    User::factory()->create([
        'bot_enabled' => false,
        'twitch_data' => ['login' => 'not_in_list'],
    ]);

    $response = $this->getJson('/api/internal/bot/channels', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->json('channels');

    expect($response)->toHaveCount(2)
        ->and($response)->toContain('jasperdiscovers')
        ->and($response)->toContain('another_user')
        ->and($response)->not()->toContain('not_in_list');
});

test('channels skips users with missing twitch_data login', function () {
    User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['display_name' => 'NoLogin'],
    ]);

    $this->getJson('/api/internal/bot/channels', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->assertExactJson(['channels' => []]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Tokens endpoint - GET
// ──────────────────────────────────────────────────────────────────────────────

test('tokens show returns 403 without secret', function () {
    $this->getJson('/api/internal/bot/tokens')
        ->assertStatus(403);
});

test('tokens show returns 404 when no tokens stored', function () {
    $this->getJson('/api/internal/bot/tokens', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertStatus(404);
});

test('tokens show returns stored tokens with correct shape', function () {
    BotToken::create([
        'account' => 'overlabels',
        'access_token' => 'access-abc',
        'refresh_token' => 'refresh-xyz',
        'expires_at' => 1_900_000_000,
        'obtained_at' => 1_800_000_000,
        'scopes' => ['user:read:chat', 'user:write:chat', 'user:bot'],
    ]);

    $this->getJson('/api/internal/bot/tokens', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->assertExactJson([
            'access_token' => 'access-abc',
            'refresh_token' => 'refresh-xyz',
            'expires_at' => 1_900_000_000,
            'obtained_at' => 1_800_000_000,
            'scopes' => ['user:read:chat', 'user:write:chat', 'user:bot'],
        ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Tokens endpoint - POST
// ──────────────────────────────────────────────────────────────────────────────

test('tokens store returns 403 without secret', function () {
    $this->postJson('/api/internal/bot/tokens', [
        'access_token' => 'a',
        'refresh_token' => 'r',
        'expires_at' => 1,
        'obtained_at' => 1,
    ])->assertStatus(403);
});

test('tokens store rejects missing fields', function () {
    $this->postJson('/api/internal/bot/tokens', [], ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['access_token', 'refresh_token', 'expires_at', 'obtained_at']);
});

test('tokens store creates row on first call', function () {
    $this->postJson('/api/internal/bot/tokens', [
        'access_token' => 'new-access',
        'refresh_token' => 'new-refresh',
        'expires_at' => 1_700_000_000,
        'obtained_at' => 1_600_000_000,
        'scopes' => ['user:bot'],
    ], ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertNoContent();

    $token = BotToken::where('account', 'overlabels')->firstOrFail();

    expect($token->access_token)->toBe('new-access')
        ->and($token->refresh_token)->toBe('new-refresh')
        ->and($token->expires_at)->toBe(1_700_000_000)
        ->and($token->obtained_at)->toBe(1_600_000_000)
        ->and($token->scopes)->toBe(['user:bot']);
});

test('tokens store upserts existing row', function () {
    BotToken::create([
        'account' => 'overlabels',
        'access_token' => 'old',
        'refresh_token' => 'old-r',
        'expires_at' => 1,
        'obtained_at' => 1,
        'scopes' => [],
    ]);

    $this->postJson('/api/internal/bot/tokens', [
        'access_token' => 'rotated',
        'refresh_token' => 'rotated-r',
        'expires_at' => 2,
        'obtained_at' => 2,
    ], ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertNoContent();

    expect(BotToken::count())->toBe(1);

    $token = BotToken::where('account', 'overlabels')->first();
    expect($token->access_token)->toBe('rotated')
        ->and($token->refresh_token)->toBe('rotated-r');
});

test('tokens store encrypts tokens at rest', function () {
    $this->postJson('/api/internal/bot/tokens', [
        'access_token' => 'plaintext-access',
        'refresh_token' => 'plaintext-refresh',
        'expires_at' => 1,
        'obtained_at' => 1,
    ], ['X-Internal-Secret' => 'test-bot-secret']);

    $raw = DB::table('bot_tokens')->where('account', 'overlabels')->first();

    expect($raw->access_token)->not()->toBe('plaintext-access')
        ->and($raw->refresh_token)->not()->toBe('plaintext-refresh');
});
