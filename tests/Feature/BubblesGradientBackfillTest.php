<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * The 2026-11-15 backfill: Chat Emote Bubbles installs from before the
 * two-color gradient get its two controls and its CSS, unless the streamer
 * has rewritten the parts of the CSS it patches.
 */
function gradientBackfill(): Migration
{
    return require base_path('database/migrations/2026_11_15_130000_add_gradient_to_installed_chat_emote_bubbles.php');
}

function gradientUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'gradient'.fake()->unique()->randomNumber(5)],
    ]);
}

function gradientInstall(User $user): OverlayTemplate
{
    $catalog = app(RecipeCatalog::class);
    $recipe = $catalog->sync($catalog->find('chat-emote-bubbles'));
    $instance = app(RecipeInstaller::class)->install($recipe, $user, RecipeInstance::instanceSlugFrom('chat-emote-bubbles'));

    return OverlayTemplate::findOrFail($instance->primitive_map['overlays']['bubbles']);
}

/**
 * Turn a fresh install back into one from before the gradient: the migration's
 * own patches run backwards, the two rows deleted, the tags taken out of the
 * allowlist, and the sort order closed up again.
 */
function makePreGradient(OverlayTemplate $template): void
{
    $migration = new ReflectionClass(gradientBackfill());
    $css = $template->css;

    foreach (array_reverse($migration->getConstant('PATCHES')) as $patch) {
        expect(substr_count($css, $patch['to']))->toBe(1);
        $css = str_replace($patch['to'], $patch['from'], $css);
    }
    $looks = $migration->getConstant('OPTIONAL_PATCHES')[1];
    $css = str_replace($looks['to'], $looks['from'], $css);

    $template->controls()->whereIn('key', ['bubble_color_2', 'gradient_angle'])->delete();
    $template->controls()->where('sort_order', '>', 2)->decrement('sort_order', 2);

    $template->update([
        'css' => $css,
        'template_tags' => array_values(array_diff($template->template_tags, ['c:bubble_color_2', 'c:gradient_angle'])),
    ]);
}

/** The controls as a fresh install makes them, minus row identity. */
function gradientRows(OverlayTemplate $template): array
{
    return $template->controls()->orderBy('sort_order')->get()
        ->map(fn (OverlayControl $c) => [$c->key, $c->label, $c->type, $c->description, $c->config, $c->sort_order, $c->source, $c->source_managed])
        ->all();
}

it('gives an install from before the gradient exactly what a fresh install has now', function () {
    $user = gradientUser();
    $fresh = gradientInstall($user);
    $expectedCss = $fresh->css;
    $expectedRows = gradientRows($fresh);

    makePreGradient($fresh);
    expect($fresh->fresh()->css)->not->toContain('--tint2')
        ->and($fresh->controls()->count())->toBe(10);

    gradientBackfill()->up();

    $template = $fresh->fresh();
    expect($template->css)->toBe($expectedCss)
        ->and(gradientRows($template))->toBe($expectedRows)
        ->and($template->template_tags)->toContain('c:bubble_color_2')
        ->and($template->template_tags)->toContain('c:gradient_angle');
});

it('starts the second color on the first and the angle at 0, so nothing on stream changes', function () {
    $user = gradientUser();
    $template = gradientInstall($user);
    makePreGradient($template);
    $template->controls()->where('key', 'bubble_color')->update(['value' => '#ff7eb6']);

    gradientBackfill()->up();

    expect($template->controls()->where('key', 'bubble_color_2')->value('value'))->toBe('#ff7eb6')
        ->and($template->controls()->where('key', 'gradient_angle')->value('value'))->toBe('0');
});

it('shows the active preset again on an install from before the gradient', function () {
    $user = gradientUser();
    $template = gradientInstall($user);
    makePreGradient($template);

    $this->actingAs($user)->get('/products/chat-emote-bubbles/install')
        ->assertInertia(fn (Assert $page) => $page->where('product.designer.presets.0.active', false));

    gradientBackfill()->up();

    $this->actingAs($user)->get('/products/chat-emote-bubbles/install')
        ->assertInertia(fn (Assert $page) => $page->where('product.designer.presets.0.active', true));
});

it('removes the first-day shine arc, which would fight the gradient ring for ::after', function () {
    $user = gradientUser();
    $template = gradientInstall($user);
    $expectedCss = $template->css;
    makePreGradient($template);

    $shine = (new ReflectionClass(gradientBackfill()))->getConstant('OPTIONAL_PATCHES')[0]['from'];
    $template->update(['css' => str_replace(".look-snow .skin::before {\n", $shine.".look-snow .skin::before {\n", $template->css)]);

    gradientBackfill()->up();

    expect($template->fresh()->css)->toBe($expectedCss);
});

it('leaves an install alone, CSS and controls, when the streamer rewrote a rule it patches', function () {
    $user = gradientUser();
    $template = gradientInstall($user);
    makePreGradient($template);

    $edited = str_replace('.look-heart .skin::before { background: color-mix(in srgb, var(--tint) 30%, transparent); }', '.look-heart .skin::before { background: hotpink; }', $template->fresh()->css);
    $template->update(['css' => $edited]);

    gradientBackfill()->up();

    expect($template->fresh()->css)->toBe($edited)
        ->and($template->controls()->count())->toBe(10)
        ->and($template->fresh()->template_tags)->not->toContain('c:gradient_angle');
});

it('keeps an edit the patches do not touch, and does not move any existing control', function () {
    $user = gradientUser();
    $template = gradientInstall($user);
    makePreGradient($template);

    $template->update(['css' => $template->fresh()->css."\n.bubble { filter: saturate(1.4); }\n"]);
    $before = $template->controls()->get(['key', 'value', 'updated_at'])->keyBy('key')->map(fn ($c) => [$c->value, (string) $c->updated_at])->all();

    $this->travel(5)->minutes();
    gradientBackfill()->up();

    $after = $template->controls()->whereNotIn('key', ['bubble_color_2', 'gradient_angle'])->get(['key', 'value', 'updated_at'])
        ->keyBy('key')->map(fn ($c) => [$c->value, (string) $c->updated_at])->all();

    ksort($before);
    ksort($after);

    expect($template->fresh()->css)->toContain('.bubble { filter: saturate(1.4); }')
        ->and($template->fresh()->css)->toContain('--fill')
        ->and($after)->toBe($before);
});

it('does nothing on a second run, nor on an install that already has the gradient', function () {
    $user = gradientUser();
    $current = gradientInstall($user);
    $currentCss = $current->css;

    $old = gradientInstall(gradientUser());
    makePreGradient($old);

    gradientBackfill()->up();
    $patchedCss = $old->fresh()->css;
    gradientBackfill()->up();

    expect($current->fresh()->css)->toBe($currentCss)
        ->and($current->controls()->count())->toBe(12)
        ->and($old->fresh()->css)->toBe($patchedCss)
        ->and($old->controls()->count())->toBe(12);
});
