<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * The 2026-11-19 backfill: Chat Emote Bubbles installs from before the Shape
 * look get its markup, CSS and four controls, unless the streamer has
 * rewritten the parts it patches. shapesBackfill() and makePreShapes() live
 * in tests/Pest.php, because the older bubbles backfill tests need them too.
 */
function shapesUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'shapes'.fake()->unique()->randomNumber(5)],
    ]);
}

function shapesInstall(User $user): OverlayTemplate
{
    $catalog = app(RecipeCatalog::class);
    $recipe = $catalog->sync($catalog->find('chat-emote-bubbles'));
    $instance = app(RecipeInstaller::class)->install($recipe, $user, RecipeInstance::instanceSlugFrom('chat-emote-bubbles'));

    return OverlayTemplate::findOrFail($instance->primitive_map['overlays']['bubbles']);
}

/** The controls as a fresh install makes them, minus row identity. */
function shapesRows(OverlayTemplate $template): array
{
    return $template->controls()->orderBy('sort_order')->get()
        ->map(fn (OverlayControl $c) => [$c->key, $c->label, $c->type, $c->value, $c->description, $c->config, $c->sort_order, $c->source, $c->source_managed])
        ->all();
}

it('gives an install from before the shapes exactly what a fresh install has now', function () {
    $fresh = shapesInstall(shapesUser());
    $expectedHtml = $fresh->html;
    $expectedCss = $fresh->css;
    $expectedRows = shapesRows($fresh);
    $expectedTags = collect($fresh->template_tags)->sort()->values()->all();

    makePreShapes($fresh);
    expect($fresh->fresh()->html)->not->toContain('ol-shape')
        ->and($fresh->fresh()->css)->not->toContain('look-shape')
        ->and($fresh->controls()->count())->toBe(12);

    shapesBackfill()->up();

    $template = $fresh->fresh();
    expect($template->html)->toBe($expectedHtml)
        ->and($template->css)->toBe($expectedCss)
        ->and(shapesRows($template))->toBe($expectedRows)
        ->and(collect($template->template_tags)->sort()->values()->all())->toBe($expectedTags);
});

it('changes nothing on stream: every existing value and its _at stay as they were', function () {
    $template = shapesInstall(shapesUser());
    makePreShapes($template);
    $template->controls()->where('key', 'look')->update(['value' => 'heart']);
    $before = $template->controls()->get(['key', 'value', 'updated_at'])->keyBy('key')->map(fn ($c) => [$c->value, (string) $c->updated_at])->all();

    $this->travel(5)->minutes();
    shapesBackfill()->up();

    $after = $template->controls()->whereNotIn('key', ['shape', 'border_width', 'fill_strength', 'breathing_room'])->get(['key', 'value', 'updated_at'])
        ->keyBy('key')->map(fn ($c) => [$c->value, (string) $c->updated_at])->all();

    ksort($before);
    ksort($after);

    expect($after)->toBe($before)
        ->and($template->controls()->where('key', 'look')->value('value'))->toBe('heart');
});

it('shows the active preset again on an install from before the shapes', function () {
    $user = shapesUser();
    $template = shapesInstall($user);
    makePreShapes($template);

    $this->actingAs($user)->get('/products/chat-emote-bubbles/install')
        ->assertInertia(fn (Assert $page) => $page->where('product.designer.presets.0.active', false));

    shapesBackfill()->up();

    $this->actingAs($user)->get('/products/chat-emote-bubbles/install')
        ->assertInertia(fn (Assert $page) => $page->where('product.designer.presets.0.active', true));
});

it('leaves an install alone, markup, CSS and controls, when the streamer rewrote a part it patches', function (string $field, string $from, string $to) {
    $template = shapesInstall(shapesUser());
    makePreShapes($template);

    $edited = str_replace($from, $to, $template->fresh()->{$field});
    expect($edited)->not->toBe($template->fresh()->{$field});
    $template->update([$field => $edited]);
    $html = $template->fresh()->html;
    $css = $template->fresh()->css;

    shapesBackfill()->up();

    expect($template->fresh()->html)->toBe($html)
        ->and($template->fresh()->css)->toBe($css)
        ->and($template->controls()->count())->toBe(12)
        ->and($template->fresh()->template_tags)->not->toContain('c:shape');
})->with([
    'the heart emote rule' => ['css', 'transform: translate(-50%, -56%); }', 'transform: translate(-50%, -60%); }'],
    'the fill variable' => ['css', '--fill: linear-gradient(var(--flow), var(--tint), var(--tint2));', '--fill: var(--tint);'],
    'the root element' => ['html', 'look-[[[c:look]]] dir-[[[c:direction]]]', 'look-[[[c:look]]] my-class dir-[[[c:direction]]]'],
]);

it('keeps a look select or a description the streamer changed', function () {
    $template = shapesInstall(shapesUser());
    makePreShapes($template);

    $look = $template->controls()->where('key', 'look')->first();
    $look->config = ['choices' => [['value' => 'bubble', 'label' => 'Soap', 'hint' => 'Mine.']]];
    $look->saveQuietly();
    $template->controls()->where('key', 'bubble_size')->update(['description' => 'My own words.']);

    shapesBackfill()->up();

    expect($template->controls()->where('key', 'look')->first()->config['choices'])->toBe([['value' => 'bubble', 'label' => 'Soap', 'hint' => 'Mine.']])
        ->and($template->controls()->where('key', 'bubble_size')->value('description'))->toBe('My own words.')
        ->and($template->controls()->where('key', 'shape')->exists())->toBeTrue();
});

it('does nothing on a second run, nor on an install that already has the shapes', function () {
    $current = shapesInstall(shapesUser());
    $currentHtml = $current->html;
    $currentCss = $current->css;

    $old = shapesInstall(shapesUser());
    makePreShapes($old);

    shapesBackfill()->up();
    $patchedHtml = $old->fresh()->html;
    $patchedCss = $old->fresh()->css;
    shapesBackfill()->up();

    expect($current->fresh()->html)->toBe($currentHtml)
        ->and($current->fresh()->css)->toBe($currentCss)
        ->and($current->controls()->count())->toBe(16)
        ->and($old->fresh()->html)->toBe($patchedHtml)
        ->and($old->fresh()->css)->toBe($patchedCss)
        ->and($old->controls()->count())->toBe(16);
});
