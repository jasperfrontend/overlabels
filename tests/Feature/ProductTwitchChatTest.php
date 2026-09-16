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
 * Twitch Chat is the fourth product and the first that installs nothing but
 * an overlay: no integration, no list, no bot. The overlay reads chat from
 * Twitch directly, and its whole look is twelve template-scoped controls
 * that land in CSS custom properties, so a designer can drive it later by
 * writing controls alone.
 */
function twitchChatUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'chattester'.fake()->unique()->randomNumber(5)],
    ], $attrs));
}

function twitchChatRecipe(): Recipe
{
    $catalog = app(RecipeCatalog::class);

    return $catalog->sync($catalog->find('twitch_chat'));
}

function twitchChatDocument(): array
{
    return OverlayMarkdown::parse(file_get_contents(RecipeInstaller::directoryFor('twitch_chat').DIRECTORY_SEPARATOR.'chat.md'));
}

it('is listed on the products shelf with its hero image, after bowling', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.3.slug', 'twitch_chat')
            ->where('products.3.category', 'product')
            ->where('products.3.hero', '/products/twitch-chat-hero.svg')
            ->where('products.3.installs', ['Overlay'])
            ->where('products.3.service', null)
        );

    expect(is_file(public_path('products/twitch-chat-hero.svg')))->toBeTrue()
        ->and(file_get_contents(public_path('products/twitch-chat-hero.svg')))->not->toContain('c2pa');
});

it('shows one overlay and nothing else to connect', function () {
    $this->get('/products/twitch_chat')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.overlays.0.name', 'Twitch Chat')
            ->where('product.integrations', [])
            ->where('product.lists', [])
            ->where('product.commands', [])
            ->where('product.requires_bot', false)
        );
});

it('installs the chat overlay with its twelve controls and no other rows', function () {
    $user = twitchChatUser();

    $instance = app(RecipeInstaller::class)->install(twitchChatRecipe(), $user, 'twitch_chat');

    $template = OverlayTemplate::find($instance->primitive_map['overlays']['chat']);
    expect($template)->not->toBeNull()
        ->and($template->type)->toBe('static')
        ->and($template->html)->toContain('[[[foreach:chat as msg]]]')
        ->and($template->html)->toContain('data-key="[[[msg.id]]]"')
        ->and($template->html)->toContain('[[[msg.badge_images]]]')
        ->and($template->css)->toContain('--font: [[[c:font]]];');

    $controls = OverlayControl::where('overlay_template_id', $template->id)->orderBy('sort_order')->get();
    expect($controls->pluck('key')->all())->toBe([
        'layout', 'font', 'font_size', 'twitch_colors', 'name_color', 'text_color',
        'accent', 'background', 'background_color', 'lifetime', 'show_badges', 'emote_size',
    ])
        ->and($controls->where('type', 'expression')->count())->toBe(0)
        ->and($controls->where('source_managed', true)->count())->toBe(0)
        ->and($controls->firstWhere('key', 'layout')->value)->toBe('bottom')
        ->and($controls->firstWhere('key', 'lifetime')->value)->toBe('0')
        ->and($controls->firstWhere('key', 'accent')->value)->toBe('#9146ff');

    expect(ExternalIntegration::where('user_id', $user->id)->count())->toBe(0)
        ->and(OptionSet::where('user_id', $user->id)->count())->toBe(0)
        ->and(OverlayControl::where('user_id', $user->id)->whereNull('overlay_template_id')->count())->toBe(0);
});

it('reads every control it declares, and every control it reads is declared', function () {
    $doc = twitchChatDocument();
    $declared = collect($doc['controls'])->pluck('key')->sort()->values()->all();

    preg_match_all('/\[\[\[(?:if:)?c:([a-z][a-z0-9_]*)(?=[\]| ])/', $doc['html'].$doc['css'], $m);
    $read = collect($m[1])->unique()->sort()->values()->all();

    expect($read)->toBe($declared)
        ->and(count($declared))->toBe(12);
});

it('keeps the CSS on the compiled-bindings fast path: no if or foreach blocks in it', function () {
    $css = twitchChatDocument()['css'];

    expect($css)->not->toContain('[[[if:')
        ->and($css)->not->toContain('[[[elseif:')
        ->and($css)->not->toContain('[[[foreach:');
});

it('only carries the fading class when the lifetime control is above zero', function () {
    $html = twitchChatDocument()['html'];

    expect($html)->toContain('[[[if:c:lifetime > 0]]] fading[[[endif]]]')
        ->and($html)->toContain('layout-[[[c:layout]]]')
        ->and($html)->toContain('bg-[[[c:background]]]');
});

it('has no wires beyond the overlay and its OBS link', function () {
    $user = twitchChatUser();
    $instance = app(RecipeInstaller::class)->install(twitchChatRecipe(), $user, 'twitch_chat');

    $states = WiringFacts::productSubject($instance)['states'];
    expect($states['product.overlay'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.integration'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.list'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.command'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.bot_on'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.bot_modded'])->toBe(WiringCatalog::NOT_APPLICABLE);
});

it('uninstalls the overlay and its controls together', function () {
    $user = twitchChatUser();
    $instance = app(RecipeInstaller::class)->install(twitchChatRecipe(), $user, 'twitch_chat');
    $templateId = $instance->primitive_map['overlays']['chat'];

    app(RecipeInstaller::class)->uninstall($instance);

    expect(OverlayTemplate::find($templateId))->toBeNull()
        ->and(OverlayControl::where('overlay_template_id', $templateId)->count())->toBe(0)
        ->and(RecipeInstance::where('user_id', $user->id)->count())->toBe(0);
});
