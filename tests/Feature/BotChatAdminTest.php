<?php

use App\Models\BotAlias;
use App\Models\BotChatOutbox;
use App\Models\BotExpression;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
});

function adminUser(string $login = 'streamer_b'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function managePayload(array $overrides = []): array
{
    return array_merge([
        'channel_login' => 'streamer_b',
        'chatter_id' => '11111',
        'chatter_login' => 'somemod',
        'chatter_display_name' => 'SomeMod',
        'badges' => ['moderator'],
        'subject' => 'cmd',
        'action' => 'add',
    ], $overrides);
}

function postManage(array $payload): TestResponse
{
    return test()->postJson(
        '/api/internal/bot/manage',
        $payload,
        ['X-Internal-Secret' => 'test-bot-secret'],
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// cmd: add / edit / delete / options
// ──────────────────────────────────────────────────────────────────────────────

it('cmd add creates a Bot Expression and queues a chat reply', function () {
    $user = adminUser();

    postManage(managePayload([
        'name' => 'lol',
        'payload' => 'HAHA [[[bot:from_user]]]',
    ]))->assertOk();

    expect(BotExpression::where('user_id', $user->id)->where('command', 'lol')->exists())->toBeTrue();
    expect(BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message)->toBe('added !lol');
});

it('strips leading ! from command names so `!ol cmd add !test foo` lands as `test`', function () {
    $user = adminUser();

    // Single bang on cmd add.
    postManage(managePayload([
        'name' => '!greet',
        'payload' => 'hello [[[bot:from_user]]]',
    ]))->assertOk();
    expect(BotExpression::where('user_id', $user->id)->where('command', 'greet')->exists())->toBeTrue();
    expect(BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message)->toBe('added !greet');

    // Double bang on cmd delete - ltrim strips all leading `!` chars.
    postManage(managePayload([
        'action' => 'delete',
        'name' => '!!greet',
    ]))->assertOk();
    expect(BotExpression::where('user_id', $user->id)->where('command', 'greet')->exists())->toBeFalse();

    // Leading bang on alias add - name stripped to plain, target_template's
    // own leading bang stripped by the alias validator.
    postManage(managePayload([
        'subject' => 'alias',
        'action' => 'add',
        'name' => '!w',
        'payload' => '!increment wins {1}',
    ]))->assertOk();
    $alias = BotAlias::where('user_id', $user->id)->where('command', 'w')->first();
    expect($alias)->not->toBeNull();
    expect($alias->target_template)->toBe('increment wins {1}');
});

it('cmd add rejects collision with a builtin', function () {
    $user = adminUser();

    postManage(managePayload([
        'name' => 'reset',
        'payload' => 'denied',
    ]))->assertOk();

    expect(BotExpression::where('user_id', $user->id)->where('command', 'reset')->exists())->toBeFalse();
    expect(BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message)
        ->toStartWith('error:')
        ->toContain('built-in');
});

it('cmd add rejects an expression that starts with a slash command', function () {
    $user = adminUser();

    postManage(managePayload([
        'name' => 'vanish',
        'payload' => '/timeout [[[bot:from_user]]] 1',
    ]))->assertOk();

    expect(BotExpression::where('user_id', $user->id)->where('command', 'vanish')->exists())->toBeFalse();
    expect(BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message)
        ->toStartWith('error:')
        ->toContain("can't start with '/'");
});

it('cmd edit updates expression payload', function () {
    $user = adminUser();
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'discord',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'old text',
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);

    postManage(managePayload([
        'action' => 'edit',
        'name' => 'discord',
        'payload' => 'new text [[[bot:from_user]]]',
    ]))->assertOk();

    expect(BotExpression::where('user_id', $user->id)->where('command', 'discord')->first()?->expression)
        ->toBe('new text [[[bot:from_user]]]');
});

it('cmd delete removes the expression', function () {
    $user = adminUser();
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'gone',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'bye',
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);

    postManage(managePayload([
        'action' => 'delete',
        'name' => 'gone',
    ]))->assertOk();

    expect(BotExpression::where('user_id', $user->id)->where('command', 'gone')->exists())->toBeFalse();
});

it('cmd options sets cooldown', function () {
    $user = adminUser();
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'slow',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'yo',
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);

    postManage(managePayload([
        'action' => 'options',
        'name' => 'slow',
        'option' => 'cooldown',
        'value' => '30',
    ]))->assertOk();

    expect(BotExpression::where('user_id', $user->id)->where('command', 'slow')->first()?->cooldown_seconds)->toBe(30);
});

it('cmd options canonicalises permission shortforms', function () {
    $user = adminUser();
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'mod_only',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'yo',
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);

    postManage(managePayload([
        'action' => 'options',
        'name' => 'mod_only',
        'option' => 'permission',
        'value' => 'mod',
    ]))->assertOk();

    expect(BotExpression::where('user_id', $user->id)->where('command', 'mod_only')->first()?->permission_level)
        ->toBe('moderator');
});

it('cmd options rejects out-of-range cooldown', function () {
    $user = adminUser();
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'slow',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 5,
        'expression' => 'yo',
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);

    postManage(managePayload([
        'action' => 'options',
        'name' => 'slow',
        'option' => 'cooldown',
        'value' => '999999',
    ]))->assertOk();

    expect(BotExpression::where('user_id', $user->id)->where('command', 'slow')->first()?->cooldown_seconds)->toBe(5);
    expect(BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message)
        ->toContain('0 and 86400');
});

it('cmd options destroy sets a self-destruct timer', function () {
    $user = adminUser();
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'temp',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'fleeting',
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);

    postManage(managePayload([
        'action' => 'options',
        'name' => 'temp',
        'option' => 'destroy',
        'value' => '12',
    ]))->assertOk();

    $expr = BotExpression::where('user_id', $user->id)->where('command', 'temp')->first();
    expect($expr->destroy_at)->not->toBeNull();
    expect($expr->destroy_at->between(now()->addHours(12)->subMinute(), now()->addHours(12)->addMinute()))->toBeTrue();
    expect(BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message)
        ->toContain('destroyed in 12h');
});

it('cmd options destroy 0 cancels a pending timer', function () {
    $user = adminUser();
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'temp',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'fleeting',
        'enabled' => true,
        'hidden_from_commands' => false,
        'destroy_at' => now()->addHours(3),
    ]);

    postManage(managePayload([
        'action' => 'options',
        'name' => 'temp',
        'option' => 'destroy',
        'value' => '0',
    ]))->assertOk();

    expect(BotExpression::where('user_id', $user->id)->where('command', 'temp')->first()?->destroy_at)->toBeNull();
    expect(BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message)
        ->toContain('cancelled');
});

it('cmd options destroy rejects out-of-range and non-numeric values', function () {
    $user = adminUser();
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'temp',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'fleeting',
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);

    postManage(managePayload([
        'action' => 'options',
        'name' => 'temp',
        'option' => 'destroy',
        'value' => '99999',
    ]))->assertOk();
    expect(BotExpression::where('user_id', $user->id)->where('command', 'temp')->first()?->destroy_at)->toBeNull();

    postManage(managePayload([
        'action' => 'options',
        'name' => 'temp',
        'option' => 'destroy',
        'value' => 'soon',
    ]))->assertOk();
    expect(BotExpression::where('user_id', $user->id)->where('command', 'temp')->first()?->destroy_at)->toBeNull();
    expect(BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message)
        ->toContain('whole number of hours');
});

it('alias options destroy is refused (commands only)', function () {
    $user = adminUser();
    BotAlias::create([
        'user_id' => $user->id,
        'command' => 'w',
        'target_template' => 'increment wins {1}',
        'permission_level' => 'moderator',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);

    postManage(managePayload([
        'subject' => 'alias',
        'action' => 'options',
        'name' => 'w',
        'option' => 'destroy',
        'value' => '12',
    ]))->assertOk();

    expect(BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message)
        ->toContain('only works on commands');
});

// ──────────────────────────────────────────────────────────────────────────────
// alias: add / delete / options
// ──────────────────────────────────────────────────────────────────────────────

it('alias add creates a Bot Alias', function () {
    $user = adminUser();

    postManage(managePayload([
        'subject' => 'alias',
        'action' => 'add',
        'name' => 'w',
        'payload' => '!increment wins {1}',
    ]))->assertOk();

    $alias = BotAlias::where('user_id', $user->id)->where('command', 'w')->first();
    expect($alias)->not->toBeNull();
    expect($alias->target_template)->toBe('increment wins {1}');
    expect($alias->permission_level)->toBe('moderator');
});

it('alias add rejects self-loop', function () {
    $user = adminUser();

    postManage(managePayload([
        'subject' => 'alias',
        'action' => 'add',
        'name' => 'w',
        'payload' => '!w wins {1}',
    ]))->assertOk();

    expect(BotAlias::where('user_id', $user->id)->where('command', 'w')->exists())->toBeFalse();
    expect(BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message)
        ->toContain('point to itself');
});

it('alias delete removes the alias', function () {
    $user = adminUser();
    BotAlias::create([
        'user_id' => $user->id,
        'command' => 'gone',
        'target_template' => 'increment wins',
        'permission_level' => 'moderator',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);

    postManage(managePayload([
        'subject' => 'alias',
        'action' => 'delete',
        'name' => 'gone',
    ]))->assertOk();

    expect(BotAlias::where('user_id', $user->id)->where('command', 'gone')->exists())->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// list / help / gates
// ──────────────────────────────────────────────────────────────────────────────

it('list returns the user commands as a single chat line', function () {
    $user = adminUser();
    BotExpression::create([
        'user_id' => $user->id,
        'command' => 'discord',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'expression' => 'd',
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);
    BotAlias::create([
        'user_id' => $user->id,
        'command' => 'w',
        'target_template' => 'increment wins {1}',
        'permission_level' => 'moderator',
        'cooldown_seconds' => 0,
        'enabled' => true,
        'hidden_from_commands' => false,
    ]);

    postManage(managePayload([
        'subject' => 'list',
        'action' => '',
    ]))->assertOk();

    $msg = BotChatOutbox::where('user_id', $user->id)->latest('id')->first()?->message;
    expect($msg)->toContain('commands: !discord')->toContain('aliases: !w');
});

it('help returns a usage line', function () {
    adminUser();

    postManage(managePayload([
        'subject' => 'help',
        'action' => '',
    ]))->assertOk();

    expect(BotChatOutbox::latest('id')->first()?->message)->toContain('!ol');
});

it('rejects non-mod chatters', function () {
    $user = adminUser();

    postManage(managePayload([
        'badges' => [],
        'name' => 'denied',
        'payload' => 'noooo',
    ]))->assertOk()->assertJson(['queued' => false, 'reason' => 'gate']);

    expect(BotExpression::where('user_id', $user->id)->where('command', 'denied')->exists())->toBeFalse();
});

it('returns channel_not_found for an unknown login', function () {
    postManage(managePayload([
        'channel_login' => 'nobody_here',
    ]))->assertOk()->assertJson(['queued' => false, 'reason' => 'channel_not_found']);
});

it('refuses requests without the internal secret header', function () {
    test()->postJson('/api/internal/bot/manage', managePayload())
        ->assertStatus(403);
});
