<?php

use App\Models\ExternalIntegration;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Services\Tower\TowerService;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * Chat Tower is the third product and the first to install an overlay, an
 * integration and a List together: the tower overlay with its eleven
 * controls, the `tower` integration with its eleven service controls, and
 * the `tower_record` list the record roster is written into.
 */
function towerUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'towertester'.fake()->unique()->randomNumber(5)],
    ], $attrs));
}

function towerRecipe(): Recipe
{
    $catalog = app(RecipeCatalog::class);

    return $catalog->sync($catalog->find('chat_tower'));
}

it('is listed with its hero image, between checkin and bowling', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.1.slug', 'chat_tower')
            ->where('products.1.hero', '/products/chat-tower-hero.svg')
        );

    expect(is_file(public_path('products/chat-tower-hero.svg')))->toBeTrue()
        ->and(file_get_contents(public_path('products/chat-tower-hero.svg')))->not->toContain('c2pa');
});

it('shows the integration, the list and the overlay it will create, and no chat commands', function () {
    $this->get('/products/chat_tower')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.integrations.0', 'tower')
            ->where('product.lists.0.slug', TowerService::RECORD_LIST_SLUG)
            ->where('product.overlays.0.name', 'Chat Tower')
            ->where('product.commands', [])
        );
});

it('installs the tower overlay, connects the integration and creates the record list', function () {
    $user = towerUser();

    $instance = app(RecipeInstaller::class)->install(towerRecipe(), $user, 'chat_tower');

    $template = OverlayTemplate::find($instance->primitive_map['overlays']['tower']);
    expect($template)->not->toBeNull()
        ->and($template->html)->toContain('[[[foreach:tower as block]]]')
        ->and($template->html)->toContain('[[[foreach:c:list:tower_record as name]]]')
        ->and($template->html)->toContain('data-falling="[[[tower.falling]]]"');
    expect(OverlayControl::where('overlay_template_id', $template->id)->count())->toBe(11)
        ->and(OverlayControl::where('overlay_template_id', $template->id)->where('type', 'expression')->count())->toBe(10);

    $integration = ExternalIntegration::where('user_id', $user->id)->where('service', 'tower')->first();
    expect($integration)->not->toBeNull()
        ->and($integration->enabled)->toBeTrue()
        ->and($instance->primitive_map['integrations']['tower']['created'])->toBeTrue()
        ->and(OverlayControl::where('user_id', $user->id)->where('source', 'tower')->where('source_managed', true)->count())->toBe(11);

    $list = OptionSet::find($instance->primitive_map['lists']['record']);
    expect($list)->not->toBeNull()
        ->and($list->slug)->toBe(TowerService::RECORD_LIST_SLUG)
        ->and($list->recipe_instance_id)->toBe($instance->id)
        ->and($list->items)->toBe([]);
});

it('refuses to install over an existing list with the slug tower_record', function () {
    $user = towerUser();
    OptionSet::create([
        'user_id' => $user->id, 'slug' => TowerService::RECORD_LIST_SLUG, 'label' => 'Mine', 'items' => [], 'next_item_id' => 1,
        'min_items' => 0, 'max_items' => null, 'user_editable' => true,
    ]);

    expect(fn () => app(RecipeInstaller::class)->install(towerRecipe(), $user, 'chat_tower'))
        ->toThrow(RuntimeException::class, "slug 'tower_record'");

    expect(RecipeInstance::where('user_id', $user->id)->count())->toBe(0)
        ->and(OverlayTemplate::where('owner_id', $user->id)->count())->toBe(0);
});

it('reports the integration and list wires and leaves the command wire out', function () {
    $user = towerUser(['bot_enabled' => true]);
    $instance = app(RecipeInstaller::class)->install(towerRecipe(), $user, 'chat_tower');

    $states = WiringFacts::productSubject($instance)['states'];
    expect($states['product.integration'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.list'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.command'])->toBe(WiringCatalog::NOT_APPLICABLE);

    ExternalIntegration::where('user_id', $user->id)->where('service', 'tower')->update(['enabled' => false]);
    expect(WiringFacts::productSubject($instance->fresh())['states']['product.integration'])->toBe(WiringCatalog::MISSING);
});
