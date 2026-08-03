<?php

use App\Models\ExternalEvent;
use App\Models\TwitchEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function bdUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function bdTwitchEvent(?User $user, string $type = 'channel.follow', array $data = []): TwitchEvent
{
    return TwitchEvent::create([
        'user_id' => $user?->id,
        'event_type' => $type,
        'event_data' => $data ?: ['user_name' => 'testFromUser'],
        'twitch_timestamp' => now(),
        'processed' => false,
    ]);
}

function bdExternalEvent(User $user, string $service = 'kofi', string $type = 'donation'): ExternalEvent
{
    return ExternalEvent::create([
        'user_id' => $user->id,
        'service' => $service,
        'event_type' => $type,
        'message_id' => (string) fake()->unique()->uuid(),
        'raw_payload' => ['from_name' => 'Jo Example'],
        'normalized_payload' => ['event.from_name' => 'Jo Example'],
    ]);
}

function bdPair(string $source, int $id): array
{
    return ['source' => $source, 'id' => $id];
}

// ──────────────────────────────────────────────────────────────────────────────
// Explicit picks
// ──────────────────────────────────────────────────────────────────────────────

it('deletes picked rows from both event tables in one request', function () {
    $user = bdUser();
    $twitch = bdTwitchEvent($user);
    $external = bdExternalEvent($user);
    $keep = bdTwitchEvent($user, 'channel.cheer');

    $this->actingAs($user)
        ->post(route('events.bulk-delete'), [
            'events' => [bdPair('twitch', $twitch->id), bdPair('kofi', $external->id)],
        ])
        ->assertRedirect();

    expect(TwitchEvent::find($twitch->id))->toBeNull()
        ->and(ExternalEvent::find($external->id))->toBeNull()
        ->and(TwitchEvent::find($keep->id))->not->toBeNull();
});

it('reports how many rows were deleted', function () {
    $user = bdUser();
    $a = bdTwitchEvent($user);
    $b = bdTwitchEvent($user);

    $this->actingAs($user)
        ->post(route('events.bulk-delete'), [
            'events' => [bdPair('twitch', $a->id), bdPair('twitch', $b->id)],
        ])
        ->assertSessionHas('message', 'Deleted 2 events.');
});

// The two tables have independent auto-increment ids, so a pair whose source is
// wrong would otherwise reach across into whichever table shares that id.
it('routes ids by source rather than deleting the same id in both tables', function () {
    $user = bdUser();
    $twitch = bdTwitchEvent($user);
    $external = bdExternalEvent($user);

    $this->actingAs($user)
        ->post(route('events.bulk-delete'), [
            'events' => [bdPair('twitch', $twitch->id)],
        ])
        ->assertRedirect();

    expect(TwitchEvent::find($twitch->id))->toBeNull()
        ->and(ExternalEvent::find($external->id))->not->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Ownership boundary
// ──────────────────────────────────────────────────────────────────────────────

it('will not delete another user\'s events', function () {
    $user = bdUser();
    $other = bdUser();
    $theirTwitch = bdTwitchEvent($other);
    $theirExternal = bdExternalEvent($other);

    $this->actingAs($user)
        ->post(route('events.bulk-delete'), [
            'events' => [bdPair('twitch', $theirTwitch->id), bdPair('kofi', $theirExternal->id)],
        ])
        ->assertRedirect();

    expect(TwitchEvent::find($theirTwitch->id))->not->toBeNull()
        ->and(ExternalEvent::find($theirExternal->id))->not->toBeNull();
});

// twitch_events.user_id is nullable (events for broadcasters we don't know), so
// a delete scoped only by id would reach rows that belong to nobody.
it('will not delete ownerless twitch events', function () {
    $user = bdUser();
    $orphan = bdTwitchEvent(null);

    $this->actingAs($user)
        ->post(route('events.bulk-delete'), [
            'events' => [bdPair('twitch', $orphan->id)],
        ])
        ->assertRedirect();

    expect(TwitchEvent::find($orphan->id))->not->toBeNull();
});

// GPS rows never appear in this feed, so they can never be picked from it -
// even if the client sends a spoofed source.
it('will not delete gps events', function () {
    $user = bdUser();
    $gps = bdExternalEvent($user, 'gps', 'location');

    $this->actingAs($user)
        ->post(route('events.bulk-delete'), [
            'events' => [bdPair('gps', $gps->id)],
        ])
        ->assertRedirect();

    expect(ExternalEvent::find($gps->id))->not->toBeNull();
});

it('requires authentication', function () {
    $user = bdUser();
    $event = bdTwitchEvent($user);

    $this->post(route('events.bulk-delete'), [
        'events' => [bdPair('twitch', $event->id)],
    ])->assertRedirect();

    expect(TwitchEvent::find($event->id))->not->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Filter-scoped delete
// ──────────────────────────────────────────────────────────────────────────────

it('deletes everything matching the filters when all is set', function () {
    $user = bdUser();
    $a = bdTwitchEvent($user);
    $b = bdTwitchEvent($user, 'channel.cheer');
    $c = bdExternalEvent($user);

    $this->actingAs($user)
        ->post(route('events.bulk-delete'), ['all' => true])
        ->assertRedirect();

    expect(TwitchEvent::find($a->id))->toBeNull()
        ->and(TwitchEvent::find($b->id))->toBeNull()
        ->and(ExternalEvent::find($c->id))->toBeNull();
});

it('honours the source filter, leaving the other table untouched', function () {
    $user = bdUser();
    $twitch = bdTwitchEvent($user);
    $kofi = bdExternalEvent($user, 'kofi');
    $streamlabs = bdExternalEvent($user, 'streamlabs');

    $this->actingAs($user)
        ->post(route('events.bulk-delete').'?source=kofi', ['all' => true])
        ->assertRedirect();

    expect(ExternalEvent::find($kofi->id))->toBeNull()
        ->and(ExternalEvent::find($streamlabs->id))->not->toBeNull()
        ->and(TwitchEvent::find($twitch->id))->not->toBeNull();
});

it('honours the event_type filter', function () {
    $user = bdUser();
    $follow = bdTwitchEvent($user, 'channel.follow');
    $cheer = bdTwitchEvent($user, 'channel.cheer');

    $this->actingAs($user)
        ->post(route('events.bulk-delete').'?event_type=channel.follow', ['all' => true])
        ->assertRedirect();

    expect(TwitchEvent::find($follow->id))->toBeNull()
        ->and(TwitchEvent::find($cheer->id))->not->toBeNull();
});

it('honours the search filter against the stored payload', function () {
    $user = bdUser();
    $bot = bdTwitchEvent($user, 'channel.follow', ['user_name' => 'spam_bot_9000']);
    $real = bdTwitchEvent($user, 'channel.follow', ['user_name' => 'Alice']);

    $this->actingAs($user)
        ->post(route('events.bulk-delete').'?search=spam_bot', ['all' => true])
        ->assertRedirect();

    expect(TwitchEvent::find($bot->id))->toBeNull()
        ->and(TwitchEvent::find($real->id))->not->toBeNull();
});

// deleteMatching() shares applyFilters() with the feed, so the event-type
// search reaches the delete path too - including its user scoping, which an
// ungrouped OR would have broken in the most destructive possible way.
it('honours an event-type search and stays inside the acting user', function () {
    $user = bdUser();
    $other = bdUser();
    $mine = bdTwitchEvent($user, 'channel.poll.end', ['title' => 'Which game next']);
    $follow = bdTwitchEvent($user, 'channel.follow');
    $theirs = bdTwitchEvent($other, 'channel.poll.end', ['title' => 'Not yours']);

    $this->actingAs($user)
        ->post(route('events.bulk-delete').'?search=poll', ['all' => true])
        ->assertRedirect();

    expect(TwitchEvent::find($mine->id))->toBeNull()
        ->and(TwitchEvent::find($follow->id))->not->toBeNull()
        ->and(TwitchEvent::find($theirs->id))->not->toBeNull();
});

it('honours the range filter and spares older rows', function () {
    $user = bdUser();
    $recent = bdTwitchEvent($user);
    $old = bdTwitchEvent($user);
    $old->created_at = now()->subDays(10);
    $old->save();

    $this->actingAs($user)
        ->post(route('events.bulk-delete').'?range=24h', ['all' => true])
        ->assertRedirect();

    expect(TwitchEvent::find($recent->id))->toBeNull()
        ->and(TwitchEvent::find($old->id))->not->toBeNull();
});

it('never lets a filtered delete escape the acting user', function () {
    $user = bdUser();
    $other = bdUser();
    $mine = bdTwitchEvent($user);
    $theirs = bdTwitchEvent($other);

    $this->actingAs($user)
        ->post(route('events.bulk-delete'), ['all' => true])
        ->assertRedirect();

    expect(TwitchEvent::find($mine->id))->toBeNull()
        ->and(TwitchEvent::find($theirs->id))->not->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Input handling
// ──────────────────────────────────────────────────────────────────────────────

it('rejects an oversized selection', function () {
    $user = bdUser();

    $this->actingAs($user)
        ->post(route('events.bulk-delete'), [
            'events' => array_map(fn (int $i) => bdPair('twitch', $i), range(1, 501)),
        ])
        ->assertSessionHasErrors('events');
});

it('reports when nothing was selected', function () {
    $user = bdUser();

    $this->actingAs($user)
        ->post(route('events.bulk-delete'), [])
        ->assertSessionHas('type', 'warning');
});
