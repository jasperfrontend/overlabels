<?php

use App\Jobs\OnboardNewUser;
use App\Models\EventTemplateMapping;
use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Kits\FeaturedKits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/*
 * A new account lands on a kit picker (`kits.start`) offering the featured
 * kits, instead of silently getting a copy of the starter kit. The same
 * featured kits lead the Alerts shelf on /products.
 */

/** A public kit with one alert that declares the given services' donation events. */
function pickerKit(array $attrs = [], array $services = ['kofi']): Kit
{
    $owner = User::factory()->create();
    $alert = OverlayTemplate::factory()->create(['owner_id' => $owner->id, 'fork_of_id' => null, 'type' => 'alert']);
    $kit = Kit::factory()->create(array_merge(['owner_id' => $owner->id, 'is_public' => true, 'forked_from_id' => null], $attrs));
    $kit->templates()->attach($alert->id);
    $kit->alert_triggers = array_map(fn (string $s) => ['template_id' => $alert->id, 'service' => $s, 'event_type' => 'donation', 'duration_ms' => 8000], $services);
    $kit->save();

    return $kit;
}

function pickerLoginAsNewAccount(?string $redirectTo = null)
{
    Http::fake();
    Queue::fake();

    $socialiteUser = (new SocialiteUser)->map([
        'id' => '987654321',
        'nickname' => 'brandnewstreamer',
        'name' => 'Brand New Streamer',
        'avatar' => 'https://example.test/avatar.png',
    ]);
    $socialiteUser->user = ['id' => '987654321', 'login' => 'brandnewstreamer'];
    $socialiteUser->token = 'access-token';
    $socialiteUser->refreshToken = 'refresh-token';
    $socialiteUser->expiresIn = 3600;
    $socialiteUser->approvedScopes = ['channel:read:subscriptions'];

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('twitch')->andReturn($provider);

    test()->get('/login'.($redirectTo ? '?redirect_to='.urlencode($redirectTo) : ''))->assertOk();

    return test()->get('/auth/callback/twitch');
}

test('featured kits are the public ones with featured_at, oldest feature first', function () {
    $second = pickerKit(['featured_at' => now()->subDay(), 'title' => 'Second'], ['bmac', 'throne']);
    $first = pickerKit(['featured_at' => now()->subDays(2), 'title' => 'First']);
    pickerKit(['title' => 'Not featured']);
    pickerKit(['featured_at' => now(), 'is_public' => false, 'title' => 'Private']);

    $cards = app(FeaturedKits::class)->cards();

    expect(array_column($cards, 'id'))->toBe([$first->id, $second->id])
        ->and($cards[1]['covers'])->toBe(['bmac', 'throne'])
        ->and($cards[1]['alerts'])->toBe(1);
});

test('a copy of a featured kit is not featured', function () {
    $kit = pickerKit(['featured_at' => now()]);

    $copy = $kit->fork(User::factory()->create());

    expect($copy->fresh()->featured_at)->toBeNull();
});

test('the kit picker lists the featured kits', function () {
    $kit = pickerKit(['featured_at' => now()]);
    pickerKit();

    $this->actingAs(User::factory()->create())
        ->get(route('kits.start'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('kits/start')
            ->has('kits', 1)
            ->where('kits.0.id', $kit->id));
});

test('the products page sends featured kits only when the Alerts shelf is on it', function () {
    pickerKit(['featured_at' => now()]);

    $this->get('/products')->assertInertia(fn (AssertableInertia $page) => $page->has('featured_kits', 1));
    $this->get('/products?category=alert')->assertInertia(fn (AssertableInertia $page) => $page->has('featured_kits', 1));
    $this->get('/products?category=product')->assertInertia(fn (AssertableInertia $page) => $page->has('featured_kits', 0));
});

test('onboarding no longer copies the starter kit or wires any alert', function () {
    pickerKit(['is_starter_kit' => true]);
    $user = User::factory()->create(['webhook_secret' => null]);

    (new OnboardNewUser($user))->handle();

    expect(Kit::where('owner_id', $user->id)->exists())->toBeFalse()
        ->and(OverlayTemplate::where('owner_id', $user->id)->exists())->toBeFalse()
        ->and(EventTemplateMapping::where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->fresh()->webhook_secret)->not->toBeNull();
});

test('a brand new account lands on the kit picker after its first login', function () {
    pickerLoginAsNewAccount()->assertRedirect(route('kits.start', absolute: false));

    expect(User::where('twitch_id', '987654321')->exists())->toBeTrue();
});

test('a brand new account that was going somewhere still goes there', function () {
    $target = url('/products/twitch-chat-plinko/install');

    pickerLoginAsNewAccount($target)->assertRedirect($target);
});

test('the kits page leads with the featured kits and keeps them out of the public list', function () {
    $featured = pickerKit(['featured_at' => now()]);
    $plain = pickerKit();

    $this->actingAs(User::factory()->create())
        ->get(route('kits.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('kits/index')
            ->has('featuredKits', 1)
            ->where('featuredKits.0.id', $featured->id)
            ->has('recentPublicKits', 1)
            ->where('recentPublicKits.0.id', $plain->id));
});
