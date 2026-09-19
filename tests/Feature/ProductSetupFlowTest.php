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

    $this->actingAs($user)->post('/products/chat-checkin/install')->assertRedirect('/products/chat-checkin');

    expect(ProductSetup::activeSlug($user->fresh()))->toBe('chat-checkin');

    $this->actingAs($user->fresh())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('productSetup.slug', 'chat-checkin')
            ->where('productSetup.name', 'Chat Checkin')
            ->where('productSetup.url', route('products.show', 'chat-checkin'))
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

    $this->actingAs($user)->post('/products/follower-bowling/install')->assertSessionHasErrors('install');

    expect(ProductSetup::activeSlug($user->fresh()))->toBeNull();
});

it('turns ready and ends when the product page sees nothing left', function () {
    $user = flowUser();
    $this->actingAs($user)->post('/products/chat-checkin/install');
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
    expect(ProductSetup::activeSlug($user->fresh()))->toBe('chat-checkin');

    // The product page is what ends it.
    $this->actingAs($user)
        ->get('/products/chat-checkin')
        ->assertInertia(fn (Assert $page) => $page->where('installed.remaining', 0));
    expect(ProductSetup::activeSlug($user->fresh()))->toBeNull();
});

it('refreshes the mod lookup when the product page loads mid-setup', function () {
    $user = flowUser(['bot_enabled' => true]);
    $this->actingAs($user)->post('/products/chat-checkin/install');
    Cache::put('bot:moderated_channels', 'unknown', now()->addMinutes(5));

    $this->actingAs($user->fresh())->get('/products/chat-checkin')->assertOk();

    // Forgotten on load, then re-asked: with no bot token the fresh answer is
    // unknown again, but it is a fresh answer, written after the page ran.
    expect(Cache::get('bot:moderated_channels'))->toBe('unknown');

    ProductSetup::end($user->fresh());
    Cache::put('bot:moderated_channels', ['keep'], now()->addMinutes(5));
    $this->actingAs($user->fresh())->get('/products/chat-checkin')->assertOk();
    expect(Cache::get('bot:moderated_channels'))->toBe(['keep']);
});

it('ends on Not now', function () {
    $user = flowUser();
    $this->actingAs($user)->post('/products/chat-checkin/install');

    $this->actingAs($user->fresh())->post('/products/setup/dismiss')->assertRedirect();

    expect(ProductSetup::activeSlug($user->fresh()))->toBeNull();
});

it('ends on uninstall of the same product only', function () {
    $user = flowUser();
    $this->actingAs($user)->post('/products/follower-bowling/install');
    $this->actingAs($user->fresh())->post('/products/chat-checkin/install');
    expect(ProductSetup::activeSlug($user->fresh()))->toBe('chat-checkin');

    $this->actingAs($user->fresh())->post('/products/follower-bowling/uninstall');
    expect(ProductSetup::activeSlug($user->fresh()))->toBe('chat-checkin');

    $this->actingAs($user->fresh())->post('/products/chat-checkin/uninstall');
    expect(ProductSetup::activeSlug($user->fresh()))->toBeNull();
});

it('shares nothing when the instance is gone by another road', function () {
    $user = flowUser();
    $this->actingAs($user)->post('/products/chat-checkin/install');
    $instance = ProductSetup::instanceFor($user->fresh(), 'chat-checkin');
    $instance->delete();

    $this->actingAs($user->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('productSetup', null));
});

it('keeps the steps in checklist order so the banner names the first missing one', function () {
    $user = flowUser(['bot_enabled' => true]);
    $catalog = app(RecipeCatalog::class);
    $instance = app(RecipeInstaller::class)->install($catalog->sync($catalog->find('follower-bowling')), $user, 'follower_bowling');
    Kit::create(['owner_id' => $user->id, 'title' => 'k', 'is_public' => false]);

    $labels = array_column(ProductSetup::steps($instance), 'label');

    expect($labels[0])->toBe('The bot is switched on')
        ->and($labels)->toContain('Its list still exists', 'You have an overlay link for OBS')
        ->and($labels)->not->toContain('Its integration is connected');
});

it('sends the next step to the page its control is on, with the fragment that finds it', function () {
    $user = flowUser(['bot_enabled' => false]);
    $catalog = app(RecipeCatalog::class);
    app(RecipeInstaller::class)->install($catalog->sync($catalog->find('chat-checkin')), $user, 'chat_checkin');
    ProductSetup::start($user, 'chat-checkin');

    $banner = ProductSetup::banner($user->fresh(), app(RecipeCatalog::class));

    // The bot toggle is the first thing missing, and it lives on the bot
    // settings page - not on the product page the banner button returns to.
    expect($banner['next']['target'])->toBe('bot-toggle')
        ->and($banner['next']['url'])->toBe(route('settings.integrations.bot.show').'#el-bot-toggle')
        ->and($banner['url'])->toBe(route('products.show', 'chat-checkin'));
});

it('omits the fragment for a step with no single control to point at', function () {
    $user = flowUser(['bot_enabled' => true]);
    $catalog = app(RecipeCatalog::class);
    $instance = app(RecipeInstaller::class)->install($catalog->sync($catalog->find('follower-bowling')), $user, 'follower_bowling');
    ProductSetup::start($user, 'follower-bowling');

    // Delete the list so its wire, which names no control, is what is missing.
    OptionSet::find($instance->primitive_map['lists']['lane'])->delete();

    $banner = ProductSetup::banner($user->fresh(), app(RecipeCatalog::class));

    expect($banner['next']['label'])->toBe('Its list still exists')
        ->and($banner['next']['target'])->toBeNull()
        ->and($banner['next']['url'])->toBe(route('lists.index'));
});
