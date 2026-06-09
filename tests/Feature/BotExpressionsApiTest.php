<?php

use App\Models\BotChatOutbox;
use App\Models\BotCommand;
use App\Models\BotExpression;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Bot\BotExpressionResolver;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);

    // Stub the Twitch fetch so the resolver doesn't make HTTP calls in tests.
    // Tests that need bare Twitch tags rebind this to whatever they want.
    $stub = new class extends TwitchApiService
    {
        public function __construct() {}

        public function getExtendedUserData(string $accessToken, string $twitchId): array
        {
            return [];
        }
    };
    app()->instance(TwitchApiService::class, $stub);
});

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeOptedInBotUser(string $login = 'streamer_a'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'bot_settings' => ['controls_enabled' => true],
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function firePayload(array $overrides = []): array
{
    return array_merge([
        'channel_login' => 'streamer_a',
        'command' => 'distance',
        'chatter_id' => '12345',
        'chatter_login' => 'cool_chatter',
        'chatter_display_name' => 'CoolChatter',
        'badges' => [],
        'args' => '',
    ], $overrides);
}

function fireRequest(array $payload): TestResponse
{
    return test()->postJson(
        '/api/internal/bot/expressions/fire',
        $payload,
        ['X-Internal-Secret' => 'test-bot-secret'],
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Fire endpoint - auth
// ──────────────────────────────────────────────────────────────────────────────

test('fire returns 403 without secret', function () {
    $this->postJson('/api/internal/bot/expressions/fire', firePayload())
        ->assertStatus(403);
});

test('fire returns 403 with wrong secret', function () {
    $this->postJson(
        '/api/internal/bot/expressions/fire',
        firePayload(),
        ['X-Internal-Secret' => 'wrong'],
    )->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// Fire endpoint - lookup
// ──────────────────────────────────────────────────────────────────────────────

test('fire returns channel_not_found when login does not match opted-in user', function () {
    fireRequest(firePayload(['channel_login' => 'nobody']))
        ->assertOk()
        ->assertJson(['queued' => false, 'reason' => 'channel_not_found']);
});

test('fire returns expression_not_found when command has no matching expression', function () {
    makeOptedInBotUser();

    fireRequest(firePayload())
        ->assertOk()
        ->assertJson(['queued' => false, 'reason' => 'expression_not_found']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Fire endpoint - gating
// ──────────────────────────────────────────────────────────────────────────────

test('fire returns gate when expression is disabled', function () {
    $user = makeOptedInBotUser();
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'distance',
        'expression' => 'hi',
        'enabled' => false,
    ]);

    fireRequest(firePayload())
        ->assertOk()
        ->assertJson(['queued' => false, 'reason' => 'gate']);

    expect(BotChatOutbox::count())->toBe(0);
});

test('fire returns gate when chatter lacks permission', function () {
    $user = makeOptedInBotUser();
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'distance',
        'expression' => 'mods only',
        'permission_level' => 'moderator',
    ]);

    fireRequest(firePayload(['badges' => []]))
        ->assertOk()
        ->assertJson(['queued' => false, 'reason' => 'gate']);
});

test('fire allows subscriber for moderator-tier expression via tier ladder', function () {
    $user = makeOptedInBotUser();
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'distance',
        'expression' => 'allowed',
        'permission_level' => 'subscriber',
    ]);

    fireRequest(firePayload(['badges' => ['vip']]))
        ->assertOk()
        ->assertJson(['queued' => true, 'message' => 'allowed']);
});

test('fire respects cooldown for non-broadcaster', function () {
    $user = makeOptedInBotUser();
    $expr = BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'distance',
        'expression' => 'pong',
        'cooldown_seconds' => 60,
        'last_fired_at' => Carbon::now()->subSeconds(10),
    ]);

    fireRequest(firePayload(['badges' => ['subscriber']]))
        ->assertOk()
        ->assertJson(['queued' => false, 'reason' => 'gate']);
});

test('fire bypasses cooldown for broadcaster', function () {
    $user = makeOptedInBotUser();
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'distance',
        'expression' => 'pong',
        'cooldown_seconds' => 60,
        'last_fired_at' => Carbon::now()->subSeconds(10),
    ]);

    fireRequest(firePayload(['badges' => ['broadcaster']]))
        ->assertOk()
        ->assertJson(['queued' => true, 'message' => 'pong']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Fire endpoint - resolution + outbox
// ──────────────────────────────────────────────────────────────────────────────

test('fire resolves bot tags and writes to outbox', function () {
    $user = makeOptedInBotUser();
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'shoutout',
        'expression' => 'Hi [[[bot:from_user]]], shouting out [[[bot:args.0]]]!',
    ]);

    fireRequest(firePayload([
        'command' => 'shoutout',
        'chatter_display_name' => 'Alice',
        'args' => 'JasperDiscovers',
    ]))
        ->assertOk()
        ->assertJson([
            'queued' => true,
            'message' => 'Hi Alice, shouting out JasperDiscovers!',
        ]);

    $row = BotChatOutbox::first();
    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($user->id)
        ->and($row->message)->toBe('Hi Alice, shouting out JasperDiscovers!');
});

test('fire resolves c tags from own controls', function () {
    $user = makeOptedInBotUser();
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'goal_km',
        'label' => 'Goal',
        'type' => 'number',
        'value' => '42',
        'source_managed' => false,
    ]);
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'goal',
        'expression' => 'goal is [[[c:goal_km]]]km',
    ]);

    fireRequest(firePayload(['command' => 'goal']))
        ->assertOk()
        ->assertJson(['message' => 'goal is 42km']);
});

test('fire resolves c tags from service-managed controls via broadcastKey', function () {
    $user = makeOptedInBotUser();
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'donations_received',
        'source' => 'kofi',
        'label' => 'Donations',
        'type' => 'counter',
        'value' => '7',
        'source_managed' => true,
    ]);
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'donations',
        'expression' => '[[[c:kofi:donations_received]]] donations today',
    ]);

    fireRequest(firePayload(['command' => 'donations']))
        ->assertOk()
        ->assertJson(['message' => '7 donations today']);
});

test('fire stamps last_fired_at', function () {
    $user = makeOptedInBotUser();
    $expr = BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'distance',
        'expression' => 'hi',
        'last_fired_at' => null,
    ]);

    fireRequest(firePayload())->assertOk();

    expect($expr->fresh()->last_fired_at)->not->toBeNull();
});

test('fire skips outbox write when resolved message is empty', function () {
    $user = makeOptedInBotUser();
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'empty',
        'expression' => '[[[c:nonexistent]]]',
    ]);

    fireRequest(firePayload(['command' => 'empty']))
        ->assertOk()
        ->assertJson(['queued' => false]);

    expect(BotChatOutbox::count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Sync endpoint - merged commandMap
// ──────────────────────────────────────────────────────────────────────────────

test('commands sync includes builtins with type=builtin', function () {
    makeOptedInBotUser('streamer_a');

    $payload = $this->getJson('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->json('channels.streamer_a');

    expect($payload)->not->toBeEmpty();
    foreach ($payload as $entry) {
        expect($entry)->toHaveKeys(['command', 'permission_level', 'type'])
            ->and($entry['type'])->toBe('builtin');
    }
});

test('commands sync merges expressions with type=expression', function () {
    $user = makeOptedInBotUser('streamer_a');
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'distance',
        'permission_level' => 'everyone',
        'expression' => 'hi',
    ]);

    $payload = $this->getJson('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->json('channels.streamer_a');

    $expressions = array_filter($payload, fn ($e) => $e['type'] === 'expression');
    expect($expressions)->toHaveCount(1);

    $first = array_values($expressions)[0];
    expect($first['command'])->toBe('distance')
        ->and($first['permission_level'])->toBe('everyone');
});

test('commands sync excludes disabled expressions', function () {
    $user = makeOptedInBotUser('streamer_a');
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'distance',
        'expression' => 'hi',
        'enabled' => false,
    ]);

    $payload = $this->getJson('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->json('channels.streamer_a');

    $expressions = array_filter($payload, fn ($e) => $e['type'] === 'expression');
    expect($expressions)->toBeEmpty();
});

test('commands sync drops expression that collides with builtin', function () {
    $user = makeOptedInBotUser('streamer_a');
    // 'set' is a builtin (BotCommand::DEFAULTS).
    BotExpression::factory()->create([
        'user_id' => $user->id,
        'command' => 'set',
        'expression' => 'should be ignored',
    ]);

    $payload = $this->getJson('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk()
        ->json('channels.streamer_a');

    $matchingSet = array_filter($payload, fn ($e) => $e['command'] === 'set');
    expect($matchingSet)->toHaveCount(1);
    $first = array_values($matchingSet)[0];
    expect($first['type'])->toBe('builtin');
});

// ──────────────────────────────────────────────────────────────────────────────
// Resolver - directly
// ──────────────────────────────────────────────────────────────────────────────

test('resolver applies number formatter with locale', function () {
    $user = makeOptedInBotUser();
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'count',
        'type' => 'number',
        'value' => '1234567',
        'source_managed' => false,
    ]);

    $resolver = app(BotExpressionResolver::class);
    $output = $resolver->resolve($user, '[[[c:count|number]]]');

    // en-US locale defaults to comma thousands.
    expect($output)->toBe('1,234,567');
});

test('resolver applies distance formatter to convert meters', function () {
    $user = makeOptedInBotUser();
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'session_distance',
        'type' => 'number',
        'value' => '8704',
        'source_managed' => false,
    ]);

    $resolver = app(BotExpressionResolver::class);

    expect($resolver->resolve($user, '[[[c:session_distance|distance:mi]]]'))->toBe('5.41')
        ->and($resolver->resolve($user, '[[[c:session_distance|distance:km]]]'))->toBe('8.7');
});

test('resolver applies uppercase formatter', function () {
    $user = makeOptedInBotUser();
    $resolver = app(BotExpressionResolver::class);
    $context = ['from_user' => 'alice'];

    expect($resolver->resolve($user, '[[[bot:from_user|uppercase]]]', $context))->toBe('ALICE');
});

test('resolver login formatter strips the @ for URLs, mention keeps it', function () {
    $user = makeOptedInBotUser();
    $resolver = app(BotExpressionResolver::class);

    // Chatter typed "!so @Johnny45" - args arrives with the @ already attached.
    $context = ['args.0' => '@Johnny45'];

    expect($resolver->resolve($user, 'https://twitch.tv/[[[bot:args.0|login]]]', $context))
        ->toBe('https://twitch.tv/Johnny45')
        ->and($resolver->resolve($user, '[[[bot:args.0|mention]]]', $context))
        ->toBe('@Johnny45');
});

test('mention formatter adds the @ when the chatter omitted it', function () {
    $user = makeOptedInBotUser();
    $resolver = app(BotExpressionResolver::class);

    // Chatter typed "!so Johnny45" - no @.
    $context = ['args.0' => 'Johnny45'];

    expect($resolver->resolve($user, '[[[bot:args.0|mention]]]', $context))
        ->toBe('@Johnny45')
        ->and($resolver->resolve($user, 'https://twitch.tv/[[[bot:args.0|login]]]', $context))
        ->toBe('https://twitch.tv/Johnny45');
});

test('login and mention formatters leave empty args empty', function () {
    $user = makeOptedInBotUser();
    $resolver = app(BotExpressionResolver::class);

    expect($resolver->resolve($user, '[[[bot:args.0|login]]]', []))->toBe('')
        ->and($resolver->resolve($user, '[[[bot:args.0|mention]]]', []))->toBe('');
});

test('resolver does not re-scan substituted values for tags (single-pass)', function () {
    $user = makeOptedInBotUser();
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'sneaky',
        'type' => 'string',
        'value' => '[[[c:goal_km]]]',
        'source_managed' => false,
    ]);
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'goal_km',
        'type' => 'number',
        'value' => '999',
        'source_managed' => false,
    ]);

    $resolver = app(BotExpressionResolver::class);
    $output = $resolver->resolve($user, '[[[c:sneaky]]]');

    // Single-pass: the literal "[[[c:goal_km]]]" survives in the output. If we
    // ever re-scanned, it would resolve to "999" and this test would fail.
    expect($output)->toBe('[[[c:goal_km]]]');
});

test('resolver dry run skips Twitch fetch', function () {
    $user = makeOptedInBotUser();

    // Bind a stub that throws if called - dry-run path should never reach it.
    app()->instance(TwitchApiService::class, new class extends TwitchApiService
    {
        public function __construct() {}

        public function getExtendedUserData(string $accessToken, string $twitchId): array
        {
            throw new RuntimeException('Twitch fetch happened during dry run');
        }
    });

    $resolver = app(BotExpressionResolver::class);
    $output = $resolver->resolve($user, 'followers: [[[followers_total]]]', [], dryRun: true);

    expect($output)->toBe('followers: ');
});

test('resolver caps output at 500 characters', function () {
    $user = makeOptedInBotUser();
    $resolver = app(BotExpressionResolver::class);

    $longExpression = str_repeat('a', 600);
    $output = $resolver->resolve($user, $longExpression);

    expect(mb_strlen($output))->toBe(500);
});
