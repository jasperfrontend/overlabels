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
use App\Support\ProductDesigner;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * Snow Overlay for OBS is the seventh product and the first that reads
 * nothing at all: the overlay is one `[[[snow_scene]]]` tag, which the
 * renderer swaps for a three.js canvas the lazy snow chunk draws, set by CSS
 * custom properties bound to the controls. Nothing on the server takes part
 * after the install, so what is pinned here is the install, the designer and
 * the page; the snowfall itself is pinned in resources/js/snow/snow.test.ts.
 */
function snowUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'snowtester'.fake()->unique()->randomNumber(5)],
    ]);
}

function snowRecipe(): Recipe
{
    $catalog = app(RecipeCatalog::class);

    return $catalog->sync($catalog->find('snow-overlay'));
}

function snowDocument(): array
{
    return OverlayMarkdown::parse(file_get_contents(RecipeInstaller::directoryFor('snow-overlay').DIRECTORY_SEPARATOR.'snow.md'));
}

it('installs one overlay and nothing else to connect', function () {
    $manifest = app(RecipeCatalog::class)->find('snow-overlay');

    expect($manifest['installs']['overlays'] ?? [])->toHaveCount(1)
        ->and($manifest['installs']['integrations'] ?? [])->toBe([])
        ->and($manifest['installs']['lists'] ?? [])->toBe([])
        ->and($manifest['installs']['bot_commands'] ?? [])->toBe([])
        ->and($manifest['requires_bot'] ?? false)->toBeFalse();
});

it('installs the snow overlay with its nine controls and no other rows', function () {
    $user = snowUser();

    $instance = app(RecipeInstaller::class)->install(snowRecipe(), $user, 'snow_overlay');

    $template = OverlayTemplate::find($instance->primitive_map['overlays']['snow']);
    expect($template)->not->toBeNull()
        ->and($template->type)->toBe('static')
        ->and($template->html)->toContain('[[[snow_scene]]]')
        ->and(OverlayControl::where('overlay_template_id', $template->id)->count())->toBe(9)
        ->and(ExternalIntegration::where('user_id', $user->id)->count())->toBe(0)
        ->and(OptionSet::where('user_id', $user->id)->count())->toBe(0);

    $shape = OverlayControl::where('overlay_template_id', $template->id)->where('key', 'shape')->first();
    expect(collect($shape->config['choices'])->pluck('value')->all())->toBe(['round', 'square', 'snowflake']);
});

it('binds every snow setting the module reads to a control', function () {
    // snowPhysics.ts readSnowSettings() reads these off .ol-snow. A property
    // the CSS stops setting would silently fall back to its default.
    $css = snowDocument()['css'];

    foreach ([
        '--snow-shape: [[[c:shape]]]',
        '--snow-count: [[[c:flake_count]]]',
        '--snow-speed: [[[c:speed]]]',
        '--snow-size: [[[c:flake_size]]]',
        '--snow-color: [[[c:flake_color]]]',
        '--snow-color-2: [[[c:flake_color_2]]]',
        '--snow-angle: [[[c:gradient_angle]]]',
    ] as $binding) {
        expect($css)->toContain($binding);
    }
});

it('gives the gradient angle the same angle knob as Chat Emote Bubbles', function () {
    $user = snowUser();
    $instance = app(RecipeInstaller::class)->install(snowRecipe(), $user, 'snow_overlay');

    $angle = OverlayControl::where('overlay_template_id', $instance->primitive_map['overlays']['snow'])->where('key', 'gradient_angle')->first();
    expect($angle->config['angle_colors'])->toBe(['flake_color', 'flake_color_2']);
});

it('declares a designer with presets for every look', function () {
    $manifest = app(RecipeCatalog::class)->find('snow-overlay');

    expect(ProductDesigner::declared($manifest))->not->toBeNull()
        ->and(collect($manifest['designer']['presets'])->pluck('values.shape')->unique()->sort()->values()->all())
        ->toBe(['round', 'snowflake', 'square']);
});

it('lists the snow tag in the tag catalogue, rendered empty by the server', function () {
    $mapper = app(TemplateDataMapperService::class);

    expect($mapper->mapTwitchDataForTemplates([], 'Snow'))->toHaveKey('snow_scene', '');
});

it('has no wires beyond the overlay and its OBS link', function () {
    $user = snowUser();
    $instance = app(RecipeInstaller::class)->install(snowRecipe(), $user, 'snow_overlay');

    $states = WiringFacts::productSubject($instance)['states'];
    expect($states['product.overlay'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.integration'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.list'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.command'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.bot_on'])->toBe(WiringCatalog::NOT_APPLICABLE);
});

it('ships its hero without a c2pa manifest', function () {
    $file = public_path('products/snow-overlay-hero.jpg');

    expect(app(RecipeCatalog::class)->find('snow-overlay')['hero'])->toBe('/products/snow-overlay-hero.jpg')
        ->and(is_file($file))->toBeTrue()
        ->and(file_get_contents($file))->not->toContain('c2pa');
});

it('serves a public pitch page with the hero as its share image', function () {
    $manifest = json_decode(file_get_contents(base_path('resources/recipes/snow-overlay/manifest.json')), true);

    $this->get(route('products.show', 'snow-overlay'))
        ->assertOk()
        ->assertSee($manifest['pitch']['tagline'])
        ->assertSee('Get Snow Overlay for OBS')
        ->assertSee('/products/snow-overlay-hero.jpg');
});

it('demos itself with the recording, which loads nothing until it starts', function () {
    $file = public_path('products/snow-overlay/demo.mp4');
    expect(is_file($file))->toBeTrue();

    $this->get(route('products.show', 'snow-overlay'))
        ->assertOk()
        ->assertSee('/products/snow-overlay/demo.mp4')
        ->assertSee('preload="none"', false)
        ->assertSee('data-video-demo-autostart="1"', false);

    $this->get('/')
        ->assertOk()
        ->assertSee('/products/snow-overlay/demo.mp4')
        ->assertSee('data-video-demo-autostart="0"', false);
});
