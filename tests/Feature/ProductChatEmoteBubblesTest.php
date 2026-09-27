<?php

use App\Events\ControlValueUpdated;
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
use App\Support\ProductDesigner;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
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
            ->where('products.1.hero', '/products/chat-emote-bubbles-hero.jpg')
            ->where('products.1.installs', ['Overlay'])
            ->where('products.1.service', null)
        );

    expect(is_file(public_path('products/chat-emote-bubbles-hero.jpg')))->toBeTrue()
        ->and(file_get_contents(public_path('products/chat-emote-bubbles-hero.jpg')))->not->toContain('c2pa');
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

it('declares a vocabulary on each text control, styles every value in it, and defaults to one of them', function () {
    // The three text controls are each a closed vocabulary the CSS keys on
    // (`look-`, `dir-`, `spawn-` classes). The row says which values exist,
    // so the Controls tab offers a select rather than a box to guess into,
    // and this holds the declaration against the CSS in both directions.
    $doc = bubblesDocument();
    $prefixes = ['look' => '.look-', 'direction' => '.dir-', 'spawn' => '.spawn-'];

    $text = collect($doc['controls'])->where('type', 'text')->keyBy('key');
    expect($text->keys()->all())->toEqualCanonicalizing(array_keys($prefixes));

    foreach ($text as $key => $control) {
        $choices = $control['config']['choices'] ?? [];
        $values = array_column($choices, 'value');

        expect($choices)->not->toBe([])
            ->and($values)->toContain($control['value']);

        foreach ($choices as $choice) {
            expect($choice['label'])->not->toBe('')
                ->and($choice['hint'])->not->toBe('')
                ->and($doc['css'])->toContain($prefixes[$key].$choice['value']);
        }

        preg_match_all('/'.preg_quote($prefixes[$key], '/').'([a-z]+)/', $doc['css'], $styled);
        expect(array_values(array_unique($styled[1])))->toEqualCanonicalizing($values);
    }
});

it('installs the vocabularies onto the rows', function () {
    $user = bubblesUser();
    $instance = app(RecipeInstaller::class)->install(bubblesRecipe(), $user, 'chat_emote_bubbles');
    $declared = collect(bubblesDocument()['controls'])->keyBy('key');

    $controls = OverlayControl::where('overlay_template_id', $instance->primitive_map['overlays']['bubbles'])->get()->keyBy('key');

    foreach (['look', 'direction', 'spawn'] as $key) {
        expect($controls[$key]->config['choices'])->toBe($declared[$key]['config']['choices']);
    }

    expect($controls['bubble_size']->config)->not->toHaveKey('choices');
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

it('declares a designer with three looks, every control in a group and no skin strip', function () {
    // The designer is the manifest's to declare, and this product's block is
    // the first written for a product other than Twitch Chat. Soap is the
    // install's own defaults, so a fresh install shows it active.
    $designer = ProductDesigner::declared(app(RecipeCatalog::class)->find('chat-emote-bubbles'));
    $defaults = collect(bubblesDocument()['controls'])->pluck('value', 'key')->all();

    expect($designer['overlay'])->toBe('bubbles')
        ->and($designer)->not->toHaveKey('skin_key')
        ->and(ProductDesigner::skins($designer))->toBe([])
        ->and(array_column(ProductDesigner::presets($designer), 'key'))->toBe(['soap', 'winter', 'valentine'])
        ->and(ProductDesigner::preset($designer, 'soap')['values'])->toEqual($defaults)
        ->and(ProductDesigner::preset($designer, 'winter')['values']['look'])->toBe('snow')
        ->and(ProductDesigner::preset($designer, 'winter')['values']['direction'])->toBe('down')
        ->and(ProductDesigner::preset($designer, 'valentine')['values']['look'])->toBe('heart')
        ->and(ProductDesigner::preset($designer, 'valentine')['values']['spawn'])->toBe('cannon')
        ->and(collect(ProductDesigner::keys($designer))->sort()->values()->all())->toBe(collect(array_keys($defaults))->sort()->values()->all())
        ->and(ProductDesigner::extras($designer))->toBe(['sample_chat', 'chat_filters'])
        ->and(ProductDesigner::stage($designer))->toBe([['w' => 1920, 'h' => 1080]]);
});

it('opens the designer for an install, with the bubbles controls and no chat window', function () {
    $user = bubblesUser();
    $instance = app(RecipeInstaller::class)->install(bubblesRecipe(), $user, 'chat_emote_bubbles');

    $this->actingAs($user)->get('/products/chat-emote-bubbles/design')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/design')
            ->where('product.slug', 'chat-emote-bubbles')
            ->where('overlay.id', $instance->primitive_map['overlays']['bubbles'])
            ->has('controls', 10)
            ->where('controls.look.value', 'bubble')
            ->where('controls.spawn_x.type', 'number')
            ->has('presets', 3)
            ->where('presets.0.key', 'soap')
            ->where('skins', [])
            ->where('skin_key', null)
            ->has('groups', 4)
            ->where('groups.0.title', 'Look')
            ->where('extras', ['sample_chat', 'chat_filters'])
            ->where('stage', [['w' => 1920, 'h' => 1080]])
            // The emote buffer is a fixed bound, not a foreach cap, so the
            // window slider has no business here; the filters do apply,
            // because a hidden chatter's emotes do not bubble.
            ->missing('chat_window')
            ->missing('chat_window_max')
            ->has('chat_filters')
            ->where('max_hidden_logins', User::MAX_HIDDEN_LOGINS)
        );

    $url = $this->actingAs($user)->get('/products/chat-emote-bubbles/design')->viewData('page')['props']['preview_url'];
    expect($url)->toStartWith('/overlay/'.OverlayTemplate::find($instance->primitive_map['overlays']['bubbles'])->slug.'?chat=sample#');

    // Not for an account with no install.
    $this->actingAs(bubblesUser())->get('/products/chat-emote-bubbles/design')->assertNotFound();
});

it('shows the designer card on the product page once installed, and applies a look from it', function () {
    Event::fake([ControlValueUpdated::class]);
    $user = bubblesUser();

    $this->actingAs($user)->get('/products/chat-emote-bubbles')
        ->assertInertia(fn (Assert $page) => $page
            ->has('product.designer.presets', 3)
            ->where('product.designer.presets.0.active', false)
            ->where('product.designer.groups', ['Look', 'Motion', 'Spawn', 'Crowd'])
        );

    $instance = app(RecipeInstaller::class)->install(bubblesRecipe(), $user, 'chat_emote_bubbles');
    $templateId = $instance->primitive_map['overlays']['bubbles'];

    $this->actingAs($user)->get('/products/chat-emote-bubbles')
        ->assertInertia(fn (Assert $page) => $page->where('product.designer.presets.0.active', true));

    $this->actingAs($user)->postJson('/products/chat-emote-bubbles/presets/winter')
        ->assertOk()
        ->assertJsonPath('values.look', 'snow')
        ->assertJsonPath('values.bubble_color', '#bfe3ff');

    $values = OverlayControl::where('overlay_template_id', $templateId)->pluck('value', 'key')->all();
    expect($values['look'])->toBe('snow')
        ->and($values['direction'])->toBe('down')
        ->and($values['spawn'])->toBe('random')
        ->and($values['max_bubbles'])->toBe('60');

    Event::assertDispatchedTimes(ControlValueUpdated::class, 10);

    $this->actingAs($user)->get('/products/chat-emote-bubbles')
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.designer.presets.0.active', false)
            ->where('product.designer.presets.1.active', true)
        );

    $this->actingAs($user)->post('/products/chat-emote-bubbles/presets/clean')->assertNotFound();
});
