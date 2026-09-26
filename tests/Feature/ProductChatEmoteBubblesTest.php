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
use App\Support\OverlayMarkdown;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * Chat Emote Bubbles is the fifth product and the second that installs nothing
 * but an overlay. It is the Twitch Chat product's sibling: the same direct
 * Twitch connection, read through the `emotes` loop instead of `chat`, and its
 * whole behaviour is ten template-scoped controls landing in CSS.
 */
function bubblesUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'bubbletester'.fake()->unique()->randomNumber(5)],
    ], $attrs));
}

function bubblesRecipe(): Recipe
{
    $catalog = app(RecipeCatalog::class);

    return $catalog->sync($catalog->find('chat-emote-bubbles'));
}

function bubblesDocument(): array
{
    return OverlayMarkdown::parse(file_get_contents(RecipeInstaller::directoryFor('chat-emote-bubbles').DIRECTORY_SEPARATOR.'bubbles.md'));
}

it('is listed on the products shelf between checkin and the tower', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.1.slug', 'chat-emote-bubbles')
            ->where('products.1.category', 'product')
            ->where('products.1.hero', '/products/chat-emote-bubbles-hero.svg')
            ->where('products.1.installs', ['Overlay'])
            ->where('products.1.service', null)
        );

    expect(is_file(public_path('products/chat-emote-bubbles-hero.svg')))->toBeTrue()
        ->and(file_get_contents(public_path('products/chat-emote-bubbles-hero.svg')))->not->toContain('c2pa');
});

it('shows one overlay and nothing else to connect', function () {
    $this->get('/products/chat-emote-bubbles')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.overlays.0.name', 'Chat Emote Bubbles')
            ->where('product.integrations', [])
            ->where('product.lists', [])
            ->where('product.commands', [])
            ->where('product.requires_bot', false)
        );
});

it('installs the bubbles overlay with its ten controls and no other rows', function () {
    $user = bubblesUser();

    $instance = app(RecipeInstaller::class)->install(bubblesRecipe(), $user, 'chat_emote_bubbles');

    $template = OverlayTemplate::find($instance->primitive_map['overlays']['bubbles']);
    expect($template)->not->toBeNull()
        ->and($template->type)->toBe('static')
        ->and($template->html)->toContain('[[[foreach:emotes as e]]]')
        ->and($template->html)->toContain('data-key="[[[e.id]]]"')
        ->and($template->html)->toContain('[[[e.html]]]')
        ->and($template->css)->toContain('--size: [[[c:bubble_size]]]px;');

    $controls = OverlayControl::where('overlay_template_id', $template->id)->orderBy('sort_order')->get();
    expect($controls->pluck('key')->all())->toBe([
        'look', 'bubble_size', 'bubble_color', 'speed', 'direction',
        'max_bubbles', 'pop_after', 'spawn', 'spawn_x', 'spawn_y',
    ])
        ->and($controls->where('type', 'expression')->count())->toBe(0)
        ->and($controls->where('source_managed', true)->count())->toBe(0)
        ->and($controls->firstWhere('key', 'look')->value)->toBe('bubble')
        ->and($controls->firstWhere('key', 'direction')->value)->toBe('up')
        ->and($controls->firstWhere('key', 'spawn')->value)->toBe('outside')
        ->and($controls->firstWhere('key', 'pop_after')->value)->toBe('0')
        ->and($controls->firstWhere('key', 'max_bubbles')->value)->toBe('40')
        ->and($controls->firstWhere('key', 'max_bubbles')->config['max'])->toBe(100);

    expect(ExternalIntegration::where('user_id', $user->id)->count())->toBe(0)
        ->and(OptionSet::where('user_id', $user->id)->count())->toBe(0)
        ->and(OverlayControl::where('user_id', $user->id)->whereNull('overlay_template_id')->count())->toBe(0);
});

it('reads every control it declares, and every control it reads is declared', function () {
    $doc = bubblesDocument();
    $declared = collect($doc['controls'])->pluck('key')->sort()->values()->all();

    preg_match_all('/\[\[\[(?:if:)?c:([a-z][a-z0-9_]*)(?=[\]| ])/', $doc['html'].$doc['css'], $m);
    $read = collect($m[1])->unique()->sort()->values()->all();

    expect($read)->toBe($declared)
        ->and(count($declared))->toBe(10);
});

it('caps the bubbles on screen through a selector, which is the one tag in the CSS that cannot be a custom property', function () {
    // `:nth-last-child(-n + N)` shows the newest N children. A tag inside a
    // selector cannot be rewritten to var() by the compiled-bindings fast
    // path, and the `?? 40` is what sends the stylesheet to the slow path
    // instead of letting the fast path emit an invalid selector silently.
    $css = bubblesDocument()['css'];

    expect($css)->toContain('.bubble:nth-last-child(-n + [[[c:max_bubbles ?? 40]]]) { display: block; }')
        ->and($css)->not->toContain('[[[if:')
        ->and($css)->not->toContain('[[[foreach:');
});

it('only carries the popping class when pop_after is above zero', function () {
    $html = bubblesDocument()['html'];

    expect($html)->toContain('[[[if:c:pop_after > 0]]] popping[[[endif]]]')
        ->and($html)->toContain('look-[[[c:look]]]')
        ->and($html)->toContain('dir-[[[c:direction]]]')
        ->and($html)->toContain('spawn-[[[c:spawn]]]');
});

it('has no wires beyond the overlay and its OBS link', function () {
    $user = bubblesUser();
    $instance = app(RecipeInstaller::class)->install(bubblesRecipe(), $user, 'chat_emote_bubbles');

    $states = WiringFacts::productSubject($instance)['states'];
    expect($states['product.overlay'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.integration'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.list'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.command'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.bot_on'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.bot_modded'])->toBe(WiringCatalog::NOT_APPLICABLE);
});

it('uninstalls the overlay and its controls together', function () {
    $user = bubblesUser();
    $instance = app(RecipeInstaller::class)->install(bubblesRecipe(), $user, 'chat_emote_bubbles');
    $templateId = $instance->primitive_map['overlays']['bubbles'];

    app(RecipeInstaller::class)->uninstall($instance);

    expect(OverlayTemplate::find($templateId))->toBeNull()
        ->and(OverlayControl::where('overlay_template_id', $templateId)->count())->toBe(0)
        ->and(RecipeInstance::where('user_id', $user->id)->count())->toBe(0);
});
