<?php

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Models\UserChatPreset;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\ProductDesigner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * A streamer's own looks for a product: the current look saved under a name,
 * then applied, updated, renamed and deleted from the designer. Values are
 * captured server-side; the client only ever sends a name. Which one is
 * active is never stored - the designer derives it. One table holds every
 * product's looks, and a look is only ever reachable from the designer it
 * was saved on.
 */
function savedLookUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'savedlook'.fake()->unique()->randomNumber(5)],
    ]);
}

function savedLookDesigner(string $product = 'twitch-chat-overlay'): array
{
    return ProductDesigner::declared(app(RecipeCatalog::class)->find($product));
}

function savedLookInstall(User $user, string $product = 'twitch-chat-overlay'): RecipeInstance
{
    $catalog = app(RecipeCatalog::class);

    return app(RecipeInstaller::class)->install($catalog->sync($catalog->find($product)), $user, RecipeInstance::instanceSlugFrom($product));
}

function savedLookOverlay(RecipeInstance $instance, string $product = 'twitch-chat-overlay'): OverlayTemplate
{
    return ProductDesigner::overlayFor(savedLookDesigner($product), $instance);
}

function savedLookPreset(string $key): array
{
    return ProductDesigner::preset(savedLookDesigner(), $key)['values'];
}

const SAVED_LOOKS = '/products/twitch-chat-overlay/saved-presets';

it('saves the overlay look as it stands, under the name given, and the designer lists it', function () {
    $user = savedLookUser();
    $instance = savedLookInstall($user);
    $template = savedLookOverlay($instance);

    // Tune two knobs off the Clean install, so the capture is provably the
    // rows and not the built-in bundle.
    $template->controls()->where('key', 'layout')->first()->writeValue('ticker');
    $template->controls()->where('key', 'accent')->first()->writeValue('#123456');

    $response = $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => '  Podcast night  '])
        ->assertCreated()
        ->assertJsonPath('preset.name', 'Podcast night')
        ->assertJsonPath('preset.values.layout', 'ticker')
        ->assertJsonPath('preset.values.accent', '#123456')
        ->assertJsonPath('preset.values.skin', 'clean');

    $values = $response->json('preset.values');
    expect(array_keys($values))->toBe(ProductDesigner::keys(savedLookDesigner()))
        ->and(UserChatPreset::where('user_id', $user->id)->count())->toBe(1)
        ->and(UserChatPreset::where('user_id', $user->id)->value('product'))->toBe('twitch-chat-overlay');

    $this->actingAs($user)->get('/products/twitch-chat-overlay/design')
        ->assertInertia(fn (Assert $page) => $page
            ->has('saved_presets', 1)
            ->where('saved_presets.0.name', 'Podcast night')
            ->where('saved_presets.0.values.layout', 'ticker')
            ->where('saved_presets_max', UserChatPreset::MAX_PER_USER)
        );
});

it('keeps any name verbatim, emoji and punctuation included', function () {
    $user = savedLookUser();
    savedLookInstall($user);

    $name = '🔥|name:thing';
    $family = '👨‍👩‍👧‍👦 family <b>&</b> "quotes" 40';

    $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => $name])->assertCreated()->assertJsonPath('preset.name', $name);
    $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => $family])->assertCreated()->assertJsonPath('preset.name', $family);

    expect(UserChatPreset::where('user_id', $user->id)->pluck('name')->sort()->values()->all())->toBe(collect([$name, $family])->sort()->values()->all());

    // Forty emoji is forty characters, not eighty or a hundred and sixty.
    $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => str_repeat('🔥', UserChatPreset::NAME_MAX)])->assertCreated();
    $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => str_repeat('🔥', UserChatPreset::NAME_MAX + 1)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('refuses a blank name, a name already used, and a twenty-first look', function () {
    $user = savedLookUser();
    savedLookInstall($user);

    $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => '   '])->assertUnprocessable()->assertJsonValidationErrors(['name']);

    $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => 'Cozy'])->assertCreated();
    $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => 'Cozy'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You already have a look with that name.');

    for ($i = UserChatPreset::where('user_id', $user->id)->count(); $i < UserChatPreset::MAX_PER_USER; $i++) {
        UserChatPreset::create(['user_id' => $user->id, 'product' => 'twitch-chat-overlay', 'name' => "Look $i", 'values' => ['skin' => 'clean']]);
    }

    $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => 'One more'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You have '.UserChatPreset::MAX_PER_USER.' saved looks already. Delete one to save another.');

    expect(UserChatPreset::where('user_id', $user->id)->count())->toBe(UserChatPreset::MAX_PER_USER);
});

it('applies a saved look: every captured control written and broadcast, the values returned', function () {
    Event::fake([ControlValueUpdated::class]);
    $user = savedLookUser();
    $instance = savedLookInstall($user);
    $template = savedLookOverlay($instance);

    $values = savedLookPreset('vapor');
    $values['font_size'] = '31';
    $preset = UserChatPreset::create(['user_id' => $user->id, 'product' => 'twitch-chat-overlay', 'name' => 'Mine', 'values' => $values]);

    $this->actingAs($user)->postJson(SAVED_LOOKS."/{$preset->id}/apply")
        ->assertOk()
        ->assertJsonPath('values.font_size', '31')
        ->assertJsonPath('values.skin', 'vapor');

    $stored = OverlayControl::where('overlay_template_id', $template->id)->pluck('value', 'key')->all();
    foreach ($values as $key => $value) {
        expect($stored[$key])->toBe($value);
    }

    Event::assertDispatchedTimes(ControlValueUpdated::class, count(ProductDesigner::keys(savedLookDesigner())));
    Event::assertDispatched(ControlValueUpdated::class, fn (ControlValueUpdated $e) => $e->overlaySlug === $template->slug && $e->key === 'font_size' && $e->value === '31');
});

it('updates a saved look with the overlay as it stands now', function () {
    $user = savedLookUser();
    $instance = savedLookInstall($user);
    $template = savedLookOverlay($instance);

    $preset = UserChatPreset::create(['user_id' => $user->id, 'product' => 'twitch-chat-overlay', 'name' => 'Mine', 'values' => savedLookPreset('pixel')]);
    $template->controls()->where('key', 'lifetime')->first()->writeValue('9');

    $this->actingAs($user)->postJson(SAVED_LOOKS."/{$preset->id}/overwrite")
        ->assertOk()
        ->assertJsonPath('preset.id', $preset->id)
        ->assertJsonPath('preset.values.skin', 'clean')
        ->assertJsonPath('preset.values.lifetime', '9');

    expect($preset->fresh()->values['skin'])->toBe('clean');
});

it('renames a saved look, and refuses the name of another of your own', function () {
    $user = savedLookUser();
    savedLookInstall($user);

    $one = UserChatPreset::create(['user_id' => $user->id, 'product' => 'twitch-chat-overlay', 'name' => 'One', 'values' => ['skin' => 'clean']]);
    UserChatPreset::create(['user_id' => $user->id, 'product' => 'twitch-chat-overlay', 'name' => 'Two', 'values' => ['skin' => 'clean']]);

    $this->actingAs($user)->patchJson(SAVED_LOOKS."/{$one->id}", ['name' => ' Uno '])
        ->assertOk()
        ->assertJsonPath('preset.name', 'Uno');

    // Its own current name is not a collision.
    $this->actingAs($user)->patchJson(SAVED_LOOKS."/{$one->id}", ['name' => 'Uno'])->assertOk();

    $this->actingAs($user)->patchJson(SAVED_LOOKS."/{$one->id}", ['name' => 'Two'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    expect($one->fresh()->name)->toBe('Uno');
});

it('deletes a saved look', function () {
    $user = savedLookUser();
    savedLookInstall($user);
    $preset = UserChatPreset::create(['user_id' => $user->id, 'product' => 'twitch-chat-overlay', 'name' => 'Gone', 'values' => ['skin' => 'clean']]);

    $this->actingAs($user)->deleteJson(SAVED_LOOKS."/{$preset->id}")->assertOk()->assertJsonPath('deleted', true);

    expect(UserChatPreset::find($preset->id))->toBeNull();
});

it('answers 404 for another account\'s look on every door, and never touches it', function () {
    $owner = savedLookUser();
    savedLookInstall($owner);
    $theirs = UserChatPreset::create(['user_id' => $owner->id, 'product' => 'twitch-chat-overlay', 'name' => 'Theirs', 'values' => ['skin' => 'terminal']]);

    $intruder = savedLookUser();
    savedLookInstall($intruder);

    $this->actingAs($intruder)->postJson(SAVED_LOOKS."/{$theirs->id}/apply")->assertNotFound();
    $this->actingAs($intruder)->postJson(SAVED_LOOKS."/{$theirs->id}/overwrite")->assertNotFound();
    $this->actingAs($intruder)->patchJson(SAVED_LOOKS."/{$theirs->id}", ['name' => 'Stolen'])->assertNotFound();
    $this->actingAs($intruder)->deleteJson(SAVED_LOOKS."/{$theirs->id}")->assertNotFound();

    $fresh = $theirs->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe('Theirs')
        ->and($fresh->values['skin'])->toBe('terminal');
});

it('keeps each product\'s looks to its own designer', function () {
    // The same account installs both products. A look saved on the bubbles
    // designer must not be listed on, or reachable from, the chat designer:
    // its keys mean nothing to the chat overlay and would apply as nothing.
    $user = savedLookUser();
    savedLookInstall($user);
    savedLookInstall($user, 'chat-emote-bubbles');

    $bubbles = $this->actingAs($user)->postJson('/products/chat-emote-bubbles/saved-presets', ['name' => 'Cozy'])
        ->assertCreated()
        ->assertJsonPath('preset.values.look', 'bubble')
        ->json('preset');

    expect(array_keys($bubbles['values']))->toBe(ProductDesigner::keys(savedLookDesigner('chat-emote-bubbles')))
        ->and(UserChatPreset::find($bubbles['id'])->product)->toBe('chat-emote-bubbles');

    // The same name is free on the other product.
    $chat = $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => 'Cozy'])->assertCreated()->json('preset');

    $this->actingAs($user)->get('/products/twitch-chat-overlay/design')
        ->assertInertia(fn (Assert $page) => $page->has('saved_presets', 1)->where('saved_presets.0.id', $chat['id']));
    $this->actingAs($user)->get('/products/chat-emote-bubbles/design')
        ->assertInertia(fn (Assert $page) => $page->has('saved_presets', 1)->where('saved_presets.0.id', $bubbles['id']));

    // Every door on the chat designer treats the bubbles look as not there.
    $this->actingAs($user)->postJson(SAVED_LOOKS."/{$bubbles['id']}/apply")->assertNotFound();
    $this->actingAs($user)->postJson(SAVED_LOOKS."/{$bubbles['id']}/overwrite")->assertNotFound();
    $this->actingAs($user)->patchJson(SAVED_LOOKS."/{$bubbles['id']}", ['name' => 'Moved'])->assertNotFound();
    $this->actingAs($user)->deleteJson(SAVED_LOOKS."/{$bubbles['id']}")->assertNotFound();

    expect(UserChatPreset::find($bubbles['id'])?->name)->toBe('Cozy');

    // And the cap counts per product.
    for ($i = 1; $i < UserChatPreset::MAX_PER_USER; $i++) {
        UserChatPreset::create(['user_id' => $user->id, 'product' => 'chat-emote-bubbles', 'name' => "Look $i", 'values' => ['look' => 'snow']]);
    }
    $this->actingAs($user)->postJson('/products/chat-emote-bubbles/saved-presets', ['name' => 'One more'])->assertUnprocessable();
    $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => 'Still room'])->assertCreated();
});
