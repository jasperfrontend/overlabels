<?php

use App\Events\AlertTriggered;
use App\Models\EventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * The webhook path builds an alert with the account's access token as stored.
 * Nothing on that path refreshed it: a streamer whose token had merely
 * expired (Twitch user tokens last ~4h) and who had not opened the dashboard
 * or an overlay since got failed alerts until something else refreshed it.
 * Now an expired stored token is refreshed once, in-request, before the
 * alert is built - and a failed refresh backs off for ten minutes so a dead
 * account never pays an outbound call per event.
 */
function tokenRefreshUser(array $attrs = []): User
{
    $user = User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'stale-token',
        'refresh_token' => 'refresh-me',
        'token_expires_at' => now()->subHours(2),
        'bot_enabled' => false,
    ], $attrs));

    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id, 'fork_of_id' => null, 'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        'html' => '<div>[[[event.user_name]]]</div>', 'css' => '',
        'alert_sound_url' => null, 'tts_message' => null, 'chat_message' => null,
        'template_tags' => ['event.user_name'],
    ]);
    EventTemplateMapping::create([
        'user_id' => $user->id, 'event_type' => 'channel.follow', 'template_id' => $alert->id,
        'enabled' => true, 'duration_ms' => 5000,
    ]);

    return $user;
}

function postFollowFor(User $user): TwitchEvent
{
    $id = 'msg-'.Str::uuid();
    postSignedNotification($id, [
        'broadcaster_user_id' => $user->twitch_id, 'user_id' => '123', 'user_name' => 'someone',
    ], $id)->assertOk();

    return TwitchEvent::where('message_id', $id)->firstOrFail();
}

beforeEach(function () {
    Cache::flush();
    Event::fake([AlertTriggered::class]);
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
    });
});

test('an expired stored token is refreshed before the alert is built', function () {
    Http::fake(['id.twitch.tv/oauth2/token' => Http::response([
        'access_token' => 'fresh-token', 'refresh_token' => 'refresh-me-2', 'expires_in' => 14400, 'scope' => [],
    ])]);

    $user = tokenRefreshUser();
    $row = postFollowFor($user);

    $user->refresh();
    expect($user->access_token)->toBe('fresh-token')
        ->and($user->token_expires_at->isFuture())->toBeTrue()
        ->and($row->alert_id)->not->toBeNull();
    Http::assertSentCount(1);
});

test('a valid stored token costs no outbound call', function () {
    Http::fake();

    postFollowFor(tokenRefreshUser(['token_expires_at' => now()->addHours(3)]));

    Http::assertNothingSent();
});

test('a failed refresh backs off for ten minutes instead of retrying on every event', function () {
    Http::fake(['id.twitch.tv/oauth2/token' => Http::response(['message' => 'Invalid refresh token'], 400)]);

    $user = tokenRefreshUser();
    postFollowFor($user);
    postFollowFor($user);
    postFollowFor($user);

    Http::assertSentCount(1);
    expect($user->refresh()->access_token)->toBe('stale-token');

    $this->travel(11)->minutes();
    postFollowFor($user);
    Http::assertSentCount(2);
});
