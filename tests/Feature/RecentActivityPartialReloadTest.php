<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\TwitchEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * Headers for a partial reload. The version has to be the one the middleware
 * computes from the asset manifest - a mismatch makes Inertia answer 409 with a
 * full-reload instruction instead of the partial, and the assertions below
 * would then be testing nothing.
 */
function rpPartialHeaders(string $only): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'dashboard/recents',
        'X-Inertia-Partial-Data' => $only,
    ];
}

function rpUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function rpEvent(User $user, string $name = 'Alice'): TwitchEvent
{
    return TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.follow',
        'event_data' => ['user_name' => $name],
        'twitch_timestamp' => now(),
        'processed' => false,
    ]);
}

it('serves every prop on a full page load', function () {
    $user = rpUser();
    rpEvent($user);

    $this->actingAs($user)
        ->get(route('dashboard.recents'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/recents')
            ->has('recentEvents')
            ->has('filters')
            ->has('facets')
            ->has('recentTemplates')
            ->has('userLists')
        );
});

// The search box reloads on every keystroke batch, so the props a filter cannot
// change are deferred behind closures. If they ever stop being deferred this
// test fails and the keystroke cost comes back silently.
it('omits the filter-independent props from a partial reload', function () {
    $user = rpUser();
    rpEvent($user);

    // A partial reload answers with JSON rather than the HTML page, so
    // assertInertia (which reads the `page` view data) cannot see it.
    $this->actingAs($user)
        ->get(route('dashboard.recents'), rpPartialHeaders('recentEvents,filters'))
        ->assertOk()
        ->assertJsonPath('component', 'dashboard/recents')
        ->assertJsonStructure(['props' => ['recentEvents', 'filters']])
        ->assertJsonMissingPath('props.facets')
        ->assertJsonMissingPath('props.recentTemplates')
        ->assertJsonMissingPath('props.userLists');
});

// A poll payload has no "poll" in it anywhere - the word only exists in the
// event type. Searching "po" used to match polls purely by accident, through
// the "po" in the payload's `channel_points_voting` key, so typing the word out
// in full made results disappear.
it('finds events by event type, not just by payload', function () {
    $user = rpUser();
    $poll = TwitchEvent::create([
        'user_id' => $user->id,
        'event_type' => 'channel.poll.end',
        'event_data' => ['title' => 'Which game next', 'channel_points_voting' => ['is_enabled' => false]],
        'twitch_timestamp' => now(),
        'processed' => false,
    ]);
    rpEvent($user, 'Alice');

    $this->actingAs($user)
        ->get(route('dashboard.recents', ['search' => 'poll']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentEvents.data', 1)
            ->where('recentEvents.data.0.id', $poll->id)
        );
});

// The type match is an OR against the payload match. Ungrouped, it would climb
// out past the user_id scope and show every user's polls to everybody.
it('keeps the event-type search inside the user scope', function () {
    $user = rpUser();
    $other = rpUser();
    TwitchEvent::create([
        'user_id' => $other->id,
        'event_type' => 'channel.poll.end',
        'event_data' => ['title' => 'Not yours'],
        'twitch_timestamp' => now(),
        'processed' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.recents', ['search' => 'poll']))
        ->assertInertia(fn (Assert $page) => $page->has('recentEvents.data', 0));
});

it('still filters the feed on a partial reload', function () {
    $user = rpUser();
    rpEvent($user, 'spam_bot_9000');
    rpEvent($user, 'Alice');

    $this->actingAs($user)
        ->get(route('dashboard.recents', ['search' => 'spam_bot']), rpPartialHeaders('recentEvents,filters'))
        ->assertOk()
        ->assertJsonPath('props.filters.search', 'spam_bot')
        ->assertJsonCount(1, 'props.recentEvents.data');
});
