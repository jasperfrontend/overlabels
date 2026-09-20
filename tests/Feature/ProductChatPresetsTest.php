<?php

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\BunnyFonts;
use App\Support\ChatPresets;
use App\Support\OverlayMarkdown;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * The Twitch Chat product's ten looks. A preset is a bundle of values for
 * the overlay's thirteen look controls, applied through one POST that
 * writes each control and broadcasts it. Which one is active is never
 * stored: it is read back off the controls.
 */
function presetUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'presettester'.fake()->unique()->randomNumber(5)],
    ]);
}

function presetRecipe(): Recipe
{
    $catalog = app(RecipeCatalog::class);

    return $catalog->sync($catalog->find(ChatPresets::PRODUCT));
}

function presetDocument(): array
{
    return OverlayMarkdown::parse(file_get_contents(RecipeInstaller::directoryFor(ChatPresets::PRODUCT).DIRECTORY_SEPARATOR.'chat.md'));
}

it('has ten presets, each writing exactly the thirteen controls the overlay declares', function () {
    $declared = collect(presetDocument()['controls'])->pluck('key')->sort()->values()->all();

    expect(count(ChatPresets::PRESETS))->toBe(10)
        ->and(collect(ChatPresets::KEYS)->sort()->values()->all())->toBe($declared);

    foreach (ChatPresets::PRESETS as $key => $preset) {
        expect(collect($preset['values'])->keys()->sort()->values()->all())->toBe($declared)
            ->and($preset['values']['skin'])->toBe($key)
            ->and($preset['label'])->not->toBe('')
            ->and($preset['blurb'])->not->toBe('');
    }
});

it('names only skins the overlay CSS defines, layouts and backgrounds it styles, and fonts Bunny serves', function () {
    $doc = presetDocument();
    preg_match_all('/\.skin-([a-z]+)/', $doc['css'], $m);
    $skins = array_unique($m[1]);

    foreach (ChatPresets::PRESETS as $key => $preset) {
        $v = $preset['values'];
        if ($v['skin'] !== 'clean') {
            expect($skins)->toContain($v['skin']);
        }
        expect($doc['css'])->toContain('.layout-'.$v['layout'].' ')
            ->and($doc['css'])->toContain('.bg-'.$v['background'].' ')
            // A preset writes its font straight through writeValue(), which
            // does not go past the value endpoint's allowlist - so a preset
            // naming a family Bunny does not serve is the one way to get an
            // unloadable font onto an overlay. Hence the check here.
            ->and(BunnyFonts::canonical($v['font']))->toBe($v['font']);
        expect($v['name_color'])->toMatch('/^#[0-9a-f]{6}$/')
            ->and($v['text_color'])->toMatch('/^#[0-9a-f]{6}$/')
            ->and($v['accent'])->toMatch('/^#[0-9a-f]{6}$/')
            ->and($v['background_color'])->toMatch('/^#[0-9a-f]{6}$/');
    }
});

it('publishes the presets on the product page with clean active right after an install', function () {
    $user = presetUser();

    $this->actingAs($user)->get('/products/twitch-chat-overlay')
        ->assertInertia(fn (Assert $page) => $page->has('product.presets', 10)->where('product.presets.0.active', false));

    app(RecipeInstaller::class)->install(presetRecipe(), $user, 'twitch_chat_overlay');

    $this->actingAs($user)->get('/products/twitch-chat-overlay')
        ->assertInertia(fn (Assert $page) => $page
            ->has('product.presets', 10)
            ->where('product.presets.0.key', 'clean')
            ->where('product.presets.0.active', true)
            ->where('product.presets.1.key', 'terminal')
            ->where('product.presets.1.active', false)
            ->where('product.presets.1.preview.font', 'JetBrains Mono')
        );
});

it('publishes no presets for another product', function () {
    $this->get('/products/chat-tower')
        ->assertInertia(fn (Assert $page) => $page->where('product.presets', []));
});

it('applies a preset: every control written, every control broadcast, the card turns active', function () {
    Event::fake([ControlValueUpdated::class]);
    $user = presetUser();
    $instance = app(RecipeInstaller::class)->install(presetRecipe(), $user, 'twitch_chat_overlay');
    $template = ChatPresets::overlayFor($instance);

    $this->actingAs($user)->post('/products/twitch-chat-overlay/presets/terminal')
        ->assertRedirect('/products/twitch-chat-overlay');

    $values = OverlayControl::where('overlay_template_id', $template->id)->pluck('value', 'key')->all();
    foreach (ChatPresets::PRESETS['terminal']['values'] as $key => $value) {
        expect($values[$key])->toBe($value);
    }

    Event::assertDispatchedTimes(ControlValueUpdated::class, 13);
    Event::assertDispatched(ControlValueUpdated::class, fn (ControlValueUpdated $e) => $e->overlaySlug === $template->slug && $e->key === 'skin' && $e->value === 'terminal');

    $this->actingAs($user)->get('/products/twitch-chat-overlay')
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.presets.0.active', false)
            ->where('product.presets.1.active', true)
        );
});

it('shows no preset as active once a value has drifted from the bundle', function () {
    $user = presetUser();
    $instance = app(RecipeInstaller::class)->install(presetRecipe(), $user, 'twitch_chat_overlay');
    ChatPresets::overlayFor($instance)->controls()->where('key', 'accent')->first()->writeValue('#123456');

    $this->actingAs($user)->get('/products/twitch-chat-overlay')
        ->assertInertia(fn (Assert $page) => $page->where('product.presets', fn ($presets) => collect($presets)->every(fn ($p) => $p['active'] === false)));
});

it('refuses an unknown preset, a product without presets, and an account that has not installed', function () {
    $user = presetUser();

    $this->actingAs($user)->post('/products/twitch-chat-overlay/presets/neon')->assertNotFound();

    app(RecipeInstaller::class)->install(presetRecipe(), $user, 'twitch_chat_overlay');
    $this->actingAs($user)->post('/products/twitch-chat-overlay/presets/glitter')->assertNotFound();
    $this->actingAs($user)->post('/products/chat-tower/presets/clean')->assertNotFound();

    $other = presetUser();
    $this->actingAs($other)->post('/products/twitch-chat-overlay/presets/neon')->assertNotFound();
    expect(OverlayControl::where('user_id', $user->id)->where('key', 'skin')->value('value'))->toBe('clean');
});

it('requires a login', function () {
    $this->post('/products/twitch-chat-overlay/presets/neon')->assertRedirect();
});
