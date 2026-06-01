<?php

use App\Models\BotChatOutbox;
use App\Models\BotCommand;
use App\Models\ListMetaCommand;
use App\Models\ListSnapshot;
use App\Models\OptionSet;
use App\Models\User;
use App\Services\Lists\ListActionService;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['services.twitchbot.listener_secret' => 'test-bot-secret']);
});

function actionUser(string $login = 'streamer_a'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function actionList(User $user, string $slug = 'raffle', array $items = []): OptionSet
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

function fireMetaPayload(array $overrides = []): array
{
    return array_merge([
        'channel_login' => 'streamer_a',
        'command' => 'list',
        'chatter_id' => '12345',
        'chatter_login' => 'somemod',
        'chatter_display_name' => 'SomeMod',
        'badges' => ['moderator'],
        'args' => '',
    ], $overrides);
}

function fireListActionRequest(array $payload): TestResponse
{
    return test()->postJson(
        '/api/internal/bot/list-actions/fire',
        $payload,
        ['X-Internal-Secret' => 'test-bot-secret'],
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Service-level: read actions
// ──────────────────────────────────────────────────────────────────────────────

it('count reports list size', function () {
    $user = actionUser();
    actionList($user, 'raffle', ['a', 'b', 'c']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle count', 'Mod');

    expect($reply)->toBe("'raffle' has 3 entries.");
});

it('first / last / random return one item by default', function () {
    $user = actionUser();
    actionList($user, 'colors', ['red', 'green', 'blue']);

    $svc = app(ListActionService::class);
    expect($svc->handleInvocation($user, 'colors first', 'Mod'))->toBe("First of 'colors': red")
        ->and($svc->handleInvocation($user, 'colors last', 'Mod'))->toBe("Last of 'colors': blue")
        ->and($svc->handleInvocation($user, 'colors random', 'Mod'))->toMatch("/^Random from 'colors': (red|green|blue)$/");
});

it('first / last take a numeric arg', function () {
    $user = actionUser();
    actionList($user, 'pool', ['a', 'b', 'c', 'd', 'e']);

    $svc = app(ListActionService::class);
    expect($svc->handleInvocation($user, 'pool first 3', 'Mod'))->toBe("First 3 of 'pool': a, b, c")
        ->and($svc->handleInvocation($user, 'pool last 2', 'Mod'))->toBe("Last 2 of 'pool': d, e");
});

// ──────────────────────────────────────────────────────────────────────────────
// Service-level: search / searchall
// ──────────────────────────────────────────────────────────────────────────────

it('search returns the newest match, case-insensitively', function () {
    $user = actionUser();
    // Append order = chronology. Last item is "newest".
    actionList($user, 'q', [
        "Yo Jasper what's good!",
        'Hi Jasper how are you doing?',
        'Hello my name is Jasper and this is my quote.',
    ]);

    $reply = app(ListActionService::class)->handleInvocation($user, 'q search jasper', 'Mod');

    expect($reply)->toBe(
        "First search results for 'jasper' in list 'q', searched from new to old: Hello my name is Jasper and this is my quote."
    );
});

it('search joins multi-word keywords', function () {
    $user = actionUser();
    actionList($user, 'q', ['nothing here', 'how are you doing today']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'q search how are', 'Mod');

    expect($reply)->toContain("'how are'")
        ->and($reply)->toContain('how are you doing today');
});

it('search reports no matches cleanly', function () {
    $user = actionUser();
    actionList($user, 'q', ['hello world', 'goodbye world']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'q search zzz', 'Mod');

    expect($reply)->toBe("No matches for 'zzz' in 'q'.");
});

it('search needs a keyword', function () {
    $user = actionUser();
    actionList($user, 'q', ['anything']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'q search', 'Mod');

    expect($reply)->toContain('search needs a keyword')
        ->and($reply)->toContain('@Mod');
});

it('search on empty list is friendly', function () {
    $user = actionUser();
    actionList($user, 'q', []);

    $reply = app(ListActionService::class)->handleInvocation($user, 'q search foo', 'Mod');

    expect($reply)->toBe("'q' is empty.");
});

it('searchall returns all matches numbered newest-first', function () {
    $user = actionUser();
    actionList($user, 'q', [
        "Yo Jasper what's good!",
        'Hi Jasper how are you doing?',
        'Hello my name is Jasper and this is my quote.',
    ]);

    $reply = app(ListActionService::class)->handleInvocation($user, 'q searchall jasper', 'Mod');

    expect($reply)->toBe(
        "All search results for 'jasper' in 'q': [1]Hello my name is Jasper and this is my quote. [2]Hi Jasper how are you doing? [3]Yo Jasper what's good!"
    );
});

it('searchall caps at MAX_REPLY_CHARS and appends "+N more"', function () {
    $user = actionUser();
    // 20 entries each containing "kw", each ~30 chars - well over 400 chars total.
    $entries = [];
    for ($i = 1; $i <= 20; $i++) {
        $entries[] = "entry $i with kw inside text";
    }
    actionList($user, 'q', $entries);

    $reply = app(ListActionService::class)->handleInvocation($user, 'q searchall kw', 'Mod');

    expect(mb_strlen($reply))->toBeLessThanOrEqual(400)
        ->and($reply)->toContain("All search results for 'kw'")
        ->and($reply)->toMatch('/\(\+\d+ more\)$/');
});

it('searchall reports no matches cleanly', function () {
    $user = actionUser();
    actionList($user, 'q', ['hello world']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'q searchall zzz', 'Mod');

    expect($reply)->toBe("No matches for 'zzz' in 'q'.");
});

it('searchall needs a keyword', function () {
    $user = actionUser();
    actionList($user, 'q', ['anything']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'q searchall', 'Mod');

    expect($reply)->toContain('searchall needs a keyword');
});

// ──────────────────────────────────────────────────────────────────────────────
// Per-action permission gate (chat_permissions overrides)
// ──────────────────────────────────────────────────────────────────────────────

it('denies a default mod+ action when a viewer (empty badges) runs it', function () {
    $user = actionUser();
    actionList($user, 'raffle', ['a']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle count', 'Viewer', []);

    expect($reply)->toBe("@Viewer - 'count' on 'raffle' is moderator+ only.");
});

it('runs an action a streamer has opened up to everyone for this list', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a', 'b']);
    $list->update(['chat_permissions' => ['count' => 'everyone']]);

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle count', 'Viewer', []);

    expect($reply)->toBe("'raffle' has 2 entries.");
});

it('still gates other actions on the same list after one is opened', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a', 'b']);
    $list->update(['chat_permissions' => ['count' => 'everyone']]);

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle clear', 'Viewer', []);

    expect($reply)->toContain("'clear' on 'raffle' is moderator+ only.");
});

it('bypasses the gate when badges is null (dashboard path)', function () {
    $user = actionUser();
    actionList($user, 'raffle', ['a', 'b']);

    // No badges passed - service treats this as the trusted owner path
    // (used by ListActionWebController). Even a mod-only action fires.
    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle clear', 'Streamer', null);

    expect($reply)->toContain("Cleared 'raffle'");
});

it('lets unknown-action help through regardless of permission', function () {
    $user = actionUser();
    actionList($user, 'raffle', ['a']);

    // A viewer typing `!list raffle wat` should see the help text, not a
    // permission-denied message. Action lookup happens before the gate.
    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle wat', 'Viewer', []);

    expect($reply)->toContain("'wat' isn't a valid action");
});

it('resolveAllPermissions merges stored overrides over defaults', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle');
    $list->update(['chat_permissions' => ['count' => 'everyone', 'random' => 'vip']]);

    $resolved = app(ListActionService::class)->resolveAllPermissions($list->fresh());

    expect($resolved['count'])->toBe('everyone')
        ->and($resolved['random'])->toBe('vip')
        ->and($resolved['clear'])->toBe('moderator')
        ->and($resolved)->toHaveCount(count(ListActionService::ACTION_DEFAULTS));
});

// ──────────────────────────────────────────────────────────────────────────────
// Service-level: help messages
// ──────────────────────────────────────────────────────────────────────────────

it('bare !list returns the global help message', function () {
    $user = actionUser();

    $reply = app(ListActionService::class)->handleInvocation($user, '', 'Mod');

    expect($reply)->toContain('List actions:')
        ->and($reply)->toContain('@Mod')
        ->and($reply)->toContain('Usage: !list <slug> <action>');
});

it('slug-only returns the per-list help message', function () {
    $user = actionUser();
    actionList($user, 'raffle');

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle', 'Mod');

    expect($reply)->toContain("Actions for 'raffle'")
        ->and($reply)->toContain('draw, clear');
});

it('unknown action returns help with the action list', function () {
    $user = actionUser();
    actionList($user, 'raffle');

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle floof', 'Mod');

    expect($reply)->toContain("'floof' isn't a valid action");
});

it('unknown slug returns a helpful error', function () {
    $user = actionUser();

    $reply = app(ListActionService::class)->handleInvocation($user, 'does_not_exist count', 'Mod');

    expect($reply)->toContain("no list named 'does_not_exist'");
});

// ──────────────────────────────────────────────────────────────────────────────
// Service-level: destructive actions create snapshots
// ──────────────────────────────────────────────────────────────────────────────

it('draw picks a winner, removes them, and snapshots before', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['alice', 'bob']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle draw', 'Mod');

    expect($reply)->toMatch("/^🎰 Winner of 'raffle': (alice|bob)$/")
        ->and(count($list->fresh()->items))->toBe(1)
        ->and(ListSnapshot::where('list_id', $list->id)->where('reason', 'before_draw')->count())->toBe(1);
});

it('clear empties + snapshots', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a', 'b', 'c']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle clear', 'Mod');

    expect($reply)->toContain('Cleared')
        ->and($list->fresh()->items)->toBe([])
        ->and(ListSnapshot::where('list_id', $list->id)->where('reason', 'before_clear')->count())->toBe(1);
});

it('pop requires first or last; bare pop errors with help', function () {
    $user = actionUser();
    actionList($user, 'raffle', ['a', 'b']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle pop', 'Mod');

    expect($reply)->toContain('pop needs first or last');
});

it('pop first removes the head and snapshots', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a', 'b', 'c']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle pop first', 'Mod');

    expect($reply)->toBe("Popped first from 'raffle': a")
        ->and(ListItems::values($list->fresh()->items))->toBe(['b', 'c'])
        ->and(ListSnapshot::where('list_id', $list->id)->where('reason', 'before_pop')->count())->toBe(1);
});

it('pop last removes the tail', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a', 'b', 'c']);

    app(ListActionService::class)->handleInvocation($user, 'raffle pop last', 'Mod');

    expect(ListItems::values($list->fresh()->items))->toBe(['a', 'b']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Service-level: clone
// ──────────────────────────────────────────────────────────────────────────────

it('clone needs a new slug; bare clone errors', function () {
    $user = actionUser();
    actionList($user, 'src', ['a']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'src clone', 'Mod');

    expect($reply)->toContain('clone needs a new slug');
});

it('clone creates a new list with the same items and inherits the label verbatim', function () {
    $user = actionUser();
    $src = actionList($user, 'src', ['a', 'b', 'c']);
    $src->update(['label' => 'My Pizza List']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'src clone snap1', 'Mod');

    $clone = OptionSet::where('user_id', $user->id)->where('slug', 'snap1')->first();
    expect($reply)->toBe("Cloned 'src' to 'snap1' (3 items).")
        ->and($clone)->not->toBeNull()
        ->and(ListItems::values($clone->items))->toBe(['a', 'b', 'c'])
        // Label inherited verbatim - no "Copy of" prefix. Streamer
        // already picked a unique slug; auto-prefixing creates rename chores.
        ->and($clone->label)->toBe('My Pizza List')
        ->and(ListItems::values($src->fresh()->items))->toBe(['a', 'b', 'c']); // source untouched
});

it('clone refuses an already-used slug', function () {
    $user = actionUser();
    actionList($user, 'src', ['a']);
    actionList($user, 'taken', ['x']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'src clone taken', 'Mod');

    expect($reply)->toContain("you already have a list named 'taken'");
});

// ──────────────────────────────────────────────────────────────────────────────
// Service-level: state actions
// ──────────────────────────────────────────────────────────────────────────────

it('disable + enable toggle disabled_at', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a']);
    $svc = app(ListActionService::class);

    expect($list->disabled_at)->toBeNull();

    $svc->handleInvocation($user, 'raffle disable', 'Mod');
    expect($list->fresh()->disabled_at)->not->toBeNull();

    $svc->handleInvocation($user, 'raffle enable', 'Mod');
    expect($list->fresh()->disabled_at)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Service-level: empty-list edge cases
// ──────────────────────────────────────────────────────────────────────────────

it('draw on empty list returns a friendly message', function () {
    $user = actionUser();
    actionList($user, 'empty_pool', []);

    $reply = app(ListActionService::class)->handleInvocation($user, 'empty_pool draw', 'Mod');

    expect($reply)->toContain("Can't draw")
        ->and($reply)->toContain('empty');
});

// ──────────────────────────────────────────────────────────────────────────────
// Bot internal API
// ──────────────────────────────────────────────────────────────────────────────

it('fire endpoint queues the reply to bot_chat_outbox', function () {
    $user = actionUser('streamer_a');
    actionList($user, 'raffle', ['a', 'b']);
    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    fireListActionRequest(fireMetaPayload(['args' => 'raffle count']))
        ->assertOk()
        ->assertJson(['fired' => true]);

    $msg = BotChatOutbox::where('user_id', $user->id)->latest()->first();
    expect($msg->message)->toBe("'raffle' has 2 entries.");
});

it('fire endpoint replies with a friendly mods-only message for default-permission actions', function () {
    $user = actionUser('streamer_a');
    actionList($user, 'raffle', ['a']);
    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    // Default chat_permissions = NULL = everything mod+. Viewer-tier
    // badges (empty) trying `count` get a per-action denial reply, not
    // the old endpoint-level `gate` rejection.
    $resp = fireListActionRequest(fireMetaPayload(['badges' => [], 'args' => 'raffle count']))
        ->assertOk()
        ->assertJson(['fired' => true]);

    $msg = BotChatOutbox::where('user_id', $user->id)->latest()->first();
    expect($msg->message)->toContain("'count' on 'raffle' is moderator+ only.");
});

it('fire endpoint runs an action a viewer has been opened up to per list', function () {
    $user = actionUser('streamer_a');
    $list = actionList($user, 'raffle', ['a', 'b', 'c']);
    $list->update(['chat_permissions' => ['count' => 'everyone']]);
    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    fireListActionRequest(fireMetaPayload(['badges' => [], 'args' => 'raffle count']))
        ->assertOk()
        ->assertJson(['fired' => true]);

    $msg = BotChatOutbox::where('user_id', $user->id)->latest()->first();
    expect($msg->message)->toBe("'raffle' has 3 entries.");
});

it('fire endpoint accepts broadcaster', function () {
    $user = actionUser('streamer_a');
    actionList($user, 'raffle', ['a']);
    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    fireListActionRequest(fireMetaPayload(['badges' => ['broadcaster'], 'args' => 'raffle count']))
        ->assertOk()
        ->assertJson(['fired' => true]);
});

it('fire endpoint returns meta_not_found when user has not opted in', function () {
    actionUser('streamer_a');
    // No ListMetaCommand row

    fireListActionRequest(fireMetaPayload(['args' => 'whatever']))
        ->assertOk()
        ->assertJson(['fired' => false, 'reason' => 'meta_not_found']);
});

// ──────────────────────────────────────────────────────────────────────────────
// CommandMap surface
// ──────────────────────────────────────────────────────────────────────────────

it('exposes the meta-command in /api/internal/bot/commands with type=list_meta', function () {
    $user = actionUser('streamer_map');
    ListMetaCommand::create(['user_id' => $user->id, 'command' => 'list', 'enabled' => true]);

    $resp = test()->get('/api/internal/bot/commands', ['X-Internal-Secret' => 'test-bot-secret']);

    $entries = collect($resp->json('channels.streamer_map'));
    $list = $entries->firstWhere('command', 'list');

    expect($list)->not->toBeNull()
        ->and($list['type'])->toBe('list_meta')
        // 'everyone' so the bot relays every chatter's !list to us;
        // per-action gating runs in ListActionService against the
        // target list's chat_permissions overrides.
        ->and($list['permission_level'])->toBe('everyone');
});

// ──────────────────────────────────────────────────────────────────────────────
// Web endpoints: actions + snapshots
// ──────────────────────────────────────────────────────────────────────────────

it('web action endpoint runs the same vocabulary', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a', 'b', 'c']);

    $this->actingAs($user)->postJson("/dashboard/lists/{$list->id}/actions", [
        'action' => 'count',
    ])->assertOk()->assertJson(['reply' => "'raffle' has 3 entries."]);
});

it('web action endpoint can draw and the list shrinks', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a', 'b']);

    $this->actingAs($user)->postJson("/dashboard/lists/{$list->id}/actions", [
        'action' => 'draw',
    ])->assertOk();

    expect(count($list->fresh()->items))->toBe(1);
});

it('web action endpoint refuses foreign user (404)', function () {
    $owner = actionUser();
    $intruder = actionUser('streamer_b');
    $list = actionList($owner, 'mine', ['a']);

    $this->actingAs($intruder)->postJson("/dashboard/lists/{$list->id}/actions", [
        'action' => 'count',
    ])->assertNotFound();
});

it('snapshots endpoint lists recent snapshots', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a', 'b']);
    app(ListActionService::class)->handleInvocation($user, 'raffle clear', 'Mod');

    $this->actingAs($user)->getJson("/dashboard/lists/{$list->id}/snapshots")
        ->assertOk()
        ->assertJsonPath('snapshots.0.reason', 'before_clear')
        ->assertJsonPath('snapshots.0.item_count', 2);
});

it('snapshot restore writes the snapshot items back and snapshots first', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a', 'b', 'c']);

    // Clear creates the snapshot
    app(ListActionService::class)->handleInvocation($user, 'raffle clear', 'Mod');
    $snap = ListSnapshot::where('list_id', $list->id)->where('reason', 'before_clear')->first();

    // Restore - should produce a before_restore snapshot AND bring back items
    $this->actingAs($user)->postJson("/dashboard/lists/{$list->id}/snapshots/{$snap->id}/restore")
        ->assertOk();

    expect(ListItems::values($list->fresh()->items))->toBe(['a', 'b', 'c'])
        ->and(ListSnapshot::where('list_id', $list->id)->where('reason', 'before_restore')->count())->toBe(1);
});

it('snapshot pin toggles the pinned flag', function () {
    $user = actionUser();
    $list = actionList($user, 'raffle', ['a']);
    app(ListActionService::class)->handleInvocation($user, 'raffle clear', 'Mod');
    $snap = ListSnapshot::where('list_id', $list->id)->first();

    $this->actingAs($user)->patchJson("/dashboard/lists/{$list->id}/snapshots/{$snap->id}/pin")
        ->assertOk()
        ->assertJson(['pinned' => true]);

    expect($snap->fresh()->pinned)->toBeTrue();
});

it('meta-command endpoint creates and updates', function () {
    $user = actionUser();

    $this->actingAs($user)->putJson('/dashboard/lists/meta-command', [
        'command' => 'queue',
        'enabled' => true,
    ])->assertOk()->assertJson(['meta' => ['command' => 'queue', 'enabled' => true]]);

    expect(ListMetaCommand::where('user_id', $user->id)->first()->command)->toBe('queue');

    // Update
    $this->actingAs($user)->putJson('/dashboard/lists/meta-command', [
        'command' => 'l',
        'enabled' => false,
    ])->assertOk()->assertJson(['meta' => ['command' => 'l', 'enabled' => false]]);
});

it('meta-command rejects collisions with existing commands', function () {
    $user = actionUser();
    // Use a non-default command name to avoid the unique-key clash
    // with whatever the BotCommand seeder/observer auto-creates for
    // new bot-enabled users. The collision check itself is what we're
    // testing here, not the row creation.
    BotCommand::create([
        'user_id' => $user->id,
        'command' => 'mycustomcmd',
        'permission_level' => 'everyone',
        'enabled' => true,
    ]);

    $this->actingAs($user)->putJson('/dashboard/lists/meta-command', [
        'command' => 'mycustomcmd',
    ])->assertStatus(422);
});
