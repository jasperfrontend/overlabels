<?php

use App\Services\TwitchEventSubService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

/*
 * Badge artwork for the chat overlay.
 *
 * The endpoint mirrors /emotes/{channelId}: app credentials stay server-side,
 * the response is cached 24 h, and it is rate-limited.
 *
 * `global` and `channel` are returned SEPARATELY on purpose. A Shared Chat
 * message from a collab partner carries THEIR badge versions, and their
 * channel-specific art (subscriber, bits, founder) lives in a manifest we never
 * fetched. Resolving those against this channel's map would render our own
 * subscriber emblem for someone who subscribes elsewhere - stating something
 * false about a viewer. Foreign messages resolve against `global` only.
 */

const BADGE_CDN = 'https://static-cdn.jtvnw.net/badges/v1';

beforeEach(function () {
    Cache::flush();

    $this->mock(TwitchEventSubService::class, function ($mock) {
        $mock->shouldReceive('getAppAccessToken')->andReturn('app-token');
    });
});

function fakeBadgeApi(array $global, array $channel): void
{
    Http::fake([
        'api.twitch.tv/helix/chat/badges/global*' => Http::response(['data' => $global]),
        'api.twitch.tv/helix/chat/badges*' => Http::response(['data' => $channel]),
    ]);
}

function badgeSet(string $setId, array $versions): array
{
    return ['set_id' => $setId, 'versions' => $versions];
}

function badgeVersion(string $id, string $file, string $title): array
{
    return [
        'id' => $id,
        'image_url_1x' => BADGE_CDN."/$file/1",
        'image_url_2x' => BADGE_CDN."/$file/2",
        'image_url_4x' => BADGE_CDN."/$file/3",
        'title' => $title,
    ];
}

it('rejects a non-numeric channel id', function () {
    $this->getJson('/api/overlay/badges/not-a-number')
        ->assertStatus(400);
});

it('returns global and channel maps keyed set/version', function () {
    fakeBadgeApi(
        global: [badgeSet('moderator', [badgeVersion('1', 'mod', 'Moderator')])],
        channel: [badgeSet('subscriber', [badgeVersion('12', 'ours', 'Subscriber (12 months)')])],
    );

    $this->getJson('/api/overlay/badges/12345')
        ->assertOk()
        ->assertJsonPath('global.moderator/1.title', 'Moderator')
        ->assertJsonPath('channel.subscriber/12.title', 'Subscriber (12 months)');
});

it('uses the 2x image', function () {
    // Twitch's own chat renders badges at 18px, so 2x stays crisp on a 1080p
    // or 1440p overlay without paying 4x the bytes.
    fakeBadgeApi(
        global: [badgeSet('moderator', [badgeVersion('1', 'mod', 'Moderator')])],
        channel: [],
    );

    $this->getJson('/api/overlay/badges/12345')
        ->assertOk()
        ->assertJsonPath('global.moderator/1.url', BADGE_CDN.'/mod/2');
});

it('folds global into channel so a native message needs one lookup', function () {
    fakeBadgeApi(
        global: [badgeSet('moderator', [badgeVersion('1', 'mod', 'Moderator')])],
        channel: [badgeSet('subscriber', [badgeVersion('12', 'ours', 'Sub')])],
    );

    $this->getJson('/api/overlay/badges/12345')
        ->assertOk()
        ->assertJsonPath('channel.moderator/1.url', BADGE_CDN.'/mod/2')
        ->assertJsonPath('channel.subscriber/12.url', BADGE_CDN.'/ours/2');
});

it('lets the channel override global art for the same set and version', function () {
    // Subscriber and bits badges exist globally AND per channel. The channel's
    // own art is the correct one for a native message.
    fakeBadgeApi(
        global: [badgeSet('subscriber', [badgeVersion('1', 'generic', 'Subscriber')])],
        channel: [badgeSet('subscriber', [badgeVersion('1', 'ours', 'Subscriber')])],
    );

    $this->getJson('/api/overlay/badges/12345')
        ->assertOk()
        ->assertJsonPath('channel.subscriber/1.url', BADGE_CDN.'/ours/2')
        // global stays untouched, which is what a foreign message resolves
        // against.
        ->assertJsonPath('global.subscriber/1.url', BADGE_CDN.'/generic/2');
});

it('keeps a channel-only badge out of the global map', function () {
    fakeBadgeApi(
        global: [],
        channel: [badgeSet('subscriber', [badgeVersion('12', 'ours', 'Sub')])],
    );

    $response = $this->getJson('/api/overlay/badges/12345')->assertOk();

    expect($response->json('global'))->toBe([])
        ->and($response->json('channel'))->toHaveKey('subscriber/12');
});

it('skips malformed sets and versions rather than failing the request', function () {
    fakeBadgeApi(
        global: [
            ['versions' => [badgeVersion('1', 'x', 'X')]],            // no set_id
            badgeSet('nover', []),                                     // no versions
            badgeSet('noid', [['image_url_2x' => BADGE_CDN.'/y/2']]),  // version has no id
            badgeSet('good', [badgeVersion('1', 'good', 'Good')]),
        ],
        channel: [],
    );

    $response = $this->getJson('/api/overlay/badges/12345')->assertOk();

    expect(array_keys($response->json('global')))->toBe(['good/1']);
});

it('returns empty maps when no app token is available', function () {
    $this->mock(TwitchEventSubService::class, function ($mock) {
        $mock->shouldReceive('getAppAccessToken')->andReturnNull();
    });

    $this->getJson('/api/overlay/badges/12345')
        ->assertOk()
        ->assertExactJson(['global' => [], 'channel' => []]);
});

it('caches so a second request does not hit Twitch again', function () {
    fakeBadgeApi(
        global: [badgeSet('moderator', [badgeVersion('1', 'mod', 'Moderator')])],
        channel: [],
    );

    $this->getJson('/api/overlay/badges/12345')->assertOk();
    $this->getJson('/api/overlay/badges/12345')->assertOk();

    // Two calls (global + channel) for the first request, none for the second.
    Http::assertSentCount(2);
});

it('caches per channel rather than globally', function () {
    fakeBadgeApi(
        global: [badgeSet('moderator', [badgeVersion('1', 'mod', 'Moderator')])],
        channel: [],
    );

    $this->getJson('/api/overlay/badges/12345')->assertOk();
    $this->getJson('/api/overlay/badges/67890')->assertOk();

    Http::assertSentCount(4);
});

it('never exposes the app token to the client', function () {
    fakeBadgeApi(
        global: [badgeSet('moderator', [badgeVersion('1', 'mod', 'Moderator')])],
        channel: [],
    );

    $body = $this->getJson('/api/overlay/badges/12345')->assertOk()->content();

    expect($body)->not->toContain('app-token');
});

it('does not require authentication', function () {
    // Overlays run in an OBS browser source with no session. Badge art is
    // public data - the same images Twitch serves to every chat client.
    fakeBadgeApi(
        global: [badgeSet('moderator', [badgeVersion('1', 'mod', 'Moderator')])],
        channel: [],
    );

    $this->getJson('/api/overlay/badges/12345')->assertOk();
});
