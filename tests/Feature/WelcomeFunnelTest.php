<?php

use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/*
 * Does a new account get from the dashboard to a product on stream? The
 * products listing, a product page and an install each stamp the first time
 * they happen, and the welcome links say which of them carried someone to
 * the listing. The admin funnel page reads it back.
 */

function funnelUser(array $attributes = []): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'funnel'.fake()->unique()->randomNumber(5)],
        ...$attributes,
    ]);
}

function funnelStep(User $user, string $key): mixed
{
    return $user->fresh()->preference('welcome_funnel.'.$key);
}

it('stamps the listing and the banner when the banner carries someone there', function () {
    $this->freezeTime();
    $user = funnelUser();

    $this->actingAs($user)->get('/products?from=welcome-banner')->assertOk();

    expect(funnelStep($user, 'listing_at'))->toBe(now()->timestamp)
        ->and(funnelStep($user, 'listing_from'))->toBe('welcome-banner')
        ->and(funnelStep($user, 'banner_at'))->toBe(now()->timestamp)
        ->and(funnelStep($user, 'dialog_at'))->toBeNull();
});

it('keeps the first visit and its source, and still stamps a later banner click', function () {
    $this->freezeTime();
    $user = funnelUser();
    $first = now()->timestamp;

    $this->actingAs($user)->get('/products');
    $this->travel(5)->minutes();
    $this->actingAs($user->fresh())->get('/products?from=welcome-banner');

    expect(funnelStep($user, 'listing_at'))->toBe($first)
        ->and(funnelStep($user, 'listing_from'))->toBe('direct')
        ->and(funnelStep($user, 'banner_at'))->toBe(now()->timestamp);
});

it('reads an unknown source as direct and stamps no welcome link', function () {
    $user = funnelUser();

    $this->actingAs($user)->get('/products?from=somewhere-else');

    expect(funnelStep($user, 'listing_from'))->toBe('direct')
        ->and(funnelStep($user, 'banner_at'))->toBeNull()
        ->and(funnelStep($user, 'dialog_at'))->toBeNull();
});

it('stamps the dialog button', function () {
    $user = funnelUser();

    $this->actingAs($user)->get('/products?from=welcome-dialog');

    expect(funnelStep($user, 'listing_from'))->toBe('welcome-dialog')
        ->and(funnelStep($user, 'dialog_at'))->not->toBeNull();
});

it('stamps the first product page opened, and only the first', function () {
    $user = funnelUser();

    $this->actingAs($user)->get('/products/chat-checkin')->assertOk();
    $this->actingAs($user->fresh())->get('/products/chat-tower')->assertOk();

    expect(funnelStep($user, 'product_slug'))->toBe('chat-checkin')
        ->and(funnelStep($user, 'product_at'))->not->toBeNull();
});

it('stamps the install, and keeps it through an uninstall', function () {
    $user = funnelUser();

    $this->actingAs($user)->post('/products/chat-checkin/install');
    RecipeInstance::where('user_id', $user->id)->delete();

    expect(funnelStep($user, 'installed_slug'))->toBe('chat-checkin')
        ->and(funnelStep($user, 'installed_at'))->not->toBeNull();
});

it('stamps nothing for a visitor or for an admin looking through an account', function () {
    $this->get('/products?from=welcome-banner')->assertOk();

    $admin = funnelUser(['role' => 'admin']);
    $user = funnelUser();

    $this->actingAs($admin)
        ->withSession(['impersonating_user_id' => $user->id, 'real_admin_id' => $admin->id])
        ->get('/products?from=welcome-banner')
        ->assertOk();

    expect($user->fresh()->preference('welcome_funnel'))->toBeNull()
        ->and($admin->fresh()->preference('welcome_funnel'))->toBeNull();
});

it('hides the funnel page from anyone but an admin', function () {
    $this->actingAs(funnelUser())->get('/admin/funnel')->assertNotFound();
});

it('lists each signup with its steps and counts them', function () {
    $this->freezeTime();
    $admin = funnelUser(['role' => 'admin', 'created_at' => now()->subYear()]);

    $walker = funnelUser();
    $this->actingAs($walker)->get('/products?from=welcome-banner');
    $this->actingAs($walker->fresh())->get('/products/chat-checkin');
    $this->actingAs($walker->fresh())->post('/products/chat-checkin/install');

    // Installed before the funnel existed: no stamp, so the row is the answer.
    $early = funnelUser();
    $catalog = app(RecipeCatalog::class);
    app(RecipeInstaller::class)->install($catalog->sync($catalog->find('chat-checkin')), $early, RecipeInstance::instanceSlugFrom('chat-checkin'));

    $idle = funnelUser();

    $this->actingAs($admin)->get('/admin/funnel?days=30')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($admin, $walker, $early, $idle) {
            $page->component('admin/funnel/index')
                ->where('days', 30);

            // The test database may hold other recent accounts, so the
            // totals are checked against the rows rather than a number.
            $props = $page->toArray()['props'];
            $rows = collect($props['rows'])->keyBy('id');

            expect($props['totals']['signed_up'])->toBe($rows->count())
                ->and($props['totals']['installed'])->toBe($rows->whereNotNull('installed_at')->count())
                ->and($rows->has($admin->id))->toBeFalse()
                ->and($rows[$walker->id]['banner_at'])->not->toBeNull()
                ->and($rows[$walker->id]['product_slug'])->toBe('chat-checkin')
                ->and($rows[$walker->id]['listing_from'])->toBe('welcome-banner')
                ->and($rows[$walker->id]['installed_slug'])->toBe('chat-checkin')
                ->and($rows[$early->id]['installed_at'])->not->toBeNull()
                ->and($rows[$idle->id]['listing_at'])->toBeNull()
                ->and($rows[$idle->id]['installed_at'])->toBeNull();
        });
});
