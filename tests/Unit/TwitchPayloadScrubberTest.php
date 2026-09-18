<?php

use App\Services\TwitchPayloadScrubber;

// Twitch events are mostly public identity - display name, login, id, avatar -
// which is the whole point of a follower alert. These are the two cases that
// are not.

it('drops per-viewer prediction wagers', function () {
    $clean = TwitchPayloadScrubber::scrub([
        'title' => 'Will it work',
        'outcomes' => [
            [
                'id' => 'o1',
                'title' => 'Yes',
                'users' => 12,
                'channel_points' => 4000,
                'top_predictors' => [
                    ['user_name' => 'Alice', 'user_id' => '1', 'channel_points_used' => 1000, 'channel_points_won' => 2000],
                    ['user_name' => 'Bob', 'user_id' => '2', 'channel_points_used' => 500, 'channel_points_won' => 0],
                ],
            ],
        ],
    ]);

    expect($clean['outcomes'][0])->not->toHaveKey('top_predictors')
        ->and($clean['outcomes'][0]['users'])->toBe(12)
        ->and($clean['outcomes'][0]['channel_points'])->toBe(4000);

    $flat = json_encode($clean);
    expect($flat)->not->toContain('Alice')
        ->and($flat)->not->toContain('channel_points_used');
});

it('nulls the cheerer identity when the payload says anonymous', function () {
    $clean = TwitchPayloadScrubber::scrub([
        'is_anonymous' => true,
        'user_id' => '12345',
        'user_login' => 'sneaky',
        'user_name' => 'Sneaky',
        'user_avatar' => 'https://example.test/a.png',
        'bits' => 100,
        'message' => 'cheer100 hello',
    ]);

    expect($clean['user_id'])->toBeNull()
        ->and($clean['user_login'])->toBeNull()
        ->and($clean['user_name'])->toBeNull()
        ->and($clean['user_avatar'])->toBeNull()
        ->and($clean['bits'])->toBe(100);

    expect(json_encode($clean))->not->toContain('Sneaky')
        ->and(json_encode($clean))->not->toContain('sneaky');
});

it('leaves a named cheer alone', function () {
    $clean = TwitchPayloadScrubber::scrub([
        'is_anonymous' => false,
        'user_id' => '12345',
        'user_login' => 'generous',
        'user_name' => 'Generous',
        'bits' => 100,
    ]);

    expect($clean['user_name'])->toBe('Generous')
        ->and($clean['user_login'])->toBe('generous')
        ->and($clean['user_id'])->toBe('12345');
});

it('leaves a normal follow payload completely untouched', function () {
    $payload = [
        'user_id' => '1',
        'user_login' => 'newfollower',
        'user_name' => 'NewFollower',
        'broadcaster_user_id' => '2',
        'followed_at' => '2026-09-18T12:00:00Z',
    ];

    expect(TwitchPayloadScrubber::scrub($payload))->toBe($payload);
});

// Hype train contributor lists are a public leaderboard Twitch shows on stream,
// so they stay. This pins that the scrubber is narrow rather than greedy.
it('keeps hype train top contributions', function () {
    $clean = TwitchPayloadScrubber::scrub([
        'top_contributions' => [
            ['user_name' => 'Alice', 'type' => 'bits', 'total' => 500],
        ],
    ]);

    expect($clean['top_contributions'][0]['user_name'])->toBe('Alice');
});
