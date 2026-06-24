<?php

use App\Events\ExternalEventStored;
use App\Events\TwitchEventReceived;
use App\Models\ExternalEvent;
use App\Models\OptionSet;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\Lists\RecentEventFormatter;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function efUser(string $login = 'streamer_ef'): User
{
    return User::factory()->create([
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

/**
 * A list with an enabled recent-events feed. `types` defaults to [] (accept
 * every event type); pass a whitelist to narrow it.
 */
function efFeedList(User $user, array $overrides = []): OptionSet
{
    $types = $overrides['feed_types'] ?? [];
    $enabled = $overrides['feed_enabled'] ?? true;
    unset($overrides['feed_types'], $overrides['feed_enabled']);

    return OptionSet::create(array_merge([
        'user_id' => $user->id,
        'slug' => 'recent_feed',
        'items' => [],
        'next_item_id' => 1,
        'min_items' => 0,
        'user_editable' => true,
        'event_feed' => ['enabled' => $enabled, 'types' => $types],
    ], $overrides));
}

function efFollow(User $user, string $name = 'Alice'): void
{
    TwitchEventReceived::dispatch('channel.follow', ['user_name' => $name], (string) $user->twitch_id);
}

// ──────────────────────────────────────────────────────────────────────────────
// Live append: Twitch + external feed events
// ──────────────────────────────────────────────────────────────────────────────

it('appends a formatted line for a Twitch event to an enabled feed list', function () {
    $user = efUser();
    $list = efFeedList($user);

    TwitchEventReceived::dispatch('channel.cheer', ['user_name' => 'Bob', 'bits' => 100], (string) $user->twitch_id);

    expect(ListItems::values($list->fresh()->items))->toBe(['Bob cheered 100 bits']);
});

it('appends a formatted line for an external event to an enabled feed list', function () {
    $user = efUser();
    $list = efFeedList($user, ['slug' => 'donor_feed']);

    ExternalEventStored::dispatch($user->id, 'kofi', 'donation', [
        'event.from_name' => 'Alice',
        'event.amount' => '5',
        'event.currency' => 'USD',
    ]);

    expect(ListItems::values($list->fresh()->items))->toBe(['Alice Ko-fi tip 5 USD']);
});

it('appends to every matching feed list the user owns', function () {
    $user = efUser();
    $a = efFeedList($user, ['slug' => 'feed_a']);
    $b = efFeedList($user, ['slug' => 'feed_b']);

    efFollow($user, 'Carol');

    expect(ListItems::values($a->fresh()->items))->toBe(['Carol followed'])
        ->and(ListItems::values($b->fresh()->items))->toBe(['Carol followed']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filtering, FIFO, disabled, non-feed
// ──────────────────────────────────────────────────────────────────────────────

it('only appends event types in the whitelist, empty = all', function () {
    $user = efUser();
    $follows = efFeedList($user, ['slug' => 'follows_only', 'feed_types' => ['channel.follow']]);
    $all = efFeedList($user, ['slug' => 'everything', 'feed_types' => []]);

    TwitchEventReceived::dispatch('channel.cheer', ['user_name' => 'Bob', 'bits' => 50], (string) $user->twitch_id);

    expect($follows->fresh()->items)->toBe([])               // cheer filtered out
        ->and(ListItems::values($all->fresh()->items))->toBe(['Bob cheered 50 bits']);
});

it('FIFO drops the oldest line past max_items', function () {
    $user = efUser();
    $list = efFeedList($user, ['slug' => 'capped', 'max_items' => 2]);

    efFollow($user, 'One');
    efFollow($user, 'Two');
    efFollow($user, 'Three');

    expect(ListItems::values($list->fresh()->items))->toBe(['Two followed', 'Three followed']);
});

it('does not append to a disabled list', function () {
    $user = efUser();
    $list = efFeedList($user, ['slug' => 'paused', 'disabled_at' => now()]);

    efFollow($user);

    expect($list->fresh()->items)->toBe([]);
});

it('does not append to a list whose feed is turned off', function () {
    $user = efUser();
    $list = efFeedList($user, ['slug' => 'feed_off', 'feed_enabled' => false]);

    efFollow($user);

    expect($list->fresh()->items)->toBe([]);
});

it('ignores plain lists with no event_feed config', function () {
    $user = efUser();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'plain',
        'items' => [],
        'next_item_id' => 1,
        'min_items' => 0,
        'user_editable' => true,
    ]);

    efFollow($user);

    expect($list->fresh()->items)->toBe([]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Seeding on enable, via the endpoint
// ──────────────────────────────────────────────────────────────────────────────

it('seeds the list with existing events, oldest-first, when the feed is enabled', function () {
    $user = efUser();

    $older = TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'event_data' => ['user_name' => 'Older'],
        'twitch_timestamp' => now(),
        'processed' => true,
    ]);
    $older->forceFill(['created_at' => now()->subMinutes(10)])->save();

    $newer = TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'event_data' => ['user_name' => 'Newer'],
        'twitch_timestamp' => now(),
        'processed' => true,
    ]);
    $newer->forceFill(['created_at' => now()->subMinutes(1)])->save();

    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'seed_me',
        'items' => [],
        'next_item_id' => 1,
        'min_items' => 0,
        'user_editable' => true,
    ]);

    $this->actingAs($user)
        ->put("/dashboard/lists/{$list->id}/event-feed", [
            'enabled' => true,
            'types' => [],
        ])
        ->assertRedirect();

    expect(ListItems::values($list->fresh()->items))->toBe(['Older followed', 'Newer followed'])
        ->and($list->fresh()->eventFeedEnabled())->toBeTrue();
});

it('defaults a FIFO cap when enabling a feed on an uncapped list', function () {
    $user = efUser();
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'uncapped',
        'items' => [],
        'next_item_id' => 1,
        'min_items' => 0,
        'max_items' => null,
        'user_editable' => true,
    ]);

    $this->actingAs($user)
        ->put("/dashboard/lists/{$list->id}/event-feed", ['enabled' => true])
        ->assertRedirect();

    expect($list->fresh()->max_items)->toBe(50);
});

it('refuses to configure a feed on a list owned by another user', function () {
    $owner = efUser('owner');
    $other = efUser('other');
    $list = efFeedList($other, ['slug' => 'theirs', 'feed_enabled' => false]);

    $this->actingAs($owner)
        ->put("/dashboard/lists/{$list->id}/event-feed", ['enabled' => true])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// Formatter unit checks
// ──────────────────────────────────────────────────────────────────────────────

it('formats representative Twitch and external events', function () {
    $f = new RecentEventFormatter;

    expect($f->format('twitch', 'channel.follow', ['user_name' => 'Alice'], null))->toBe('Alice followed')
        ->and($f->format('twitch', 'channel.subscribe', ['user_name' => 'Bo', 'tier' => '2000'], null))->toBe('Bo subscribed Tier 2')
        ->and($f->format('twitch', 'channel.raid', ['from_broadcaster_user_name' => 'Ray', 'viewers' => 42], null))->toBe('Ray raided 42 viewers')
        ->and($f->format('twitch', 'stream.online', [], null))->toBe('went live')
        ->and($f->format('streamlabs', 'donation', null, ['event.from_name' => 'Dana', 'event.amount' => '10', 'event.currency' => 'EUR']))->toBe('Dana Streamlabs tip 10 EUR');
});

it('falls back to the event-type display name when nothing renders', function () {
    $f = new RecentEventFormatter;

    // channel.update has no who/label/details mapping -> EVENT_TYPES label
    expect($f->format('twitch', 'channel.update', [], null))->toBe('Stream Info Updated');
});
