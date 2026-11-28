<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/**
 * The 2026-11-20 palette move: an install of Plinko, Chat Tower or Follower
 * Bowling that is still exactly what we shipped gets the color controls its
 * designer edits. The fixtures are each product's stylesheet as it was live
 * before the move.
 */
function paletteMove(): Migration
{
    return require base_path('database/migrations/2026_11_20_120000_give_installed_fullscreen_products_their_palette_controls.php');
}

/** @return array<string, mixed> */
function paletteSpec(string $product): array
{
    return (new ReflectionClass(paletteMove()))->getConstant('PRODUCTS')[$product];
}

function paletteTemplate(string $product): OverlayTemplate
{
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'palette'.fake()->unique()->randomNumber(5)],
    ]);
    // The migration is frozen at the slugs products had on 2026-11-20. Plinko
    // has been chat-plinko since 2026-11-28, so install it under that and put
    // the recipe row back the way it was when the migration ran.
    $current = ['twitch-chat-plinko' => 'chat-plinko'][$product] ?? $product;
    if ($current !== $product) {
        // A second install in the same test finds the row already moved back.
        DB::table('recipes')->where('slug', $product)->update(['slug' => $current]);
    }
    $catalog = app(RecipeCatalog::class);
    $instance = app(RecipeInstaller::class)->install(
        $catalog->sync($catalog->find($current)),
        $user,
        RecipeInstance::instanceSlugFrom($product),
    );
    if ($current !== $product) {
        DB::table('recipes')->where('slug', $current)->update(['slug' => $product]);
    }

    return OverlayTemplate::findOrFail($instance->primitive_map['overlays'][paletteSpec($product)['overlay_ref']]);
}

/** Turn a fresh install back into one from before the move: old stylesheet, no color controls or tags. */
function paletteMakeOld(OverlayTemplate $template, string $product, string $cssSuffix = ''): OverlayTemplate
{
    $ref = paletteSpec($product)['overlay_ref'];
    $css = str_replace("\r\n", "\n", file_get_contents(base_path("tests/Feature/fixtures/palette-migration/{$ref}.css")));

    $template->controls()->where('type', 'color')->delete();
    $template->forceFill([
        'css' => $css.$cssSuffix,
        'template_tags' => array_values(array_filter($template->template_tags, fn (string $tag) => ! str_ends_with($tag, '_color'))),
        'updated_at' => now()->subDay(),
    ])->saveQuietly();

    return $template->fresh();
}

$products = [
    'Plinko' => ['twitch-chat-plinko', 5],
    'Chat Tower' => ['chat-tower', 10],
    'Follower Bowling' => ['follower-bowling', 6],
];

it('gives an unedited install the palette stylesheet and its color controls', function (string $product, int $colors) {
    $template = paletteMakeOld(paletteTemplate($product), $product);
    $others = $template->controls()->count();
    $spec = paletteSpec($product);

    paletteMove()->up();

    $template = $template->fresh();
    expect($template->css)->toBe($spec['css'])
        ->and(array_diff($spec['tags'], $template->template_tags))->toBe([])
        ->and($template->controls()->count())->toBe($others + $colors);

    $rows = $template->controls()->where('type', 'color')->get()->keyBy('key');
    expect($rows)->toHaveCount($colors);
    foreach ($spec['controls'] as $control) {
        expect($rows[$control['key']]->value)->toBe($control['value'])
            ->and($rows[$control['key']]->label)->toBe($control['label'])
            ->and($rows[$control['key']]->user_id)->toBe($template->owner_id)
            ->and($rows[$control['key']]->source_managed)->toBeFalse();
    }
})->with($products);

it('leaves an install whose stylesheet was edited alone', function (string $product) {
    $template = paletteMakeOld(paletteTemplate($product), $product, "\n/* my own colors */\n");
    $css = $template->css;

    paletteMove()->up();

    expect($template->fresh()->css)->toBe($css)
        ->and($template->controls()->where('type', 'color')->count())->toBe(0);
})->with($products);

it('leaves an install whose markup was edited alone', function (string $product) {
    $template = paletteMakeOld(paletteTemplate($product), $product);
    $template->forceFill(['html' => $template->html.'<p>mine</p>'])->saveQuietly();
    $css = $template->css;

    paletteMove()->up();

    expect($template->fresh()->css)->toBe($css)
        ->and($template->controls()->where('type', 'color')->count())->toBe(0);
})->with($products);

it('keeps a color the streamer already set on a control with the same key', function () {
    $template = paletteMakeOld(paletteTemplate('twitch-chat-plinko'), 'twitch-chat-plinko');
    OverlayControl::createForTemplate($template, $template->owner, [
        'key' => 'highlight_color', 'label' => 'Mine', 'type' => 'color', 'value' => '#00ff00',
    ]);

    paletteMove()->up();

    $rows = $template->controls()->where('type', 'color')->get()->keyBy('key');
    expect($rows)->toHaveCount(5)
        ->and($rows['highlight_color']->value)->toBe('#00ff00')
        ->and($rows['highlight_color']->label)->toBe('Mine');
});

it('leaves a fresh install untouched and is safe to run twice', function (string $product, int $colors) {
    $fresh = paletteTemplate($product);
    $fresh->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    $stamp = $fresh->fresh()->updated_at;
    $old = paletteMakeOld(paletteTemplate($product), $product);

    paletteMove()->up();
    paletteMove()->up();

    expect($fresh->fresh()->updated_at->equalTo($stamp))->toBeTrue()
        ->and($fresh->controls()->where('type', 'color')->count())->toBe($colors)
        ->and($old->controls()->where('type', 'color')->count())->toBe($colors);
})->with($products);
