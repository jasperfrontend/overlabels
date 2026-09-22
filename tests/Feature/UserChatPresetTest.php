<?php

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Models\UserChatPreset;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\ChatPresets;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * A streamer's own looks for the Twitch Chat product: the current look
 * saved under a name, then applied, updated, renamed and deleted from the
 * designer. Values are captured server-side; the client only ever sends a
 * name. Which one is active is never stored - the designer derives it.
 */
function savedLookUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'savedlook'.fake()->unique()->randomNumber(5)],
    ]);
}

function savedLookInstall(User $user): RecipeInstance
{
    $catalog = app(RecipeCatalog::class);

    return app(RecipeInstaller::class)->install($catalog->sync($catalog->find(ChatPresets::PRODUCT)), $user, 'twitch_chat_overlay');
}

const SAVED_LOOKS = '/products/twitch-chat-overlay/saved-presets';

it('saves the overlay look as it stands, under the name given, and the designer lists it', function () {
    $user = savedLookUser();
    $instance = savedLookInstall($user);
    $template = ChatPresets::overlayFor($instance);

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
    expect(array_keys($values))->toBe(ChatPresets::KEYS)
        ->and(UserChatPreset::where('user_id', $user->id)->count())->toBe(1);

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
        UserChatPreset::create(['user_id' => $user->id, 'name' => "Look $i", 'values' => ['skin' => 'clean']]);
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
    $template = ChatPresets::overlayFor($instance);

    $values = ChatPresets::PRESETS['vapor']['values'];
    $values['font_size'] = '31';
    $preset = UserChatPreset::create(['user_id' => $user->id, 'name' => 'Mine', 'values' => $values]);

    $this->actingAs($user)->postJson(SAVED_LOOKS."/{$preset->id}/apply")
        ->assertOk()
        ->assertJsonPath('values.font_size', '31')
        ->assertJsonPath('values.skin', 'vapor');

    $stored = OverlayControl::where('overlay_template_id', $template->id)->pluck('value', 'key')->all();
    foreach ($values as $key => $value) {
        expect($stored[$key])->toBe($value);
    }

    Event::assertDispatchedTimes(ControlValueUpdated::class, count(ChatPresets::KEYS));
    Event::assertDispatched(ControlValueUpdated::class, fn (ControlValueUpdated $e) => $e->overlaySlug === $template->slug && $e->key === 'font_size' && $e->value === '31');
});

it('updates a saved look with the overlay as it stands now', function () {
    $user = savedLookUser();
    $instance = savedLookInstall($user);
    $template = ChatPresets::overlayFor($instance);

    $preset = UserChatPreset::create(['user_id' => $user->id, 'name' => 'Mine', 'values' => ChatPresets::PRESETS['pixel']['values']]);
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

    $one = UserChatPreset::create(['user_id' => $user->id, 'name' => 'One', 'values' => ['skin' => 'clean']]);
    UserChatPreset::create(['user_id' => $user->id, 'name' => 'Two', 'values' => ['skin' => 'clean']]);

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
    $preset = UserChatPreset::create(['user_id' => $user->id, 'name' => 'Gone', 'values' => ['skin' => 'clean']]);

    $this->actingAs($user)->deleteJson(SAVED_LOOKS."/{$preset->id}")->assertOk()->assertJsonPath('deleted', true);

    expect(UserChatPreset::find($preset->id))->toBeNull();
});

it('answers 404 for another account\'s look on every door, and never touches it', function () {
    $owner = savedLookUser();
    savedLookInstall($owner);
    $theirs = UserChatPreset::create(['user_id' => $owner->id, 'name' => 'Theirs', 'values' => ['skin' => 'terminal']]);

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

it('answers 404 for another product and for an account with no install', function () {
    $user = savedLookUser();

    $this->actingAs($user)->postJson(SAVED_LOOKS, ['name' => 'No install'])->assertNotFound();

    savedLookInstall($user);
    $this->actingAs($user)->postJson('/products/chat-tower/saved-presets', ['name' => 'Wrong product'])->assertNotFound();

    expect(UserChatPreset::where('user_id', $user->id)->count())->toBe(0);
});

it('requires a login', function () {
    $this->postJson(SAVED_LOOKS, ['name' => 'Anon'])->assertUnauthorized();
});

it('goes with the account when the account is deleted', function () {
    $user = savedLookUser();
    savedLookInstall($user);
    $preset = UserChatPreset::create(['user_id' => $user->id, 'name' => 'Mine', 'values' => ['skin' => 'clean']]);

    $user->forceDelete();

    expect(UserChatPreset::find($preset->id))->toBeNull();
});
