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
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * Twitch Chat Plinko is the sixth product and the first that ships its own
 * script: the overlay is one `[[[plinko_board]]]` tag, which the renderer
 * swaps for a canvas the lazy plinko chunk draws, fed by every `!drop` it
 * reads straight from Twitch chat. Nothing on the server takes part after the
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

    return $catalog->sync($catalog->find('twitch-chat-plinko'));
}

function plinkoDocument(): array
{
    return OverlayMarkdown::parse(file_get_contents(RecipeInstaller::directoryFor('twitch-chat-plinko').DIRECTORY_SEPARATOR.'plinko.md'));
}

it('is listed last on the products shelf, with its hero', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.5.slug', 'twitch-chat-plinko')
            ->where('products.5.category', 'product')
            ->where('products.5.hero', '/products/twitch-chat-plinko-hero.jpg')
            ->where('products.5.installs', ['Overlay'])
            ->where('products.5.service', null)
        );

    foreach (['products/twitch-chat-plinko-hero.jpg', 'products/twitch-chat-plinko-hero.svg', 'products/twitch-chat-plinko/play-board.svg'] as $file) {
        expect(is_file(public_path($file)))->toBeTrue("missing {$file}")
            ->and(file_get_contents(public_path($file)))->not->toContain('c2pa');
    }
});

it('installs one overlay and nothing else to connect', function () {
    $manifest = app(RecipeCatalog::class)->find('twitch-chat-plinko');

    expect($manifest['installs']['overlays'] ?? [])->toHaveCount(1)
        ->and($manifest['installs']['integrations'] ?? [])->toBe([])
        ->and($manifest['installs']['lists'] ?? [])->toBe([])
        ->and($manifest['installs']['bot_commands'] ?? [])->toBe([])
        ->and($manifest['requires_bot'] ?? false)->toBeFalse();
});

it('installs the board overlay with no controls and no other rows', function () {
    $user = plinkoUser();

    $instance = app(RecipeInstaller::class)->install(plinkoRecipe(), $user, 'twitch_chat_plinko');

    $template = OverlayTemplate::find($instance->primitive_map['overlays']['plinko']);
    expect($template)->not->toBeNull()
        ->and($template->type)->toBe('static')
        ->and($template->html)->toContain('[[[plinko_board]]]')
        ->and($template->html)->toContain('![[[plinko_command]]]')
        ->and($template->css)->not->toContain('--plinko-command')
        ->and(OverlayControl::where('overlay_template_id', $template->id)->count())->toBe(0)
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
    $instance = app(RecipeInstaller::class)->install(plinkoRecipe(), $user, 'twitch_chat_plinko');

    $states = WiringFacts::productSubject($instance)['states'];
    expect($states['product.overlay'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.integration'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.list'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.command'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.bot_on'])->toBe(WiringCatalog::NOT_APPLICABLE);
});

it('serves a public pitch page with the hero as its share image', function () {
    $response = $this->get(route('products.show', 'twitch-chat-plinko'))->assertOk();

    $response->assertSee('Your chat types !drop. Their name bounces down the board.')
        ->assertSee('Get Twitch Chat Plinko')
        ->assertSee('/products/twitch-chat-plinko-hero.jpg')
        ->assertSee('/products/twitch-chat-plinko/play-board.svg');
});
