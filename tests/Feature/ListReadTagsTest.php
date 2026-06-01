<?php

use App\Http\Controllers\OverlayTemplateController;
use App\Models\ListAppender;
use App\Models\OptionSet;
use App\Models\User;
use App\Services\Lists\ListAppendService;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * Build the listData injection payload the same way
 * OverlayTemplateController::renderAuthenticated does, but in isolation
 * so we can test the derived tags without spinning up the full render
 * pipeline (which needs OverlayAccessToken + Twitch API stubs).
 *
 * Kept in sync with the controller logic; if you touch one, touch both.
 */
function buildListData(User $user): array
{
    $userLists = OptionSet::where('user_id', $user->id)->get();
    $controller = app(OverlayTemplateController::class);
    $reflection = new ReflectionClass($controller);
    $sumMethod = $reflection->getMethod('sumListItems');

    $listData = [];
    foreach ($userLists as $list) {
        $items = array_values($list->items ?? []);
        $baseKey = 'c:list:'.$list->slug;
        $count = count($items);

        $listData[$baseKey] = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $listData[$baseKey.'.count'] = (string) $count;
        $listData[$baseKey.':count'] = (string) $count;
        foreach ($items as $i => $item) {
            $listData[$baseKey.'.'.$i] = (string) $item;
        }
        $listData[$baseKey.':first'] = $count > 0 ? (string) $items[0] : '';
        $listData[$baseKey.':last'] = $count > 0 ? (string) $items[$count - 1] : '';
        $listData[$baseKey.':empty'] = $count === 0 ? '1' : '0';
        $listData[$baseKey.':random'] = $count > 0 ? (string) $items[array_rand($items)] : '';
        $listData[$baseKey.':sum'] = $sumMethod->invoke($controller, $list->slug, $items);
    }

    return $listData;
}

// ──────────────────────────────────────────────────────────────────────────────
// :first, :last, :count, :empty
// ──────────────────────────────────────────────────────────────────────────────

it('derives :first and :last from list items', function () {
    $user = User::factory()->create();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'donors',
        'items' => ['Alice', 'Bob', 'Carol'],
    ]);

    $data = buildListData($user);

    expect($data['c:list:donors:first'])->toBe('Alice')
        ->and($data['c:list:donors:last'])->toBe('Carol')
        ->and($data['c:list:donors:count'])->toBe('3')
        ->and($data['c:list:donors:empty'])->toBe('0');
});

it(':empty resolves to "1" for an empty list', function () {
    $user = User::factory()->create();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'empty_list', 'items' => []]);

    $data = buildListData($user);

    expect($data['c:list:empty_list:empty'])->toBe('1')
        ->and($data['c:list:empty_list:count'])->toBe('0')
        ->and($data['c:list:empty_list:first'])->toBe('')
        ->and($data['c:list:empty_list:last'])->toBe('');
});

// ──────────────────────────────────────────────────────────────────────────────
// :random
// ──────────────────────────────────────────────────────────────────────────────

it(':random returns one of the items for a non-empty list', function () {
    $user = User::factory()->create();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'colors',
        'items' => ['red', 'green', 'blue'],
    ]);

    $data = buildListData($user);

    expect($data['c:list:colors:random'])->toBeIn(['red', 'green', 'blue']);
});

it(':random is empty string for an empty list', function () {
    $user = User::factory()->create();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'nothing', 'items' => []]);

    $data = buildListData($user);

    expect($data['c:list:nothing:random'])->toBe('');
});

// ──────────────────────────────────────────────────────────────────────────────
// :sum - the loud-failure path
// ──────────────────────────────────────────────────────────────────────────────

it(':sum totals numeric items', function () {
    $user = User::factory()->create();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'tip_jar',
        'items' => ['5', '10', '15.5'],
    ]);

    $data = buildListData($user);

    expect($data['c:list:tip_jar:sum'])->toBe('30.5');
});

it(':sum renders integer-valued totals without trailing zeros', function () {
    $user = User::factory()->create();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'whole_numbers',
        'items' => ['10', '20', '30'],
    ]);

    expect(buildListData($user)['c:list:whole_numbers:sum'])->toBe('60');
});

it(':sum skips empty and whitespace-only items', function () {
    $user = User::factory()->create();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'gappy',
        'items' => ['10', '', '   ', '20'],
    ]);

    expect(buildListData($user)['c:list:gappy:sum'])->toBe('30');
});

it(':sum fails loudly with the offending item and position', function () {
    $user = User::factory()->create();
    OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'broken',
        'items' => ['10', '20', 'abc', '30'],
    ]);

    expect(buildListData($user)['c:list:broken:sum'])
        ->toBe("ERR: list 'broken' has non-numeric item 'abc' at position 2");
});

it(':sum on an empty list is "0"', function () {
    $user = User::factory()->create();
    OptionSet::create(['user_id' => $user->id, 'slug' => 'no_tips', 'items' => []]);

    expect(buildListData($user)['c:list:no_tips:sum'])->toBe('0');
});

// ──────────────────────────────────────────────────────────────────────────────
// disabled_at gates the appender
// ──────────────────────────────────────────────────────────────────────────────

it('ListAppendService::fire returns list_disabled when the target is disabled', function () {
    $user = User::factory()->create(['twitch_id' => '12345']);
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'closed_raffle',
        'items' => [],
        'disabled_at' => now(),
    ]);
    $appender = ListAppender::create([
        'user_id' => $user->id,
        'target_list_id' => $list->id,
        'command' => 'raffle',
        'value_template' => '[[[bot:from_user]]]',
        'dedup_policy' => ListAppender::DEDUP_PER_CHATTER,
    ]);

    $result = app(ListAppendService::class)->fire($appender, $user, [
        'channel_login' => 'streamer',
        'command' => 'raffle',
        'chatter_id' => '99',
        'chatter_login' => 'alice',
        'chatter_display_name' => 'Alice',
        'args' => '',
    ]);

    expect($result['fired'])->toBeFalse()
        ->and($result['reason'])->toBe('list_disabled')
        ->and($list->fresh()->items)->toBe([]);
});

it('fire works again after the list is re-enabled', function () {
    $user = User::factory()->create(['twitch_id' => '12345']);
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'open_raffle',
        'items' => [],
        'disabled_at' => now(),
    ]);
    $appender = ListAppender::create([
        'user_id' => $user->id,
        'target_list_id' => $list->id,
        'command' => 'raffle',
        'value_template' => '[[[bot:from_user]]]',
        'dedup_policy' => ListAppender::DEDUP_NONE,
    ]);

    $list->update(['disabled_at' => null]);

    $result = app(ListAppendService::class)->fire($appender, $user, [
        'channel_login' => 'streamer',
        'command' => 'raffle',
        'chatter_id' => '99',
        'chatter_login' => 'alice',
        'chatter_display_name' => 'Alice',
        'args' => '',
    ]);

    expect($result['fired'])->toBeTrue()
        ->and(ListItems::values($list->fresh()->items))->toBe(['Alice']);
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /dashboard/lists/{list} with disabled flag
// ──────────────────────────────────────────────────────────────────────────────

it('toggles disabled_at via the update endpoint', function () {
    $user = User::factory()->create();
    $list = OptionSet::create(['user_id' => $user->id, 'slug' => 'togglable', 'items' => ['x']]);

    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'disabled' => true,
    ])->assertRedirect();

    expect($list->fresh()->disabled_at)->not->toBeNull();

    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'disabled' => false,
    ])->assertRedirect();

    expect($list->fresh()->disabled_at)->toBeNull();
});

it('disabled toggle does not touch items when present', function () {
    $user = User::factory()->create();
    $list = OptionSet::create(['user_id' => $user->id, 'slug' => 'keep_items', 'items' => ['a', 'b']]);

    // Send items in the payload too, but the disabled flag should
    // make the controller ignore them and only touch disabled_at.
    $this->actingAs($user)->put("/dashboard/lists/{$list->id}", [
        'disabled' => true,
        'items' => ['this', 'should', 'not', 'replace'],
    ])->assertRedirect();

    $fresh = $list->fresh();
    expect($fresh->items)->toBe(['a', 'b'])
        ->and($fresh->disabled_at)->not->toBeNull();
});
