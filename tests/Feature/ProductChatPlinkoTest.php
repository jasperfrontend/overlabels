<?php

use App\Models\ExternalIntegration;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Services\TemplateDataMapperService;
use App\Support\OverlayMarkdown;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * Chat Plinko is the sixth product and the first that ships its own
 * script: the overlay is one `[[[plinko_board]]]` tag, which the renderer
 * swaps for a canvas the lazy plinko chunk draws, fed by every `!drop` it
 * reads straight from chat. Nothing on the server takes part after the
 * install, so what is pinned here is the install and the page; the board's
 * behaviour is pinned in resources/js/plinko/plinko.test.ts.
 */
function plinkoUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'plinkotester'.fake()->unique()->randomNumber(5)],
    ]);
}

function plinkoRecipe(): Recipe
{
    $catalog = app(RecipeCatalog::class);

    return $catalog->sync($catalog->find('chat-plinko'));
}

function plinkoDocument(): array
{
    return OverlayMarkdown::parse(file_get_contents(RecipeInstaller::directoryFor('chat-plinko').DIRECTORY_SEPARATOR.'plinko.md'));
}

it('is listed third on the products shelf, in slug order, with its hero', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.2.slug', 'chat-plinko')
            ->where('products.2.category', 'product')
            ->where('products.2.hero', '/products/chat-plinko-hero.jpg')
            ->where('products.2.installs', ['Overlay'])
            ->where('products.2.service', null)
        );

    foreach (['products/chat-plinko-hero.jpg', 'products/chat-plinko-hero.svg', 'products/chat-plinko/play-board.svg'] as $file) {
        expect(is_file(public_path($file)))->toBeTrue("missing {$file}")
            ->and(file_get_contents(public_path($file)))->not->toContain('c2pa');
    }
});

it('installs one overlay and nothing else to connect', function () {
    $manifest = app(RecipeCatalog::class)->find('chat-plinko');

    expect($manifest['installs']['overlays'] ?? [])->toHaveCount(1)
        ->and($manifest['installs']['integrations'] ?? [])->toBe([])
        ->and($manifest['installs']['lists'] ?? [])->toBe([])
        ->and($manifest['installs']['bot_commands'] ?? [])->toBe([])
        ->and($manifest['requires_bot'] ?? false)->toBeFalse();
});

it('installs the board overlay with its five color controls and no other rows', function () {
    $user = plinkoUser();

    $instance = app(RecipeInstaller::class)->install(plinkoRecipe(), $user, 'chat_plinko');

    $template = OverlayTemplate::find($instance->primitive_map['overlays']['plinko']);
    expect($template)->not->toBeNull()
        ->and($template->type)->toBe('static')
        ->and($template->html)->toContain('[[[plinko_board]]]')
        ->and($template->html)->toContain('![[[plinko_command]]]')
        ->and($template->css)->not->toContain('--plinko-command')
        ->and(OverlayControl::where('overlay_template_id', $template->id)->count())->toBe(5)
        ->and(OverlayControl::where('overlay_template_id', $template->id)->where('type', 'color')->count())->toBe(5)
        ->and(ExternalIntegration::where('user_id', $user->id)->count())->toBe(0)
        ->and(OptionSet::where('user_id', $user->id)->count())->toBe(0);
});

it('loads its fonts from Bunny, never Google', function () {
    $head = plinkoDocument()['head'];

    expect($head)->toContain('fonts.bunny.net')
        ->and($head)->not->toContain('googleapis');
});

it('styles every part of the board scoreboard the module draws', function () {
    // The module writes these class names; the overlay's CSS is the only
    // thing that places them. A renamed class would leave a list stacked in
    // the top-left corner of the stream.
    $css = plinkoDocument()['css'];

    foreach (['.ol-plinko-hud', '.ol-plinko-count', '.ol-plinko-next', '.ol-plinko-recent', '.ol-plinko-top'] as $class) {
        expect($css)->toContain($class);
    }
});

it('lists the board tag in the tag catalogue, rendered empty by the server', function () {
    $mapper = app(TemplateDataMapperService::class);

    expect($mapper->mapTwitchDataForTemplates([], 'Plinko'))->toHaveKey('plinko_board', '');
});

it('has no wires beyond the overlay and its OBS link', function () {
    $user = plinkoUser();
    $instance = app(RecipeInstaller::class)->install(plinkoRecipe(), $user, 'chat_plinko');

    $states = WiringFacts::productSubject($instance)['states'];
    expect($states['product.overlay'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.integration'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.list'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.command'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.bot_on'])->toBe(WiringCatalog::NOT_APPLICABLE);
});

it('serves a public pitch page with the hero as its share image', function () {
    $response = $this->get(route('products.show', 'chat-plinko'))->assertOk();
    $manifest = json_decode(file_get_contents(base_path('resources/recipes/chat-plinko/manifest.json')), true);

    // The tagline is copy and gets rewritten; what matters is that the pitch renders it.
    $response->assertSee($manifest['pitch']['tagline'])
        ->assertSee('Get Chat Plinko')
        ->assertSee('/products/chat-plinko-hero.jpg')
        ->assertSee('/products/chat-plinko/play-board.svg');
});

// ---------------------------------------------------------------------------
// The rename from Twitch Chat Plinko (2026-11-28)
// ---------------------------------------------------------------------------

it('301s the old product URL to chat-plinko', function () {
    $this->get('/products/twitch-chat-plinko')
        ->assertStatus(301)
        ->assertRedirect(route('products.show', 'chat-plinko'));
});

it('301s the old help page and its markdown twin to chat-plinko', function () {
    $this->get('/help/twitch-chat-plinko')->assertStatus(301)->assertRedirect('/help/chat-plinko');
    $this->get('/help/twitch-chat-plinko.md')->assertStatus(301)->assertRedirect('/help/chat-plinko.md');
});

it('keeps an install made under the old slug installed once the recipe row is renamed', function () {
    $user = plinkoUser();
    app(RecipeInstaller::class)->install(plinkoRecipe(), $user, 'twitch_chat_plinko');
    // As every install looked before the rename: the recipe row under the old slug and name.
    DB::table('recipes')->where('slug', 'chat-plinko')->update(['slug' => 'twitch-chat-plinko', 'name' => 'Twitch Chat Plinko']);

    (require base_path('database/migrations/2026_11_28_120000_rename_twitch_chat_plinko_to_chat_plinko.php'))->up();

    expect(DB::table('recipes')->where('slug', 'chat-plinko')->value('name'))->toBe('Chat Plinko')
        ->and(DB::table('recipes')->where('slug', 'twitch-chat-plinko')->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('products.show', 'chat-plinko'))
        ->assertOk()
        ->assertSee('Chat Plinko is installed');
});
