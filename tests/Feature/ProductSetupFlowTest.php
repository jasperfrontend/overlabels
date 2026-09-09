<?php

use App\Models\BotToken;
use App\Models\Kit;
use App\Models\OptionSet;
use App\Models\OverlayAccessToken;
use App\Models\User;
use App\Services\BotModeratedChannels;
use App\Services\BotPresence;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\ProductSetup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * The setup flow: one fact on the user, set by Install, shown as a banner on
 * every app page while steps remain, ended by the product page seeing
 * nothing left, by "Not now", or by an uninstall.
 */
function flowUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'flowtester'.fake()->unique()->randomNumber(5)],
        'bot_enabled' => false,
    ], $attrs));
}

function flowToken(User $user): void
{
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'name' => 'OBS',
        'token_hash' => hash('sha256', str_repeat('b', 64)),
        'token_prefix' => 'bbbbbbbb',
        'is_active' => true,
    ]);
}

beforeEach(function () {
    Cache::flush();
});

it('starts the flow on install and shares the banner on an app page', function () {
    $user = flowUser();

    $this->actingAs($user)->post('/products/chat_checkin/install')->assertRedirect('/products/chat_checkin');

    expect(ProductSetup::activeSlug($user->fresh()))->toBe('chat_checkin');

    $this->actingAs($user->fresh())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('productSetup.slug', 'chat_checkin')
            ->where('productSetup.name', 'Chat Checkin')
            ->where('productSetup.url', route('products.show', 'chat_checkin'))
            ->where('productSetup.ready', false)
            ->where('productSetup.remaining', 2)
            ->where('productSetup.next.label', 'The bot is switched on')
            ->where('productSetup.next.todo', 'make sure the bot is switched on')
            ->where('productSetup.next.target', 'bot-toggle')
        );
});

it('shares nothing when no flow is active, for a guest and for a member', function () {
    $user = flowUser();

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('productSetup', null));
});

it('does not start a flow when the install is refused', function () {
    $user = flowUser();
    OptionSet::create([
        'user_id' => $user->id, 'slug' => 'lane', 'items' => [], 'next_item_id' => 1,
        'min_items' => 0, 'max_items' => null, 'user_editable' => true,
    ]);

    $this->actingAs($user)->post('/products/follower_bowling/install')->assertSessionHasErrors('install');

    expect(ProductSetup::activeSlug($user->fresh()))->toBeNull();
});

it('turns ready and ends when the product page sees nothing left', function () {
    $user = flowUser();
    $this->actingAs($user)->post('/products/chat_checkin/install');
    $user = $user->fresh();

    // Finish the human steps: bot on, a token, and the bot lookups answering.
    $user->forceFill(['bot_enabled' => true])->save();
    flowToken($user);
    app(BotPresence::class)->record([strtolower($user->twitch_data['login'])]);
    BotToken::updateOrCreate(['account' => 'overlabels'], [
        'access_token' => 'a', 'refresh_token' => 'r', 'expires_at' => now()->addHour()->timestamp,
        'obtained_at' => now()->timestamp, 'scopes' => [BotModeratedChannels::SCOPE],
    ]);
    config(['services.twitchbot.user_id' => '1130071166', 'services.twitchbot.client_id' => 'bot-client']);
    Http::fake(['api.twitch.tv/*' => Http::response(['data' => [['broadcaster_id' => $user->twitch_id]], 'pagination' => []])]);

    // Elsewhere in the app the banner is green with the go-to link.
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('productSetup.ready', true)->where('productSetup.remaining', 0));
    expect(ProductSetup::activeSlug($user->fresh()))->toBe('chat_checkin');

    // The product page is what ends it.
    $this->actingAs($user)
        ->get('/products/chat_checkin')
        ->assertInertia(fn (Assert $page) => $page->where('installed.remaining', 0));
    expect(ProductSetup::activeSlug($user->fresh()))->toBeNull();
});

it('refreshes the mod lookup when the product page loads mid-setup', function () {
    $user = flowUser(['bot_enabled' => true]);
    $this->actingAs($user)->post('/products/chat_checkin/install');
    Cache::put('bot:moderated_channels', 'unknown', now()->addMinutes(5));

    $this->actingAs($user->fresh())->get('/products/chat_checkin')->assertOk();

    // Forgotten on load, then re-asked: with no bot token the fresh answer is
    // unknown again, but it is a fresh answer, written after the page ran.
    expect(Cache::get('bot:moderated_channels'))->toBe('unknown');

    ProductSetup::end($user->fresh());
    Cache::put('bot:moderated_channels', ['keep'], now()->addMinutes(5));
    $this->actingAs($user->fresh())->get('/products/chat_checkin')->assertOk();
    expect(Cache::get('bot:moderated_channels'))->toBe(['keep']);
});

it('ends on Not now', function () {
    $user = flowUser();
    $this->actingAs($user)->post('/products/chat_checkin/install');

    $this->actingAs($user->fresh())->post('/products/setup/dismiss')->assertRedirect();

    expect(ProductSetup::activeSlug($user->fresh()))->toBeNull();
});

it('ends on uninstall of the same product only', function () {
    $user = flowUser();
    $this->actingAs($user)->post('/products/follower_bowling/install');
    $this->actingAs($user->fresh())->post('/products/chat_checkin/install');
    expect(ProductSetup::activeSlug($user->fresh()))->toBe('chat_checkin');

    $this->actingAs($user->fresh())->post('/products/follower_bowling/uninstall');
    expect(ProductSetup::activeSlug($user->fresh()))->toBe('chat_checkin');

    $this->actingAs($user->fresh())->post('/products/chat_checkin/uninstall');
    expect(ProductSetup::activeSlug($user->fresh()))->toBeNull();
});

it('shares nothing when the instance is gone by another road', function () {
    $user = flowUser();
    $this->actingAs($user)->post('/products/chat_checkin/install');
    $instance = ProductSetup::instanceFor($user->fresh(), 'chat_checkin');
    $instance->delete();

    $this->actingAs($user->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('productSetup', null));
});

it('keeps the steps in checklist order so the banner names the first missing one', function () {
    $user = flowUser(['bot_enabled' => true]);
    $catalog = app(RecipeCatalog::class);
    $instance = app(RecipeInstaller::class)->install($catalog->sync($catalog->find('follower_bowling')), $user, 'follower_bowling');
    Kit::create(['owner_id' => $user->id, 'title' => 'k', 'is_public' => false]);

    $labels = array_column(ProductSetup::steps($instance), 'label');

    expect($labels[0])->toBe('The bot is switched on')
        ->and($labels)->toContain('Its list still exists', 'You have an overlay link for OBS')
        ->and($labels)->not->toContain('Its integration is connected');
});
