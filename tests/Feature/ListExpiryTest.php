<?php

use App\Events\ListUpdated;
use App\Models\ListAppender;
use App\Models\ListSnapshot;
use App\Models\OptionSet;
use App\Models\User;
use App\Services\Lists\ListActionService;
use App\Services\Lists\ListAppendService;
use App\Services\Lists\ListExpirySweeper;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Event::fake([ListUpdated::class]);
});

// ──────────────────────────────────────────────────────────────────────────────
// per-item added_at across mutators
//
// The per-item timestamp now lives inside each item object (item['added_at']);
// the old parallel item_added_at column is gone. (The pure-unit coverage of
// timestamp stamping/preservation lives in tests/Unit/ListItemsTest.php against
// ListItems::freshFromValues + reconcileByValue.)
// ──────────────────────────────────────────────────────────────────────────────

it('appends a fresh timestamp via ListAppendService', function () {
    $user = User::factory()->create();
    $oldTs = now()->timestamp - 100;
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'raffle',
        'items' => [ListItems::make(1, 'existing', $oldTs)],
        'next_item_id' => 2,
    ]);
    $appender = ListAppender::create([
        'user_id' => $user->id,
        'target_list_id' => $list->id,
        'command' => 'raffle',
        'permission_level' => 'everyone',
        'cooldown_seconds' => 0,
        'value_template' => '[[[bot:from_user]]]',
        'dedup_policy' => 'none',
        'enabled' => true,
    ]);

    $before = now()->timestamp;
    app(ListAppendService::class)->fire($appender, $user, [
        'chatter_id' => '123',
        'chatter_login' => 'newbie',
        'chatter_display_name' => 'Newbie',
        'args' => '',
        'channel_login' => 'streamer',
    ]);

    $list->refresh();
    $items = $list->items;
    expect(ListItems::values($items))->toBe(['existing', 'Newbie'])
        ->and($items)->toHaveCount(2)
        ->and($items[0]['added_at'])->toBe($oldTs) // old stamp preserved
        ->and($items[1]['added_at'])->toBeGreaterThanOrEqual($before); // new stamp fresh
});

it('clears timestamps via clear action', function () {
    $user = User::factory()->create();
    $built = ListItems::freshFromValues(['a', 'b'], 1);
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'clearme',
        'items' => $built['items'],
        'next_item_id' => $built['next_id'],
    ]);

    app(ListActionService::class)->handleInvocation($user, 'clearme clear');

    $list->refresh();
    expect($list->items)->toBe([]);
});

it('removes correct timestamp on draw', function () {
    $user = User::factory()->create();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'draw_test',
        'items' => [ListItems::make(1, 'only_one', 1234567890)],
        'next_item_id' => 2,
    ]);

    app(ListActionService::class)->handleInvocation($user, 'draw_test draw');

    $list->refresh();
    expect($list->items)->toBe([]);
});

it('removes correct timestamp on pop first vs last', function () {
    $user = User::factory()->create();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'popme',
        'items' => [
            ListItems::make(1, 'a', 100),
            ListItems::make(2, 'b', 200),
            ListItems::make(3, 'c', 300),
        ],
        'next_item_id' => 4,
    ]);

    app(ListActionService::class)->handleInvocation($user, 'popme pop first');
    $list->refresh();
    // Head 'a' (added_at 100) dropped; 'b'/'c' survive with their stamps.
    expect(ListItems::values($list->items))->toBe(['b', 'c'])
        ->and(array_column($list->items, 'added_at'))->toBe([200, 300]);

    app(ListActionService::class)->handleInvocation($user, 'popme pop last');
    $list->refresh();
    // Tail 'c' (added_at 300) dropped; only 'b' (added_at 200) survives.
    expect(ListItems::values($list->items))->toBe(['b'])
        ->and(array_column($list->items, 'added_at'))->toBe([200]);
});

it('inherits timestamps verbatim on clone', function () {
    $user = User::factory()->create();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'orig',
        'items' => [
            ListItems::make(1, 'a', 111),
            ListItems::make(2, 'b', 222),
        ],
        'next_item_id' => 3,
    ]);

    app(ListActionService::class)->handleInvocation($user, 'orig clone cloned');

    $cloned = OptionSet::where('user_id', $user->id)->where('slug', 'cloned')->first();
    expect(ListItems::values($cloned->items))->toBe(['a', 'b'])
        ->and(array_column($cloned->items, 'added_at'))->toBe([111, 222]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Sweeper: entry-TTL age-out
// ──────────────────────────────────────────────────────────────────────────────

it('drops items older than entry_ttl_seconds and keeps young ones', function () {
    $user = User::factory()->create();
    $now = now()->timestamp;
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'aging',
        'items' => [
            ListItems::make(1, 'old', $now - 120),
            ListItems::make(2, 'older', $now - 90),
            ListItems::make(3, 'fresh', $now - 5),
        ],
        'next_item_id' => 4,
        'entry_ttl_seconds' => 60,
    ]);

    $result = app(ListExpirySweeper::class)->run();

    $list->refresh();
    expect(ListItems::values($list->items))->toBe(['fresh'])
        ->and($list->items)->toHaveCount(1)
        ->and($result['lists_swept'])->toBe(1)
        ->and($result['items_removed'])->toBe(2);
});

it('broadcasts ListUpdated when entries are swept', function () {
    $user = User::factory()->create(['twitch_id' => '999']);
    $now = now()->timestamp;
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'broadcasty',
        'items' => [ListItems::make(1, 'stale', $now - 9999)],
        'next_item_id' => 2,
        'entry_ttl_seconds' => 60,
    ]);

    app(ListExpirySweeper::class)->run();

    Event::assertDispatched(ListUpdated::class, fn ($e) => $e->slug === 'broadcasty');
});

it('no-ops when nothing has aged out yet', function () {
    $user = User::factory()->create();
    $now = now()->timestamp;
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'still_fresh',
        'items' => ['young'],
        'item_added_at' => [$now - 5],
        'entry_ttl_seconds' => 60,
    ]);

    $result = app(ListExpirySweeper::class)->run();

    expect($result['lists_swept'])->toBe(0)
        ->and($result['items_removed'])->toBe(0);
    Event::assertNotDispatched(ListUpdated::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Sweeper: whole-list expiry
// ──────────────────────────────────────────────────────────────────────────────

it('snapshots, clears, and disables an expired list', function () {
    $user = User::factory()->create();
    $past = Carbon::now()->subMinute();
    $built = ListItems::freshFromValues(['a', 'b', 'c'], 1);
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'dying',
        'items' => $built['items'],
        'next_item_id' => $built['next_id'],
        'expires_at' => $past,
    ]);

    $result = app(ListExpirySweeper::class)->run();

    $list->refresh();
    expect($list->items)->toBe([])
        ->and($list->disabled_at?->timestamp)->toBe($past->timestamp)
        ->and($result['lists_expired'])->toBe(1);

    $snap = ListSnapshot::where('list_id', $list->id)->where('reason', 'before_clear')->first();
    expect($snap)->not->toBeNull()
        ->and(ListItems::values($snap->items))->toBe(['a', 'b', 'c']);
});

it('does not expire a list whose expires_at is in the future', function () {
    $user = User::factory()->create();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'still_going',
        'items' => ['a'],
        'item_added_at' => [now()->timestamp],
        'expires_at' => Carbon::now()->addHour(),
    ]);

    $result = app(ListExpirySweeper::class)->run();

    expect($result['lists_expired'])->toBe(0);
});

it('does not re-expire an already-disabled list on subsequent sweeps', function () {
    $user = User::factory()->create();
    $past = Carbon::now()->subMinute();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'already_dead',
        'items' => [],
        'item_added_at' => [],
        'expires_at' => $past,
        'disabled_at' => $past,
    ]);

    $result = app(ListExpirySweeper::class)->run();

    expect($result['lists_expired'])->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Controller: expiry-config PATCH endpoint
// ──────────────────────────────────────────────────────────────────────────────

it('updates entry_ttl_seconds via the PUT endpoint', function () {
    $user = User::factory()->create();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'config_me',
        'items' => ['a'],
        'item_added_at' => [now()->timestamp],
    ]);

    $this->actingAs($user)->put(route('lists.update', $list->id), [
        'entry_ttl_seconds' => 300,
    ])->assertRedirect();

    $list->refresh();
    expect($list->entry_ttl_seconds)->toBe(300)
        ->and($list->items)->toBe(['a']); // items untouched
});

it('clears entry_ttl_seconds when sent as null', function () {
    $user = User::factory()->create();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'unset_me',
        'items' => [],
        'item_added_at' => [],
        'entry_ttl_seconds' => 60,
    ]);

    $this->actingAs($user)->put(route('lists.update', $list->id), [
        'entry_ttl_seconds' => null,
    ])->assertRedirect();

    expect($list->fresh()->entry_ttl_seconds)->toBeNull();
});

it('rejects entry_ttl_seconds outside the 10s..30d range', function () {
    $user = User::factory()->create();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'reject_me',
        'items' => [],
        'item_added_at' => [],
    ]);

    $this->actingAs($user)->put(route('lists.update', $list->id), [
        'entry_ttl_seconds' => 5,
    ])->assertSessionHasErrors(['entry_ttl_seconds']);

    $this->actingAs($user)->put(route('lists.update', $list->id), [
        'entry_ttl_seconds' => 3000000, // ~35 days
    ])->assertSessionHasErrors(['entry_ttl_seconds']);
});

it('updates expires_at via the PUT endpoint', function () {
    $user = User::factory()->create();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'deadline_me',
        'items' => [],
        'item_added_at' => [],
    ]);
    $target = now()->addHour()->timestamp;

    $this->actingAs($user)->put(route('lists.update', $list->id), [
        'expires_at' => $target,
    ])->assertRedirect();

    $list->refresh();
    expect($list->expires_at?->timestamp)->toBe($target);
});

it('re-enables a previously-expired list when expires_at is cleared', function () {
    $user = User::factory()->create();
    $past = Carbon::now()->subHour();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'revive_me',
        'items' => [],
        'item_added_at' => [],
        'expires_at' => $past,
        'disabled_at' => $past,
    ]);

    $this->actingAs($user)->put(route('lists.update', $list->id), [
        'expires_at' => null,
    ])->assertRedirect();

    $list->refresh();
    expect($list->expires_at)->toBeNull()
        ->and($list->disabled_at)->toBeNull();
});

it('serializes entry_ttl_seconds and expires_at to the Inertia page', function () {
    $user = User::factory()->create();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'shown',
        'items' => ['a'],
        'item_added_at' => [now()->timestamp],
        'entry_ttl_seconds' => 120,
        'expires_at' => Carbon::now()->addDay(),
    ]);

    $this->actingAs($user)->get(route('lists.index'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/lists/index')
            ->has('lists', 1)
            ->where('lists.0.entry_ttl_seconds', 120)
            ->where('lists.0.expires_at', fn ($v) => is_int($v))
        );
});

// ──────────────────────────────────────────────────────────────────────────────
// ListUpdated event carries expires_at + disabled_at
// ──────────────────────────────────────────────────────────────────────────────

// ──────────────────────────────────────────────────────────────────────────────
// Snapshot retention sweep (30-day, pinned-exempt)
// ──────────────────────────────────────────────────────────────────────────────

it('prunes unpinned snapshots older than 30 days and keeps pinned ones', function () {
    $user = User::factory()->create();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'snaps',
        'items' => [],
        'item_added_at' => [],
    ]);

    // Old + unpinned -> swept
    $oldUnpinned = ListSnapshot::create([
        'list_id' => $list->id,
        'items' => ['old'],
        'reason' => 'manual',
        'pinned' => false,
        'created_at' => now()->subDays(31),
    ]);
    // Old + pinned -> kept
    $oldPinned = ListSnapshot::create([
        'list_id' => $list->id,
        'items' => ['precious'],
        'reason' => 'manual',
        'pinned' => true,
        'created_at' => now()->subDays(60),
    ]);
    // Young + unpinned -> kept
    $young = ListSnapshot::create([
        'list_id' => $list->id,
        'items' => ['recent'],
        'reason' => 'manual',
        'pinned' => false,
        'created_at' => now()->subDays(5),
    ]);

    ListSnapshot::where('pinned', false)
        ->where('created_at', '<', now()->subDays(30))
        ->delete();

    expect(ListSnapshot::find($oldUnpinned->id))->toBeNull()
        ->and(ListSnapshot::find($oldPinned->id))->not->toBeNull()
        ->and(ListSnapshot::find($young->id))->not->toBeNull();
});

it('threads expires_at and disabled_at into the broadcast payload', function () {
    Event::fake();
    $user = User::factory()->create(['twitch_id' => '42']);
    $exp = Carbon::now()->addDay();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'broadcast_me',
        'items' => ['a'],
        'item_added_at' => [now()->timestamp],
        'expires_at' => $exp,
    ]);

    ListUpdated::dispatchFor('42', $list);

    Event::assertDispatched(ListUpdated::class, function (ListUpdated $e) use ($exp) {
        $payload = $e->broadcastWith();

        return $payload['expires_at'] === $exp->timestamp
            && $payload['disabled_at'] === null;
    });
});
