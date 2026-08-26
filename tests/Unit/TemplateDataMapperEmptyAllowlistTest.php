<?php

use App\Services\TemplateDataMapperService;

/**
 * extractTemplateTags() stores [] for a template that references no tag.
 * mapForTemplate() used to treat [] like null - no allowlist, ship everything -
 * so a tag-less alert broadcast the whole catalogue and blew Reverb's 10 KB
 * ceiling. An array allowlist prunes, empty included; null alone means
 * "no allowlist" (preview, playground).
 */
$twitchData = [
    'user' => ['display_name' => 'Streamer', 'login' => 'streamer'],
    'channel' => ['title' => 'A title', 'game_name' => 'A game'],
    'subscribers' => [
        'total' => 1,
        'data' => [['user_name' => 'Alice', 'tier' => '1000']],
    ],
];

test('an empty allowlist yields an empty payload', function () use ($twitchData) {
    $service = new TemplateDataMapperService;

    expect($service->mapForTemplate($twitchData, 'test', []))->toBe([]);
});

test('an empty allowlist yields an empty payload even with event data', function () use ($twitchData) {
    $service = new TemplateDataMapperService;

    $result = $service->mapForTemplate($twitchData, 'test', [], ['event' => ['user_name' => 'Follower', 'broadcaster_user_id' => '1']]);

    expect($result)->toBe([]);
});

test('a null allowlist still yields the full mapped set', function () use ($twitchData) {
    $service = new TemplateDataMapperService;

    $result = $service->mapForTemplate($twitchData, 'test', null, ['event' => ['user_name' => 'Follower']]);

    expect($result)->toHaveKey('channel_title')
        ->and($result)->toHaveKey('event.user_name')
        ->and(count($result))->toBeGreaterThan(20);
});

test('a one-entry allowlist yields exactly that entry', function () use ($twitchData) {
    $service = new TemplateDataMapperService;

    $result = $service->mapForTemplate($twitchData, 'test', ['channel_title']);

    expect($result)->toBe(['channel_title' => 'A title']);
});
