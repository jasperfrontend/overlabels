<?php

use App\Http\Controllers\TwitchEventSubController;
use App\Jobs\ForgetTwitchCache;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Every Twitch event used to cost one Helix /users call for the acting user's
 * avatar, and in a raid that was the whole remaining cost of a follow (#343).
 * Only two things ever read it: an alert with an `event.*_avatar` tag, and a
 * static overlay with a bare `*_avatar` tag the event payload overwrites. So:
 * no such tag in the streamer's own templates, no lookup at all. And when one
 * is needed, the rows the worker handles next ride along in the same call.
 */
beforeEach(function () {
    config(['services.twitch.client_id' => 'test-client-id']);
    Cache::flush();
    Queue::fake([ForgetTwitchCache::class]);
    Http::fake([
        'api.twitch.tv/helix/users*' => function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $params);

            return Http::response(['data' => array_map(
                fn ($id) => ['id' => $id, 'profile_image_url' => "https://example.test/$id.png"],
                (array) ($params['id'] ?? []),
            )]);
        },
        '*' => Http::response(['data' => []]),
    ]);
});

function avatarFollowRow(User $user, string $followerId): TwitchEvent
{
    $event = [
        'broadcaster_user_id' => (string) $user->twitch_id, 'broadcaster_user_login' => 'host', 'broadcaster_user_name' => 'Host',
        'user_id' => $followerId, 'user_login' => "f$followerId", 'user_name' => "F$followerId",
    ];

    return TwitchEvent::create(['user_id' => $user->id, 'event_type' => 'channel.follow', 'event_data' => $event, 'twitch_timestamp' => now(), 'processed' => false]);
}

function avatarProcess(TwitchEvent $row): TwitchEvent
{
    app(TwitchEventSubController::class)->processStoredEvent($row, ['subscription' => ['type' => $row->event_type], 'event' => $row->event_data]);

    return $row->fresh();
}

function usersCalls(): int
{
    return count(Http::recorded(fn (Request $r) => str_contains($r->url(), '/helix/users')));
}

it('makes no avatar lookup when none of the streamer\'s templates shows an event avatar', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'html' => '<div>[[[event.user_name]]] followed!</div>']);

    $row = avatarProcess(avatarFollowRow($user, '501'));

    expect(usersCalls())->toBe(0)
        ->and($row->event_data)->not->toHaveKey('user_avatar');
});

it('looks the avatar up when an alert shows event.user_avatar', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'html' => '<img src="[[[event.user_avatar]]]">']);

    $row = avatarProcess(avatarFollowRow($user, '502'));

    expect($row->event_data['user_avatar'] ?? null)->toBe('https://example.test/502.png');
});

it('looks the avatar up when a static overlay shows a bare user_avatar the event overwrites', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'static', 'html' => '<img src="[[[user_avatar]]]">']);

    avatarProcess(avatarFollowRow($user, '503'));

    expect(usersCalls())->toBe(1);
});

it('does not count channel_avatar or an image file that merely has avatar in its name', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'static', 'html' => '<img src="[[[channel_avatar]]]"><img src="https://images.overlabels.com/overlays/twitch-avatar.png">']);

    avatarProcess(avatarFollowRow($user, '504'));

    expect(usersCalls())->toBe(0);
});

it('only counts the streamer\'s own templates', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    $someoneElse = User::factory()->create();
    OverlayTemplate::factory()->create(['owner_id' => $someoneElse->id, 'type' => 'alert', 'html' => '[[[event.user_avatar]]]']);

    avatarProcess(avatarFollowRow($user, '505'));

    expect(usersCalls())->toBe(0);
});

it('sees an avatar tag the moment a template is saved with one', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    $alert = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'html' => '<div>[[[event.user_name]]]</div>']);

    avatarProcess(avatarFollowRow($user, '506'));
    expect(usersCalls())->toBe(0);

    $alert->update(['html' => '<img src="[[[event.user_avatar]]]">']);
    $row = avatarProcess(avatarFollowRow($user, '507'));

    expect($row->event_data['user_avatar'] ?? null)->toBe('https://example.test/507.png');
});

it('looks up a whole burst in one call by riding along the rows that come next', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'html' => '<img src="[[[event.user_avatar]]]">']);
    $rows = collect(range(601, 605))->map(fn ($id) => avatarFollowRow($user, (string) $id));

    $processed = $rows->map(fn ($row) => avatarProcess($row));

    expect(usersCalls())->toBe(1);
    foreach ($processed as $i => $row) {
        expect($row->event_data['user_avatar'] ?? null)->toBe('https://example.test/'.(601 + $i).'.png');
    }
});

it('never looks up a viewer whose stored row was anonymised', function () {
    $user = User::factory()->create(['access_token' => 'token']);
    OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'html' => '<img src="[[[event.user_avatar]]]">']);
    $first = avatarFollowRow($user, '701');
    TwitchEvent::create(['user_id' => $user->id, 'event_type' => 'channel.cheer', 'twitch_timestamp' => now(), 'processed' => false,
        'event_data' => ['broadcaster_user_id' => (string) $user->twitch_id, 'user_id' => null, 'user_login' => null, 'user_name' => 'Anonymous', 'is_anonymous' => true]]);

    avatarProcess($first);

    $ids = collect(Http::recorded(fn (Request $r) => str_contains($r->url(), '/helix/users')))
        ->flatMap(function ($pair) {
            parse_str(parse_url($pair[0]->url(), PHP_URL_QUERY) ?: '', $params);

            return (array) ($params['id'] ?? []);
        });
    expect($ids->all())->not->toContain('')->and($ids->contains(null))->toBeFalse();
});
