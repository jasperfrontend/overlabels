<?php

use App\Events\ListUpdated;
use App\Models\BotChatOutbox;
use App\Models\BotCommand;
use App\Models\ListAppender;
use App\Models\ListAppendHistory;
use App\Models\OptionSet;
use App\Models\User;
use App\Services\Lists\ListAppendService;
use App\Services\TwitchApiService;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);

    // Stub Twitch fetch so the resolver doesn't make HTTP calls during template resolution.
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

function appenderUser(string $login = 'streamer_a'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function appenderList(User $user, string $slug = 'raffle_pool', array $items = []): OptionSet
{
    $built = ListItems::freshFromValues($items, 1);

    return OptionSet::create([
        'user_id' => $user->id,
        'slug' => $slug,
        'items' => $built['items'],
        'next_item_id' => $built['next_id'],
        'min_items' => 0,
        'user_editable' => true,
    ]);
}

function appenderRow(User $user, OptionSet $list, array $overrides = []): ListAppender
{
    return ListAppender::create(array_merge([
        'user_id' => $user->id,
        'target_list_id' => $list->id,
        'command' => 'raffle',
        'value_template' => '[[[bot:from_user]]]',
        'dedup_policy' => ListAppender::DEDUP_PER_CHATTER,
        'max_size' => null,
        'enabled' => true,
    ], $overrides));
}

function appenderFirePayload(array $overrides = []): array
{
    return array_merge([
        'channel_login' => 'streamer_a',
        'command' => 'raffle',
        'chatter_id' => '12345',
        'chatter_login' => 'alice',
        'chatter_display_name' => 'Alice',
        'badges' => [],
        'args' => '',
    ], $overrides);
}

function fireAppenderRequest(array $payload): TestResponse
{
    return test()->postJson(
        '/api/internal/bot/list-appenders/fire',
        $payload,
        ['X-Internal-Secret' => 'test-bot-secret'],
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Service-level
// ──────────────────────────────────────────────────────────────────────────────

it('appends a resolved value to the list and writes history', function () {
    Event::fake([ListUpdated::class]);

    $user = appenderUser();
    $list = appenderList($user);
    $appender = appenderRow($user, $list);

    $result = app(ListAppendService::class)->fire($appender, $user, appenderFirePayload());

    expect($result['fired'])->toBeTrue()
        ->and($result['value'])->toBe('Alice')
        ->and(ListItems::values($list->fresh()->items))->toBe(['Alice'])
        ->and(ListAppendHistory::where('list_appender_id', $appender->id)->count())->toBe(1);

    Event::assertDispatched(ListUpdated::class, fn (ListUpdated $e) => $e->slug === 'raffle_pool' && ListItems::values($e->items) === ['Alice']);
});

it('deduplicates per chatter when dedup_policy = per_chatter', function () {
    $user = appenderUser();
    $list = appenderList($user);
    $appender = appenderRow($user, $list, ['dedup_policy' => ListAppender::DEDUP_PER_CHATTER]);

    $svc = app(ListAppendService::class);
    expect($svc->fire($appender, $user, appenderFirePayload())['fired'])->toBeTrue();
    $r2 = $svc->fire($appender, $user, appenderFirePayload());

    expect($r2['fired'])->toBeFalse()
        ->and($r2['reason'])->toBe('already_in_list')
        ->and(ListItems::values($list->fresh()->items))->toBe(['Alice']);
});

it('allows duplicates when dedup_policy = none', function () {
    $user = appenderUser();
    $list = appenderList($user);
    $appender = appenderRow($user, $list, ['dedup_policy' => ListAppender::DEDUP_NONE]);

    $svc = app(ListAppendService::class);
    $svc->fire($appender, $user, appenderFirePayload());
    $svc->fire($appender, $user, appenderFirePayload());

    expect(ListItems::values($list->fresh()->items))->toBe(['Alice', 'Alice']);
});

it('refuses silently when the list is at max_size', function () {
    $user = appenderUser();
    $list = appenderList($user, items: ['prefilled']);
    $appender = appenderRow($user, $list, ['max_size' => 1]);

    $result = app(ListAppendService::class)->fire($appender, $user, appenderFirePayload());

    expect($result['fired'])->toBeFalse()
        ->and($result['reason'])->toBe('list_full')
        ->and(ListItems::values($list->fresh()->items))->toBe(['prefilled']);
});

it('queues args_empty_reply to bot_chat_outbox when args required but empty', function () {
    $user = appenderUser();
    $list = appenderList($user);
    $appender = appenderRow($user, $list, [
        'value_template' => '[[[bot:args]]]',
        'args_empty_reply' => '@[[[bot:from_user]]] add something after !raffle, like !raffle salami',
    ]);

    $result = app(ListAppendService::class)->fire($appender, $user, appenderFirePayload(['args' => '']));

    expect($result['fired'])->toBeFalse()
        ->and($result['reason'])->toBe('args_empty')
        ->and(BotChatOutbox::where('user_id', $user->id)->latest()->first()->message)
        ->toBe('@Alice add something after !raffle, like !raffle salami');
});

it('is silent on empty args when no args_empty_reply is set', function () {
    $user = appenderUser();
    $list = appenderList($user);
    $appender = appenderRow($user, $list, [
        'value_template' => '[[[bot:args]]]',
        'args_empty_reply' => null,
    ]);
    $outboxBefore = BotChatOutbox::where('user_id', $user->id)->count();

    $result = app(ListAppendService::class)->fire($appender, $user, appenderFirePayload(['args' => '']));

    expect($result['fired'])->toBeFalse()
        ->and($result['reason'])->toBe('args_empty')
        ->and(BotChatOutbox::where('user_id', $user->id)->count())->toBe($outboxBefore);
});

it('resolves a value_template that has multiple bot tags', function () {
    $user = appenderUser();
    $list = appenderList($user);
    $appender = appenderRow($user, $list, [
        'value_template' => '[[[bot:from_user]]]: [[[bot:args]]]',
        'dedup_policy' => ListAppender::DEDUP_NONE,
    ]);

    $result = app(ListAppendService::class)->fire($appender, $user, appenderFirePayload([
        'args' => 'hello world',
    ]));

    expect($result['value'])->toBe('Alice: hello world');
});

// ──────────────────────────────────────────────────────────────────────────────
// Internal API
// ──────────────────────────────────────────────────────────────────────────────

it('fire endpoint routes through the service and appends', function () {
    $user = appenderUser();
    $list = appenderList($user);
    appenderRow($user, $list);

    fireAppenderRequest(appenderFirePayload())
        ->assertOk()
        ->assertJson(['fired' => true, 'value' => 'Alice']);

    expect(ListItems::values($list->fresh()->items))->toBe(['Alice']);
});

it('fire endpoint returns channel_not_found for unknown channel', function () {
    fireAppenderRequest(appenderFirePayload(['channel_login' => 'no_such_channel']))
        ->assertOk()
        ->assertJson(['fired' => false, 'reason' => 'channel_not_found']);
});

it('fire endpoint returns appender_not_found for unknown command', function () {
    appenderUser();
    fireAppenderRequest(appenderFirePayload(['command' => 'doesnotexist']))
        ->assertOk()
        ->assertJson(['fired' => false, 'reason' => 'appender_not_found']);
});

it('fire endpoint gates by permission', function () {
    $user = appenderUser();
    $list = appenderList($user);
    appenderRow($user, $list, ['permission_level' => 'moderator']);

    fireAppenderRequest(appenderFirePayload(['badges' => []]))
        ->assertOk()
        ->assertJson(['fired' => false, 'reason' => 'gate']);

    fireAppenderRequest(appenderFirePayload([
        'chatter_id' => '999',
        'chatter_login' => 'mod',
        'chatter_display_name' => 'Mod',
        'badges' => ['moderator'],
    ]))
        ->assertOk()
        ->assertJson(['fired' => true]);
});

it('fire endpoint refuses without X-Internal-Secret header', function () {
    test()->postJson('/api/internal/bot/list-appenders/fire', appenderFirePayload())
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// commandMap surface
// ──────────────────────────────────────────────────────────────────────────────

it('surfaces appenders with type=list_append in /api/internal/bot/commands', function () {
    $user = appenderUser('streamer_map');
    $list = appenderList($user);
    appenderRow($user, $list, ['command' => 'raffle']);

    $resp = test()->get('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk();

    $entries = collect($resp->json('channels.streamer_map'));
    $raffle = $entries->firstWhere('command', 'raffle');

    expect($raffle)->not->toBeNull()
        ->and($raffle['type'])->toBe('list_append')
        ->and($raffle['permission_level'])->toBe('everyone');
});

it('builtin wins on command-name collision with an appender', function () {
    $user = appenderUser('streamer_collide');
    $list = appenderList($user);
    appenderRow($user, $list, ['command' => 'raffle']);
    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'raffle',
        'permission_level' => 'broadcaster',
        'enabled' => true,
    ]);

    $resp = test()->get('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret'])
        ->assertOk();

    $entries = collect($resp->json('channels.streamer_collide'));
    expect($entries->where('command', 'raffle'))->toHaveCount(1);
    expect($entries->firstWhere('command', 'raffle')['type'])->toBe('builtin');
});
