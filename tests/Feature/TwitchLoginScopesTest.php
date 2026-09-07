<?php

use App\Services\TwitchScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The Twitch login asks every streamer for exactly REQUIRED_SCOPES. The bot
 * account alone adds BOT_ACCOUNT_SCOPES via ?scopes=bot, because the
 * channel.chat.notification subscription names it as the chatting user and
 * Twitch checks that grant against THIS app's client id - the bot's own
 * Twitch app holding user:bot does not count.
 */
function twitchAuthorizeScopes(string $location): array
{
    $query = parse_url($location, PHP_URL_QUERY) ?? '';
    parse_str($query, $params);

    return TwitchScopeService::sanitizeScopeList($params['scope'] ?? '');
}

test('a plain login asks for REQUIRED_SCOPES and nothing from the bot list', function () {
    $location = $this->get('/auth/redirect/twitch')->assertRedirect()->headers->get('Location');
    $scopes = twitchAuthorizeScopes($location);

    expect(array_diff(TwitchScopeService::REQUIRED_SCOPES, $scopes))->toBe([]);
    expect(array_intersect(TwitchScopeService::BOT_ACCOUNT_SCOPES, $scopes))->toBe([]);
    expect(str_contains($location, 'force_verify=true'))->toBeFalse();
});

test('?scopes=bot adds the bot account scopes on top and forces re-consent', function () {
    $location = $this->get('/auth/redirect/twitch?scopes=bot')->assertRedirect()->headers->get('Location');
    $scopes = twitchAuthorizeScopes($location);

    expect(array_diff(TwitchScopeService::REQUIRED_SCOPES, $scopes))->toBe([]);
    expect(array_diff(TwitchScopeService::BOT_ACCOUNT_SCOPES, $scopes))->toBe([]);
    expect(str_contains($location, 'force_verify=true'))->toBeTrue();
});

test('the bot account scopes are exactly what channel.chat.notification needs from the chatting user', function () {
    expect(TwitchScopeService::BOT_ACCOUNT_SCOPES)->toBe(['user:read:chat', 'user:bot']);
    expect(array_intersect(TwitchScopeService::BOT_ACCOUNT_SCOPES, TwitchScopeService::REQUIRED_SCOPES))->toBe([]);
});
