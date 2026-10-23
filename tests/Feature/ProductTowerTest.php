<?php

use App\Models\ExternalIntegration;
use App\Models\OptionSet;
use App\Models\OverlayAccessLog;
use App\Models\OverlayAccessToken;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Services\Tower\TowerService;
use App\Support\ProductDesigner;
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

    return $catalog->sync($catalog->find('chat-tower'));
}

it('is listed with its hero image, between the bubbles and bowling', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.2.slug', 'chat-tower')
            ->where('products.2.hero', '/products/chat-tower-hero.jpg')
        );

    expect(is_file(public_path('products/chat-tower-hero.jpg')))->toBeTrue()
        ->and(file_get_contents(public_path('products/chat-tower-hero.jpg')))->not->toContain('c2pa');
});

it('installs the integration, the list and the overlay it will create, and no chat commands', function () {
    // Was a page-payload assertion against fields the install/manage page no
    // longer carries (they fed a marketing block that moved to the pitch
    // page's own manifest-driven copy) - the manifest is the fact itself.
    $manifest = app(RecipeCatalog::class)->find('chat-tower');

    expect($manifest['installs']['integrations'] ?? [])->toBe(['tower'])
        ->and($manifest['installs']['lists'][0]['slug'] ?? null)->toBe(TowerService::RECORD_LIST_SLUG)
        ->and($manifest['installs']['overlays'] ?? [])->toHaveCount(1)
        ->and($manifest['installs']['list_appenders'] ?? [])->toBe([])
        ->and($manifest['installs']['bot_aliases'] ?? [])->toBe([])
        ->and($manifest['installs']['bot_commands'] ?? [])->toBe([]);
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

it('says when a real overlay link last loaded the tower, and never counts the designer preview', function () {
    $user = towerUser();
    $this->actingAs($user)->post('/products/chat-tower/install');
    $tower = OverlayTemplate::where('owner_id', $user->id)->where('type', '!=', 'alert')->firstOrFail();

    $page = fn () => $this->actingAs($user->fresh())->get('/products/chat-tower/install');

    // Nothing has loaded it yet: the page offers Add to OBS.
    $page()->assertInertia(fn (Assert $page) => $page->where('installed.overlays.0.loaded_ago', null));

    // The designer's preview token renders the same slug, and is not OBS.
    $preview = OverlayAccessToken::create([
        'user_id' => $user->id, 'name' => ProductDesigner::TOKEN_NAME, 'token_hash' => hash('sha256', 'preview-'.$user->id),
        'token_prefix' => 'prev', 'is_active' => true, 'metadata' => ['purpose' => ProductDesigner::TOKEN_PURPOSE],
    ]);
    OverlayAccessLog::create(['token_id' => $preview->id, 'template_slug' => $tower->slug, 'accessed_at' => now()]);
    $page()->assertInertia(fn (Assert $page) => $page->where('installed.overlays.0.loaded_ago', null));

    // A load of another overlay through a real link says nothing about this one.
    $link = OverlayAccessToken::create([
        'user_id' => $user->id, 'name' => 'OBS - Chat Tower', 'token_hash' => hash('sha256', 'obs-'.$user->id),
        'token_prefix' => 'obs', 'is_active' => true,
    ]);
    OverlayAccessLog::create(['token_id' => $link->id, 'template_slug' => 'someone-elses-slug', 'accessed_at' => now()]);
    $page()->assertInertia(fn (Assert $page) => $page->where('installed.overlays.0.loaded_ago', null));

    // A real link loading the tower is the answer, latest load first.
    OverlayAccessLog::create(['token_id' => $link->id, 'template_slug' => $tower->slug, 'accessed_at' => now()->subDays(2)]);
    OverlayAccessLog::create(['token_id' => $link->id, 'template_slug' => $tower->slug, 'accessed_at' => now()->subMinutes(3)]);
    $page()->assertInertia(fn (Assert $page) => $page->where('installed.overlays.0.loaded_ago', '3 minutes ago'));
});
