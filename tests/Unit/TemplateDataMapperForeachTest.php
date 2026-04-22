<?php

use App\Services\TemplateDataMapperService;

test('mapForTemplate emits indexed subscribers keys up to provided cap', function () {
    $service = new TemplateDataMapperService;
    $twitchData = [
        'subscribers' => [
            'total' => 3,
            'data' => [
                ['user_name' => 'Alice', 'tier' => '1000'],
                ['user_name' => 'Bob', 'tier' => '2000'],
                ['user_name' => 'Carol', 'tier' => '1000'],
            ],
        ],
    ];

    $result = $service->mapForTemplate(
        $twitchData,
        'test',
        // allowlist — include the flat indexed keys so prune doesn't drop them
        ['subscribers.count', 'subscribers.0.user_name', 'subscribers.1.user_name'],
        null,
        ['subscribers' => 2, 'goals' => 3, 'followers' => 5, 'followed' => 5]
    );

    expect($result['subscribers.count'])->toBe(3);
    expect($result['subscribers.0.user_name'])->toBe('Alice');
    expect($result['subscribers.1.user_name'])->toBe('Bob');
    // Cap is 2, so index 2 is never emitted
    expect($result)->not->toHaveKey('subscribers.2.user_name');
});

test('mapForTemplate emits indexed channel_followers keys with alias rename', function () {
    $service = new TemplateDataMapperService;
    $twitchData = [
        'channel_followers' => [
            'total' => 2,
            'data' => [
                ['user_name' => 'Dave', 'followed_at' => '2025-01-01T00:00:00Z'],
                ['user_name' => 'Eve', 'followed_at' => '2025-01-02T00:00:00Z'],
            ],
        ],
    ];

    $result = $service->mapForTemplate(
        $twitchData,
        'test',
        ['channel_followers.count', 'channel_followers.0.user_name', 'channel_followers.1.user_name'],
        null,
        ['subscribers' => 10, 'goals' => 3, 'followers' => 5, 'followed' => 5]
    );

    expect($result['channel_followers.count'])->toBe(2);
    expect($result['channel_followers.0.user_name'])->toBe('Dave');
    expect($result['channel_followers.1.user_name'])->toBe('Eve');
});

test('mapForTemplate preserves existing scalar tags alongside indexed ones', function () {
    $service = new TemplateDataMapperService;
    $twitchData = [
        'subscribers' => [
            'total' => 2,
            'points' => 99,
            'data' => [
                ['user_name' => 'Alice'],
                ['user_name' => 'Bob'],
            ],
        ],
    ];

    $result = $service->mapForTemplate(
        $twitchData,
        'test',
        ['subscribers_total', 'subscribers_points', 'subscribers_latest_user_name', 'subscribers.count', 'subscribers.0.user_name'],
        null,
        ['subscribers' => 2, 'goals' => 3, 'followers' => 5, 'followed' => 5]
    );

    expect($result['subscribers_total'])->toBe(2);
    expect($result['subscribers_latest_user_name'])->toBe('Alice');
    expect($result['subscribers.count'])->toBe(2);
    expect($result['subscribers.0.user_name'])->toBe('Alice');
});

test('mapForTemplate emits count=0 when the source list is missing', function () {
    $service = new TemplateDataMapperService;

    $result = $service->mapForTemplate(
        [],
        'test',
        ['subscribers.count', 'goals.count', 'channel_followers.count', 'followed_channels.count'],
        null,
        ['subscribers' => 10, 'goals' => 3, 'followers' => 5, 'followed' => 5]
    );

    expect($result['subscribers.count'])->toBe(0);
    expect($result['goals.count'])->toBe(0);
    expect($result['channel_followers.count'])->toBe(0);
    expect($result['followed_channels.count'])->toBe(0);
});
