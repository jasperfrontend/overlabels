<?php

use App\Events\ControlValueUpdated;
use App\Models\BotCommand;
use App\Models\BotToken;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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

// ──────────────────────────────────────────────────────────────────────────────
// Helpers (Phase 2)
// ──────────────────────────────────────────────────────────────────────────────

function makeOptedInUser(string $login = 'streamer_a', bool $enabled = true): User
{
    return User::factory()->create([
        'bot_enabled' => $enabled,
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// UserObserver — seed defaults on opt-in
// ──────────────────────────────────────────────────────────────────────────────

test('opting into the bot seeds the default command set', function () {
    $user = User::factory()->create(['bot_enabled' => false]);

    expect(BotCommand::where('user_id', $user->id)->count())->toBe(0);

    $user->update(['bot_enabled' => true]);

    $commands = BotCommand::where('user_id', $user->id)->pluck('command')->all();

    expect($commands)->toHaveCount(count(BotCommand::DEFAULTS))
        ->and($commands)->toContain('control', 'set', 'increment', 'decrement', 'reset');

    $setCommand = BotCommand::where('user_id', $user->id)->where('command', 'set')->first();
    expect($setCommand->permission_level)->toBe('moderator')
        ->and($setCommand->enabled)->toBeTrue();
});

test('toggling bot_enabled off then on does not duplicate default commands', function () {
    $user = User::factory()->create(['bot_enabled' => false]);

    $user->update(['bot_enabled' => true]);
    $user->update(['bot_enabled' => false]);
    $user->update(['bot_enabled' => true]);

    expect(BotCommand::where('user_id', $user->id)->count())
        ->toBe(count(BotCommand::DEFAULTS));
});

test('creating a user with bot_enabled true seeds defaults', function () {
    $user = User::factory()->create(['bot_enabled' => true]);

    expect(BotCommand::where('user_id', $user->id)->count())
        ->toBe(count(BotCommand::DEFAULTS));
});

test('seedDefaults preserves existing permission_level overrides', function () {
    $user = makeOptedInUser();

    BotCommand::where('user_id', $user->id)
        ->where('command', 'set')
        ->update(['permission_level' => 'broadcaster']);

    BotCommand::seedDefaults($user);

    $setCommand = BotCommand::where('user_id', $user->id)->where('command', 'set')->first();
    expect($setCommand->permission_level)->toBe('broadcaster');
});

// ──────────────────────────────────────────────────────────────────────────────
// Commands endpoint
// ──────────────────────────────────────────────────────────────────────────────

test('commands returns 403 without secret', function () {
    $this->getJson('/api/internal/bot/commands')->assertStatus(403);
});

test('commands returns empty map when no users opted in', function () {
    User::factory()->create([
        'bot_enabled' => false,
        'twitch_data' => ['login' => 'opted_out'],
    ]);

    $this->getJson('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->assertExactJson(['channels' => []]);
});

test('commands returns commands keyed by lowercased login', function () {
    makeOptedInUser('JasperDiscovers');

    $response = $this->getJson('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->json('channels');

    expect($response)->toHaveKey('jasperdiscovers')
        ->and($response['jasperdiscovers'])->toHaveCount(count(BotCommand::DEFAULTS));

    $control = collect($response['jasperdiscovers'])->firstWhere('command', 'control');
    expect($control)->toBe(['command' => 'control', 'permission_level' => 'everyone']);
});

test('commands excludes disabled rows', function () {
    $user = makeOptedInUser();

    BotCommand::where('user_id', $user->id)
        ->where('command', 'reset')
        ->update(['enabled' => false]);

    $commands = $this->getJson('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret'])
        ->json('channels.streamer_a');

    expect(collect($commands)->pluck('command')->all())
        ->not()->toContain('reset')
        ->and(collect($commands)->pluck('command')->all())->toContain('set');
});

// ──────────────────────────────────────────────────────────────────────────────
// Controls show
// ──────────────────────────────────────────────────────────────────────────────

test('controls show returns 403 without secret', function () {
    $this->getJson('/api/internal/bot/controls/streamer_a/deaths')->assertStatus(403);
});

test('controls show returns 404 for unknown channel', function () {
    $this->getJson('/api/internal/bot/controls/nobody/deaths', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertStatus(404);
});

test('controls show returns 404 for unknown key', function () {
    makeOptedInUser();

    $this->getJson('/api/internal/bot/controls/streamer_a/deaths', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertStatus(404);
});

test('controls show returns control shape', function () {
    $user = makeOptedInUser();
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'deaths',
        'label' => 'Deaths',
        'type' => 'counter',
        'value' => '7',
        'source_managed' => false,
    ]);

    $this->getJson('/api/internal/bot/controls/streamer_a/deaths', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->assertExactJson([
            'key' => 'deaths',
            'type' => 'counter',
            'value' => '7',
            'label' => 'Deaths',
        ]);
});

test('controls show hides source-managed controls from chat reach', function () {
    $user = makeOptedInUser();
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'donations_received',
        'label' => 'Donations',
        'type' => 'counter',
        'value' => '42',
        'source' => 'kofi',
        'source_managed' => true,
    ]);

    $this->getJson('/api/internal/bot/controls/streamer_a/donations_received', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertStatus(404);
});

// ──────────────────────────────────────────────────────────────────────────────
// Controls update
// ──────────────────────────────────────────────────────────────────────────────

function makeBotControl(User $user, string $key, string $type = 'counter', string $value = '0'): OverlayControl
{
    return OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => $key,
        'type' => $type,
        'value' => $value,
        'source_managed' => false,
    ]);
}

test('controls update returns 403 without secret', function () {
    $this->postJson('/api/internal/bot/controls/streamer_a/deaths', ['action' => 'set', 'value' => '1'])
        ->assertStatus(403);
});

test('controls update validates action', function () {
    makeOptedInUser();

    $this->postJson(
        '/api/internal/bot/controls/streamer_a/deaths',
        ['action' => 'delete'],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertStatus(422)->assertJsonValidationErrors(['action']);
});

test('controls update requires value when action is set', function () {
    makeOptedInUser();

    $this->postJson(
        '/api/internal/bot/controls/streamer_a/deaths',
        ['action' => 'set'],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertStatus(422)->assertJsonValidationErrors(['value']);
});

test('controls update returns 404 when control missing', function () {
    makeOptedInUser();

    $this->postJson(
        '/api/internal/bot/controls/streamer_a/deaths',
        ['action' => 'set', 'value' => '5'],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertStatus(404);
});

test('controls update sets value exactly', function () {
    Event::fake([ControlValueUpdated::class]);

    $user = makeOptedInUser();
    $control = makeBotControl($user, 'deaths');

    $this->postJson(
        '/api/internal/bot/controls/streamer_a/deaths',
        ['action' => 'set', 'value' => '42'],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertOk()->assertJson([
        'key' => 'deaths',
        'type' => 'counter',
        'value' => '42',
    ]);

    expect($control->fresh()->value)->toBe('42');

    Event::assertDispatched(ControlValueUpdated::class, fn ($e) => $e->key === 'deaths'
        && $e->value === '42'
        && $e->broadcasterId === $user->twitch_id
    );
});

test('controls update increments counter by default step of 1', function () {
    Event::fake([ControlValueUpdated::class]);

    $user = makeOptedInUser();
    makeBotControl($user, 'deaths', value: '3');

    $this->postJson(
        '/api/internal/bot/controls/streamer_a/deaths',
        ['action' => 'increment'],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertOk()->assertJsonPath('value', '4');
});

test('controls update increments counter by supplied amount', function () {
    $user = makeOptedInUser();
    makeBotControl($user, 'deaths', value: '10');

    $this->postJson(
        '/api/internal/bot/controls/streamer_a/deaths',
        ['action' => 'increment', 'amount' => 5],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertOk()->assertJsonPath('value', '15');
});

test('controls update decrements counter', function () {
    $user = makeOptedInUser();
    makeBotControl($user, 'deaths', value: '10');

    $this->postJson(
        '/api/internal/bot/controls/streamer_a/deaths',
        ['action' => 'decrement', 'amount' => 3],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertOk()->assertJsonPath('value', '7');
});

test('controls update resets counter to zero', function () {
    $user = makeOptedInUser();
    makeBotControl($user, 'deaths', value: '99');

    $this->postJson(
        '/api/internal/bot/controls/streamer_a/deaths',
        ['action' => 'reset'],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertOk()->assertJsonPath('value', '0');
});

test('controls update rejects increment on text control', function () {
    $user = makeOptedInUser();
    makeBotControl($user, 'message', type: 'text', value: 'hello');

    $this->postJson(
        '/api/internal/bot/controls/streamer_a/message',
        ['action' => 'increment'],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertStatus(422);
});

test('controls update cannot touch source-managed controls', function () {
    $user = makeOptedInUser();
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'donations_received',
        'type' => 'counter',
        'value' => '50',
        'source' => 'kofi',
        'source_managed' => true,
    ]);

    $this->postJson(
        '/api/internal/bot/controls/streamer_a/donations_received',
        ['action' => 'increment'],
        ['X-Internal-Secret' => 'test-bot-secret'],
    )->assertStatus(404);

    expect(OverlayControl::where('user_id', $user->id)->first()->value)->toBe('50');
});
