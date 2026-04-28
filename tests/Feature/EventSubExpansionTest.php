<?php

use App\Models\User;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('null twitch_scopes falls back to legacy scope set', function () {
    $user = User::factory()->create(['twitch_scopes' => null]);

    $svc = app(TwitchScopeService::class);

    expect($svc->getUserScopes($user))->toEqual(TwitchScopeService::LEGACY_SCOPES);
});

test('getMissingScopes returns the four new scopes for a legacy user', function () {
    $user = User::factory()->create(['twitch_scopes' => null]);

    $missing = app(TwitchScopeService::class)->getMissingScopes($user);

    expect($missing)->toContain('channel:read:hype_train')
        ->toContain('channel:read:charity')
        ->toContain('channel:read:polls')
        ->toContain('channel:read:predictions');
});

test('getMissingScopes returns empty when the user has all required scopes', function () {
    $user = User::factory()->create([
        'twitch_scopes' => TwitchScopeService::REQUIRED_SCOPES,
    ]);

    expect(app(TwitchScopeService::class)->getMissingScopes($user))->toBe([]);
});

test('hasScope reports the correct state for each required scope', function () {
    $user = User::factory()->create([
        'twitch_scopes' => ['channel:read:polls'],
    ]);

    $svc = app(TwitchScopeService::class);

    expect($svc->hasScope($user, 'channel:read:polls'))->toBeTrue();
    expect($svc->hasScope($user, 'channel:read:predictions'))->toBeFalse();
});

test('sanitizeScopeList handles space-separated strings and filters empties', function () {
    $result = TwitchScopeService::sanitizeScopeList('user:read:email channel:read:polls ');

    expect($result)->toBe(['user:read:email', 'channel:read:polls']);

    expect(TwitchScopeService::sanitizeScopeList(''))->toBe([]);
    expect(TwitchScopeService::sanitizeScopeList([' user:read:email ', '', 'user:read:email']))
        ->toBe(['user:read:email']);
});

test('mapEventDataForTemplates flattens top_contributions into indexed keys', function () {
    $mapper = app(TemplateDataMapperService::class);

    $data = [
        'subscription' => ['type' => 'channel.hype_train.begin'],
        'event' => [
            'broadcaster_user_id' => '123',
            'level' => 2,
            'top_contributions' => [
                ['user_id' => '1', 'user_name' => 'Alice', 'type' => 'bits', 'total' => 500],
                ['user_id' => '2', 'user_name' => 'Bob', 'type' => 'subscription', 'total' => 1000],
            ],
        ],
    ];

    $mapped = $mapper->mapEventDataForTemplates($data);

    expect($mapped['event.type'])->toBe('channel.hype_train.begin');
    expect($mapped['event.top_contributions.count'])->toBe(2);
    expect($mapped['event.top_contributions.0.user_name'])->toBe('Alice');
    expect($mapped['event.top_contributions.1.user_name'])->toBe('Bob');
});

test('mapEventDataForTemplates flattens poll choices with count', function () {
    $mapper = app(TemplateDataMapperService::class);

    $data = [
        'subscription' => ['type' => 'channel.poll.begin'],
        'event' => [
            'title' => 'Favorite color',
            'choices' => [
                ['id' => 'c1', 'title' => 'Red', 'votes' => 10],
                ['id' => 'c2', 'title' => 'Blue', 'votes' => 7],
            ],
        ],
    ];

    $mapped = $mapper->mapEventDataForTemplates($data);

    expect($mapped['event.title'])->toBe('Favorite color');
    expect($mapped['event.choices.count'])->toBe(2);
    expect($mapped['event.choices.0.title'])->toBe('Red');
    expect($mapped['event.choices.1.votes'])->toBe(7);
});

test('mapEventDataForTemplates flattens poll winners (single winner)', function () {
    $mapper = app(TemplateDataMapperService::class);

    $data = [
        'subscription' => ['type' => 'channel.poll.end'],
        'event' => [
            'winners' => [
                ['id' => 'c1', 'title' => 'Red', 'votes' => 21, 'bits_votes' => 6, 'channel_points_votes' => 9],
            ],
        ],
    ];

    $mapped = $mapper->mapEventDataForTemplates($data);

    expect($mapped['event.winners.count'])->toBe(1);
    expect($mapped['event.winners.0.id'])->toBe('c1');
    expect($mapped['event.winners.0.title'])->toBe('Red');
    expect($mapped['event.winners.0.votes'])->toBe(21);
});

test('mapEventDataForTemplates flattens poll winners (tie)', function () {
    $mapper = app(TemplateDataMapperService::class);

    $data = [
        'subscription' => ['type' => 'channel.poll.end'],
        'event' => [
            'winners' => [
                ['id' => 'c1', 'title' => 'Red', 'votes' => 5],
                ['id' => 'c2', 'title' => 'Blue', 'votes' => 5],
            ],
        ],
    ];

    $mapped = $mapper->mapEventDataForTemplates($data);

    expect($mapped['event.winners.count'])->toBe(2);
    expect($mapped['event.winners.0.title'])->toBe('Red');
    expect($mapped['event.winners.1.title'])->toBe('Blue');
});

test('mapEventDataForTemplates flattens poll winners (all-zero votes)', function () {
    $mapper = app(TemplateDataMapperService::class);

    $data = [
        'subscription' => ['type' => 'channel.poll.begin'],
        'event' => [
            'winners' => [
                ['id' => 'c1', 'title' => 'Red', 'votes' => 0],
                ['id' => 'c2', 'title' => 'Blue', 'votes' => 0],
                ['id' => 'c3', 'title' => 'Green', 'votes' => 0],
            ],
        ],
    ];

    $mapped = $mapper->mapEventDataForTemplates($data);

    expect($mapped['event.winners.count'])->toBe(3);
    expect($mapped['event.winners.0.title'])->toBe('Red');
    expect($mapped['event.winners.2.title'])->toBe('Green');
});

test('mapEventDataForTemplates formats charity amount as a currency string', function () {
    $mapper = app(TemplateDataMapperService::class);

    $data = [
        'subscription' => ['type' => 'channel.charity_campaign.donate'],
        'event' => [
            'user_name' => 'Carol',
            'amount' => ['value' => 1523, 'decimal_places' => 2, 'currency' => 'USD'],
        ],
    ];

    $mapped = $mapper->mapEventDataForTemplates($data);

    expect($mapped['event.amount.value'])->toBe(1523);
    expect($mapped['event.amount.currency'])->toBe('USD');
    expect($mapped['event.amount.formatted'])->toBe('$15.23');
});

test('EVENT_TYPE_TO_SCOPE maps each expanded event type to its scope', function () {
    $map = TwitchScopeService::EVENT_TYPE_TO_SCOPE;

    expect($map['channel.hype_train.begin'])->toBe('channel:read:hype_train');
    expect($map['channel.charity_campaign.donate'])->toBe('channel:read:charity');
    expect($map['channel.goal.begin'])->toBe('channel:read:goals');
    expect($map['channel.poll.begin'])->toBe('channel:read:polls');
    expect($map['channel.prediction.begin'])->toBe('channel:read:predictions');
});
