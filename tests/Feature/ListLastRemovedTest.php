<?php

use App\Events\ListUpdated;
use App\Models\OptionSet;
use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Lists\ListActionService;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

/**
 * A `pop` or `draw` broadcasts the list's REMAINING items, so before this the
 * overlay could watch a raffle list shrink and never learn who won - the
 * winner only ever reached chat. `last_removed` (+ `_at`) is the one rail
 * that carries the removed value to the screen.
 */
function removedUser(): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'streamer_'.fake()->unique()->lexify('????')],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function removedList(User $user, string $slug, array $items): OptionSet
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

it('pop first records the popped value and when', function () {
    $user = removedUser();
    $list = removedList($user, 'lane', ['alice', 'bob', 'carol']);

    app(ListActionService::class)->handleInvocation($user, 'lane pop first', 'Mod');

    $fresh = $list->fresh();

    expect($fresh->last_removed)->toBe('alice')
        ->and($fresh->last_removed_at)->not->toBeNull()
        ->and(abs($fresh->last_removed_at->timestamp - now()->timestamp))->toBeLessThan(5)
        ->and(ListItems::values($fresh->items))->toBe(['bob', 'carol']);
});

it('pop last records the tail', function () {
    $user = removedUser();
    $list = removedList($user, 'lane', ['alice', 'bob']);

    app(ListActionService::class)->handleInvocation($user, 'lane pop last', 'Mod');

    expect($list->fresh()->last_removed)->toBe('bob');
});

it('draw records the winner', function () {
    $user = removedUser();
    $list = removedList($user, 'raffle', ['alice', 'bob']);

    $reply = app(ListActionService::class)->handleInvocation($user, 'raffle draw', 'Mod');

    $fresh = $list->fresh();

    expect($fresh->last_removed)->toBeIn(['alice', 'bob'])
        ->and($reply)->toContain($fresh->last_removed)
        ->and(ListItems::values($fresh->items))->not->toContain($fresh->last_removed);
});

it('clear does not touch the last removed value', function () {
    $user = removedUser();
    $list = removedList($user, 'lane', ['alice', 'bob']);

    app(ListActionService::class)->handleInvocation($user, 'lane pop first', 'Mod');
    app(ListActionService::class)->handleInvocation($user, 'lane clear', 'Mod');

    $fresh = $list->fresh();

    expect($fresh->items)->toBe([])
        ->and($fresh->last_removed)->toBe('alice');
});

it('the removal broadcast carries the removed value and its timestamp', function () {
    Event::fake([ListUpdated::class]);

    $user = removedUser();
    removedList($user, 'lane', ['alice', 'bob']);

    app(ListActionService::class)->handleInvocation($user, 'lane pop first', 'Mod');

    Event::assertDispatched(ListUpdated::class, function (ListUpdated $event) {
        $payload = $event->broadcastWith();

        return $event->slug === 'lane'
            && $payload['last_removed'] === 'alice'
            && is_int($payload['last_removed_at'])
            && ListItems::values($payload['items']) === ['bob'];
    });
});

it('a list that has never had a removal broadcasts nulls, not empty strings', function () {
    Event::fake([ListUpdated::class]);

    $user = removedUser();
    $list = removedList($user, 'lane', ['alice']);

    ListUpdated::dispatchFor($user->twitch_id, $list);

    Event::assertDispatched(ListUpdated::class, function (ListUpdated $event) {
        $payload = $event->broadcastWith();

        return $payload['last_removed'] === null && $payload['last_removed_at'] === null;
    });
});

it('the render payload exposes :last_removed and :last_removed_at', function () {
    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
    });

    $user = removedUser();
    $user->update(['access_token' => 'fake-twitch-token']);
    $list = removedList($user, 'lane', ['alice', 'bob']);
    app(ListActionService::class)->handleInvocation($user, 'lane pop first', 'Mod');

    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'slug' => 'lane-'.fake()->unique()->lexify('????????'),
        'html' => '<b>[[[c:list:lane:last_removed]]]</b> at [[[c:list:lane:last_removed_at]]] of [[[c:list:lane:count]]]',
        'css' => '',
        'head' => '',
        'metadata' => null,
    ]);
    $template->template_tags = $template->extractTemplateTags($user->foreachCaps());
    $template->save();

    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'last-removed-render-test',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/overlay/render', [
        'slug' => $template->slug,
        'token' => $plain,
    ])->assertOk();

    $data = $response->json('data');

    expect($data['c:list:lane:last_removed'])->toBe('alice')
        ->and($data['c:list:lane:last_removed_at'])->toBe((string) $list->fresh()->last_removed_at->timestamp)
        ->and($data['c:list:lane:count'])->toBe('1');
});
