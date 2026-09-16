<?php

use App\Http\Controllers\ProductController;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Services\Recipes\RecipeManifestValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * A listed recipe carries at most one `category`, and /products is shelves
 * built from it: Products, then Alerts, then anything uncategorised. The
 * filter is the URL (`?category=`), answered by the server, so a shelf can
 * be shared and comes back on the back button. An unknown value is Show
 * all, never a 404, because a first-timer on a stale link should see the
 * page. `installed` is a fourth filter for an account, hidden from a
 * visitor, and is not a manifest category.
 */
function categoryProductUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'categorytester'.fake()->unique()->randomNumber(5)],
    ]);
}

function shelfSlugs(Assert $page): array
{
    return array_column($page->toArray()['props']['products'], 'slug');
}

// ---------------------------------------------------------------------------
// The taxonomy
// ---------------------------------------------------------------------------

it('keeps the manifest schema enum and RecipeCatalog::CATEGORIES identical', function () {
    $schema = json_decode(file_get_contents(base_path('resources/recipes/recipe-manifest.schema.json')), true);

    expect($schema['properties']['category']['enum'])->toBe(array_keys(RecipeCatalog::CATEGORIES));
});

it('gives every listed manifest exactly one category from the taxonomy', function () {
    foreach (app(RecipeCatalog::class)->listed() as $slug => $manifest) {
        expect(array_key_exists('category', $manifest))->toBeTrue("{$slug} is listed without a category")
            ->and(array_key_exists($manifest['category'], RecipeCatalog::CATEGORIES))->toBeTrue("{$slug} carries an unknown category");
    }
});

it('files the four products and the five alerts on the expected shelves', function () {
    $byCategory = collect(app(RecipeCatalog::class)->listed())
        ->groupBy('category', true)
        ->map(fn ($manifests) => $manifests->keys()->sort()->values()->all())
        ->all();

    expect($byCategory)->toBe([
        'alert' => ['bmac_alert', 'fourthwall_alert', 'kofi_alert', 'streamlabs_alert', 'throne_alert'],
        'product' => ['chat_checkin', 'chat_tower', 'follower_bowling', 'twitch_chat'],
    ]);
});

it('rejects a category outside the taxonomy and accepts a manifest with none', function () {
    $validator = new RecipeManifestValidator;
    $catalog = app(RecipeCatalog::class);

    $manifest = $catalog->find('chat_checkin');
    $manifest['category'] = 'game';
    $result = $validator->validate($manifest);
    expect($result['valid'])->toBeFalse()
        ->and(collect($result['errors'])->pluck('pointer'))->toContain('/category');

    $manifest = $catalog->find('coin_flip');
    expect($manifest)->not->toHaveKey('category')
        ->and($validator->validate($manifest)['valid'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// The page
// ---------------------------------------------------------------------------

it('sorts Show all with products first, then alerts, slug order within a shelf', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $page->component('products/index')
                ->where('category', null)
                ->where('shelf', null)
                ->where('installed_count', null)
                ->has('products', 9);

            expect(shelfSlugs($page))->toBe([
                'chat_checkin', 'chat_tower', 'follower_bowling', 'twitch_chat',
                'bmac_alert', 'fourthwall_alert', 'kofi_alert', 'streamlabs_alert', 'throne_alert',
            ]);
        });
});

it('publishes the shelves with their labels, leads and counts', function () {
    $this->get('/products')
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories', 2)
            ->where('categories.0.key', 'product')
            ->where('categories.0.label', 'Products')
            ->where('categories.0.lead', RecipeCatalog::CATEGORIES['product']['lead'])
            ->where('categories.0.count', 4)
            ->where('categories.1.key', 'alert')
            ->where('categories.1.label', 'Alerts')
            ->where('categories.1.count', 5)
        );
});

it('filters to one shelf through the URL', function () {
    $this->get('/products?category=alert')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $page->where('category', 'alert')
                ->where('shelf.label', 'Alerts')
                ->where('shelf.lead', RecipeCatalog::CATEGORIES['alert']['lead'])
                ->has('products', 5);

            expect(shelfSlugs($page))->each->toEndWith('_alert');
        });

    $this->get('/products?category=product')
        ->assertInertia(function (Assert $page) {
            $page->where('category', 'product')->where('shelf.label', 'Products')->has('products', 4);

            expect(shelfSlugs($page))->toBe(['chat_checkin', 'chat_tower', 'follower_bowling', 'twitch_chat']);
        });
});

it('shows all for a category it does not know', function () {
    $this->get('/products?category=games')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('category', null)
            ->where('shelf', null)
            ->has('products', 9)
        );

    $this->get('/products?category[]=alert')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('category', null)->has('products', 9));
});

it('carries each product\'s category, service and install chips onto the card', function () {
    $this->get('/products')
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.slug', 'chat_checkin')
            ->where('products.0.category', 'product')
            ->where('products.0.service', 'checkin')
            ->where('products.0.installs', ['Overlay', 'Integration'])
            ->where('products.1.slug', 'chat_tower')
            ->where('products.1.installs', ['Overlay', 'Integration', 'List'])
            ->where('products.2.slug', 'follower_bowling')
            ->where('products.2.service', null)
            ->where('products.2.installs', ['Overlay', 'List', 'Chat command'])
            ->where('products.6.slug', 'kofi_alert')
            ->where('products.6.category', 'alert')
            ->where('products.6.service', 'kofi')
            ->where('products.6.hero', null)
            ->where('products.6.installs', ['Alert', 'Integration'])
        );
});

it('offers Installed only to an account, and shows what it installed', function () {
    $this->get('/products?category=installed')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('category', null)
            ->where('installed_count', null)
            ->has('products', 9)
        );

    $user = categoryProductUser();

    $this->actingAs($user)
        ->get('/products?category=installed')
        ->assertInertia(fn (Assert $page) => $page
            ->where('category', 'installed')
            ->where('shelf.label', 'Installed')
            ->where('installed_count', 0)
            ->has('products', 0)
        );

    $this->actingAs($user)->post('/products/chat_checkin/install');

    $this->actingAs($user)
        ->get('/products?category=installed')
        ->assertInertia(function (Assert $page) {
            $page->where('category', 'installed')->where('installed_count', 1)->has('products', 1);

            expect(shelfSlugs($page))->toBe(['chat_checkin']);
        });

    $this->actingAs($user)
        ->get('/products?category=alert')
        ->assertInertia(fn (Assert $page) => $page->where('installed_count', 1)->has('products', 5));
});

it('exposes no installed filter constant beyond the one the page reads', function () {
    // The Installed filter is per account and not a manifest category. If it
    // ever lands in CATEGORIES the schema enum would let a manifest declare
    // itself installed for everyone.
    expect(RecipeCatalog::CATEGORIES)->not->toHaveKey('installed')
        ->and((new ReflectionClass(ProductController::class))->getConstant('INSTALLED_FILTER'))->toBe('installed');
});

it('hands a product page the same sidebar, with its own category marked', function () {
    $this->get('/products/kofi_alert')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/show')
            ->where('product.category', 'alert')
            ->has('categories', 2)
            ->where('categories.0.key', 'product')
            ->where('categories.0.count', 4)
            ->where('categories.1.key', 'alert')
            ->where('categories.1.count', 5)
            ->where('installed_count', null)
        );

    $user = categoryProductUser();
    $this->actingAs($user)->post('/products/chat_checkin/install');

    $this->actingAs($user)
        ->get('/products/chat_checkin')
        ->assertInertia(fn (Assert $page) => $page->where('product.category', 'product')->where('installed_count', 1));
});

it('counts only listed products as installed, never a picker recipe instance', function () {
    // An account can hold instances of unlisted recipes (dice, coin_flip:
    // seeded or installed from tinker). They are not on /products, so the
    // Installed link's count must match the cards that filter shows: on
    // one real account the link said 7 while the shelf held 4.
    $user = categoryProductUser();
    $catalog = app(RecipeCatalog::class);
    app(RecipeInstaller::class)->install($catalog->sync($catalog->find('dice')), $user, 'main');
    app(RecipeInstaller::class)->install($catalog->sync($catalog->find('coin_flip')), $user, 'main');
    $this->actingAs($user)->post('/products/chat_checkin/install');

    $this->actingAs($user)
        ->get('/products?category=installed')
        ->assertInertia(function (Assert $page) {
            $page->where('installed_count', 1)->has('products', 1);

            expect(shelfSlugs($page))->toBe(['chat_checkin']);
        });

    $this->actingAs($user)
        ->get('/products/chat_checkin')
        ->assertInertia(fn (Assert $page) => $page->where('installed_count', 1));
});
