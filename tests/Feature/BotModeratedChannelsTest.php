<?php

use App\Models\BotToken;
use App\Models\User;
use App\Services\BotModeratedChannels;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

/**
 * "Is the bot a moderator in this channel" answered from the bot's own side:
 * one Helix call with the bot token lists every channel it moderates. No
 * streamer grants anything. Unknown is a real answer and never a finding.
 */
function botTokenWith(array $scopes): void
{
    BotToken::updateOrCreate(
        ['account' => 'overlabels'],
        [
            'access_token' => 'bot-access',
            'refresh_token' => 'bot-refresh',
            'expires_at' => now()->addHour()->timestamp,
            'obtained_at' => now()->timestamp,
            'scopes' => $scopes,
        ],
    );
}

function moddedUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'modtester'.fake()->unique()->randomNumber(5)],
        'bot_enabled' => true,
    ], $attrs));
}

function installCheckin(User $user)
{
    $catalog = app(RecipeCatalog::class);

    return app(RecipeInstaller::class)->install($catalog->sync($catalog->find('chat_checkin')), $user, 'chat_checkin');
}

beforeEach(function () {
    Cache::flush();
    config(['services.twitchbot.user_id' => '1130071166', 'services.twitchbot.client_id' => 'bot-client']);
});

it('lists the broadcasters the bot moderates, following pagination, with the bot token and client id', function () {
    botTokenWith(['user:bot', BotModeratedChannels::SCOPE]);
    Http::fake([
        'api.twitch.tv/helix/moderation/channels*' => Http::sequence()
            ->push(['data' => [['broadcaster_id' => '111'], ['broadcaster_id' => '222']], 'pagination' => ['cursor' => 'next']])
            ->push(['data' => [['broadcaster_id' => '333']], 'pagination' => []]),
    ]);

    $ids = app(BotModeratedChannels::class)->broadcasterIds();

    expect($ids)->toBe(['111', '222', '333']);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer bot-access')
        && $request->hasHeader('Client-Id', 'bot-client')
        && $request['user_id'] === '1130071166');
    Http::assertSentCount(2);
});

it('caches the answer so a page view does not cost a Helix call', function () {
    botTokenWith([BotModeratedChannels::SCOPE]);
    Http::fake(['api.twitch.tv/*' => Http::response(['data' => [['broadcaster_id' => '111']], 'pagination' => []])]);

    $service = app(BotModeratedChannels::class);
    expect($service->moderates('111'))->toBeTrue()
        ->and($service->moderates('999'))->toBeFalse();
    Http::assertSentCount(1);
});

it('answers unknown, and caches that, when the token lacks the scope', function () {
    botTokenWith(['user:bot']);
    Http::fake();

    $service = app(BotModeratedChannels::class);
    expect($service->moderates('111'))->toBeNull()
        ->and($service->moderates('111'))->toBeNull();
    Http::assertNothingSent();
});

it('answers unknown when there is no bot token or Twitch refuses', function () {
    Http::fake(['api.twitch.tv/*' => Http::response(['error' => 'Unauthorized'], 401)]);

    expect(app(BotModeratedChannels::class)->broadcasterIds())->toBeNull();

    botTokenWith([BotModeratedChannels::SCOPE]);
    app(BotModeratedChannels::class)->forget();
    expect(app(BotModeratedChannels::class)->broadcasterIds())->toBeNull();
});

it('lights the product wire from the bot lookup, in all three states', function () {
    $user = moddedUser();
    $instance = installCheckin($user);

    // Unknown: no token at all.
    expect(WiringFacts::productSubject($instance)['states']['product.bot_modded'])->toBe(WiringCatalog::NOT_APPLICABLE);

    // A second Http::fake() does not replace the first one's stubs, so both
    // answers come from one sequence: first a list without this channel,
    // then one with it.
    botTokenWith([BotModeratedChannels::SCOPE]);
    Http::fake([
        'api.twitch.tv/*' => Http::sequence()
            ->push(['data' => [['broadcaster_id' => '000']], 'pagination' => []])
            ->push(['data' => [['broadcaster_id' => $user->twitch_id]], 'pagination' => []]),
    ]);

    app(BotModeratedChannels::class)->forget();
    expect(WiringFacts::productSubject($instance)['states']['product.bot_modded'])->toBe(WiringCatalog::MISSING);

    app(BotModeratedChannels::class)->forget();
    expect(WiringFacts::productSubject($instance)['states']['product.bot_modded'])->toBe(WiringCatalog::SATISFIED);
});

it('does not ask about mod status while the bot is switched off', function () {
    $user = moddedUser(['bot_enabled' => false]);
    $instance = installCheckin($user);
    botTokenWith([BotModeratedChannels::SCOPE]);
    Http::fake();

    expect(WiringFacts::productSubject($instance)['states']['product.bot_modded'])->toBe(WiringCatalog::NOT_APPLICABLE);
    Http::assertNothingSent();
});
