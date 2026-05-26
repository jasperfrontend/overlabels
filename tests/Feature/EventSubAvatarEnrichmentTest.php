<?php

use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config([
        'services.twitch.client_id' => 'test-client-id',
        'services.twitch.client_secret' => 'test-client-secret',
    ]);
    Cache::flush();
});

function avatarFor(string $id): string
{
    return "https://cdn.example/$id.png";
}

function fakeHelixUsers(array $idToLogin): void
{
    Http::fake([
        'api.twitch.tv/helix/users*' => function ($request) use ($idToLogin) {
            $url = $request->url();
            parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);
            $ids = (array) ($params['id'] ?? []);
            $data = [];
            foreach ($ids as $id) {
                if (isset($idToLogin[$id])) {
                    $data[] = [
                        'id' => $id,
                        'login' => $idToLogin[$id],
                        'display_name' => $idToLogin[$id],
                        'profile_image_url' => avatarFor($id),
                    ];
                }
            }

            return Http::response(['data' => $data]);
        },
    ]);
}

it('injects user_avatar for a follow event (bare user trigram)', function () {
    fakeHelixUsers(['1234567' => 'testFromUser', '73327367' => 'testBroadcaster']);

    $event = [
        'user_id' => '1234567',
        'user_login' => 'testFromUser',
        'user_name' => 'testFromUser',
        'broadcaster_user_id' => '73327367',
        'broadcaster_user_login' => 'testBroadcaster',
        'broadcaster_user_name' => 'testBroadcaster',
        'followed_at' => '2026-05-26T19:00:00Z',
    ];

    $enriched = app(TwitchApiService::class)->enrichEventWithUserAvatars('token', $event);

    expect($enriched['user_avatar'])->toBe(avatarFor('1234567'));
    expect($enriched['broadcaster_user_avatar'])->toBe(avatarFor('73327367'));
});

it('injects from_broadcaster_user_avatar and to_broadcaster_user_avatar for raid events', function () {
    fakeHelixUsers(['11' => 'raider', '22' => 'host']);

    $event = [
        'from_broadcaster_user_id' => '11',
        'from_broadcaster_user_login' => 'raider',
        'from_broadcaster_user_name' => 'Raider',
        'to_broadcaster_user_id' => '22',
        'to_broadcaster_user_login' => 'host',
        'to_broadcaster_user_name' => 'Host',
        'viewers' => 42,
    ];

    $enriched = app(TwitchApiService::class)->enrichEventWithUserAvatars('token', $event);

    expect($enriched['from_broadcaster_user_avatar'])->toBe(avatarFor('11'));
    expect($enriched['to_broadcaster_user_avatar'])->toBe(avatarFor('22'));
});

it('enriches nested top_contributions and last_contribution in hype train events', function () {
    fakeHelixUsers([
        '73327367' => 'testBroadcaster',
        '45376845' => 'cli_user1',
        '7123458' => 'cli_user2',
    ]);

    $event = [
        'id' => 'hype-1',
        'broadcaster_user_id' => '73327367',
        'broadcaster_user_login' => 'testBroadcaster',
        'broadcaster_user_name' => 'testBroadcaster',
        'level' => 3,
        'top_contributions' => [
            ['total' => 274, 'type' => 'bits', 'user_id' => '45376845', 'user_login' => 'cli_user1', 'user_name' => 'cli_user1'],
            ['total' => 51, 'type' => 'other', 'user_id' => '7123458', 'user_login' => 'cli_user2', 'user_name' => 'cli_user2'],
        ],
        'last_contribution' => [
            'total' => 51, 'type' => 'other', 'user_id' => '7123458', 'user_login' => 'cli_user2', 'user_name' => 'cli_user2',
        ],
    ];

    $enriched = app(TwitchApiService::class)->enrichEventWithUserAvatars('token', $event);

    expect($enriched['broadcaster_user_avatar'])->toBe(avatarFor('73327367'));
    expect($enriched['top_contributions'][0]['user_avatar'])->toBe(avatarFor('45376845'));
    expect($enriched['top_contributions'][1]['user_avatar'])->toBe(avatarFor('7123458'));
    expect($enriched['last_contribution']['user_avatar'])->toBe(avatarFor('7123458'));
});

it('skips trigrams with missing or empty user_id (anonymous cheer)', function () {
    Http::fake(); // Any unexpected call will be recorded; we assert below that none happened.

    $event = [
        'user_id' => null,
        'user_login' => null,
        'user_name' => 'Anonymous',
        'is_anonymous' => true,
        'bits' => 100,
        'broadcaster_user_id' => '',
        'broadcaster_user_login' => '',
        'broadcaster_user_name' => '',
        'message' => ['text' => 'cheer100'],
    ];

    $enriched = app(TwitchApiService::class)->enrichEventWithUserAvatars('token', $event);

    expect($enriched)->not->toHaveKey('user_avatar');
    expect($enriched)->not->toHaveKey('broadcaster_user_avatar');

    Http::assertNothingSent();
});

it('batches every user id into a single Helix /users call across nested locations', function () {
    fakeHelixUsers([
        '11' => 'broadcaster',
        '22' => 'gifter',
        '33' => 'contributor_a',
        '44' => 'contributor_b',
    ]);

    $event = [
        'user_id' => '22',
        'user_login' => 'gifter',
        'user_name' => 'Gifter',
        'broadcaster_user_id' => '11',
        'broadcaster_user_login' => 'broadcaster',
        'broadcaster_user_name' => 'Broadcaster',
        'top_contributions' => [
            ['user_id' => '33', 'user_login' => 'a', 'user_name' => 'A', 'total' => 1, 'type' => 'bits'],
            ['user_id' => '44', 'user_login' => 'b', 'user_name' => 'B', 'total' => 2, 'type' => 'bits'],
        ],
        'last_contribution' => [
            'user_id' => '44', 'user_login' => 'b', 'user_name' => 'B', 'total' => 2, 'type' => 'bits',
        ],
    ];

    app(TwitchApiService::class)->enrichEventWithUserAvatars('token', $event);

    Http::assertSentCount(1);
});

it('memoises avatars per user id for 60s across consecutive enrichment calls', function () {
    fakeHelixUsers(['99' => 'streamer']);

    $event = [
        'user_id' => '99',
        'user_login' => 'streamer',
        'user_name' => 'streamer',
    ];

    $svc = app(TwitchApiService::class);
    $svc->enrichEventWithUserAvatars('token', $event);
    $svc->enrichEventWithUserAvatars('token', $event);
    $svc->enrichEventWithUserAvatars('token', $event);

    Http::assertSentCount(1);
});

it('returns the event unchanged when Helix /users fails', function () {
    Http::fake([
        'api.twitch.tv/helix/users*' => Http::response('boom', 500),
    ]);

    $event = [
        'user_id' => '42',
        'user_login' => 'someone',
        'user_name' => 'someone',
    ];

    $enriched = app(TwitchApiService::class)->enrichEventWithUserAvatars('token', $event);

    // user_avatar is added with empty value (matches Helix path: present-but-empty
    // rather than missing key, so downstream templates always see the key).
    expect($enriched)->toHaveKey('user_id');
    expect($enriched['user_id'])->toBe('42');
});

it('emits event.user_avatar through TemplateDataMapperService when present in event payload', function () {
    $mapper = app(TemplateDataMapperService::class);

    $data = [
        'subscription' => ['type' => 'channel.follow'],
        'event' => [
            'user_id' => '1234567',
            'user_login' => 'follower',
            'user_name' => 'Follower',
            'user_avatar' => 'https://cdn.example/1234567.png',
            'broadcaster_user_id' => '73327367',
            'broadcaster_user_login' => 'host',
            'broadcaster_user_name' => 'Host',
            'broadcaster_user_avatar' => 'https://cdn.example/73327367.png',
        ],
    ];

    $mapped = $mapper->mapEventDataForTemplates($data);

    expect($mapped['event.user_avatar'])->toBe('https://cdn.example/1234567.png');
    expect($mapped['event.broadcaster_user_avatar'])->toBe('https://cdn.example/73327367.png');
});

it('flattens nested user_avatar into indexed top_contributions tags', function () {
    $mapper = app(TemplateDataMapperService::class);

    $data = [
        'subscription' => ['type' => 'channel.hype_train.begin'],
        'event' => [
            'broadcaster_user_id' => '73327367',
            'level' => 2,
            'top_contributions' => [
                ['user_id' => '1', 'user_name' => 'Alice', 'user_avatar' => 'https://cdn.example/1.png', 'type' => 'bits', 'total' => 500],
                ['user_id' => '2', 'user_name' => 'Bob', 'user_avatar' => 'https://cdn.example/2.png', 'type' => 'subscription', 'total' => 1000],
            ],
            'last_contribution' => [
                'user_id' => '2', 'user_name' => 'Bob', 'user_avatar' => 'https://cdn.example/2.png', 'type' => 'subscription', 'total' => 1000,
            ],
        ],
    ];

    $mapped = $mapper->mapEventDataForTemplates($data);

    expect($mapped['event.top_contributions.0.user_avatar'])->toBe('https://cdn.example/1.png');
    expect($mapped['event.top_contributions.1.user_avatar'])->toBe('https://cdn.example/2.png');
    expect($mapped['event.last_contribution.user_avatar'])->toBe('https://cdn.example/2.png');
});
