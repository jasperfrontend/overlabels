<?php

use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\ChatDesigner;
use App\Support\ChatPresets;
use App\Support\OverlayMarkdown;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * The chat designer: every knob on the left, the product's own overlay on the
 * right. The right-hand side is the REAL overlay in a frame, not a preview
 * pipeline, so there is nothing here that can drift from what OBS shows - and
 * the knobs write controls through the ordinary value endpoint, whose
 * broadcast the frame and OBS are both already listening on.
 *
 * What these pin: the vocabularies the designer offers really exist in the
 * overlay, every control has a home on the page, and the preview frame's token
 * is one token, marked, reused and short-lived rather than minted per render.
 */
function designerUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'designtester'.fake()->unique()->randomNumber(5)],
    ]);
}

function designerRecipe(): Recipe
{
    $catalog = app(RecipeCatalog::class);

    return $catalog->sync($catalog->find(ChatPresets::PRODUCT));
}

function designerDocument(): array
{
    return OverlayMarkdown::parse(file_get_contents(RecipeInstaller::directoryFor(ChatPresets::PRODUCT).DIRECTORY_SEPARATOR.'chat.md'));
}

function designerInstall(User $user): OverlayTemplate
{
    return ChatPresets::overlayFor(app(RecipeInstaller::class)->install(designerRecipe(), $user, ChatPresets::PRODUCT));
}

it('offers only skins the overlay defines, and one per preset', function () {
    // A skin IS a preset key, one to one, which is what keeps adding a look to
    // one constant rather than two. `clean` has no rules on purpose: it is the
    // base look, so it is the one skin with no .skin- block.
    preg_match_all('/\.skin-([a-z]+)/', designerDocument()['css'], $matches);
    $defined = array_values(array_unique($matches[1]));

    expect(collect(ChatDesigner::skins())->pluck('value')->all())->toBe(array_keys(ChatPresets::PRESETS));

    foreach (ChatDesigner::skins() as $skin) {
        expect($skin['label'])->not->toBe('')
            ->and($skin['hint'])->not->toBe('');

        if ($skin['value'] !== 'clean') {
            expect($defined)->toContain($skin['value']);
        }
    }

    expect($defined)->not->toContain('clean');
});

it('offers only layouts and backgrounds the overlay styles, and fonts it loads', function () {
    $doc = designerDocument();

    foreach (ChatDesigner::CHOICES['layout'] as $choice) {
        expect($doc['css'])->toContain('.layout-'.$choice['value'].' ');
    }

    foreach (ChatDesigner::CHOICES['background'] as $choice) {
        expect($doc['css'])->toContain('.bg-'.$choice['value'].' ');
    }

    // A font the overlay never pulls in renders as the fallback, which reads
    // as the picker being broken rather than as a missing <link>.
    foreach (ChatDesigner::CHOICES['font'] as $choice) {
        expect($doc['head'])->toContain('family='.str_replace(' ', '+', $choice['value']).':');
    }

    foreach (ChatDesigner::CHOICES as $choices) {
        foreach ($choices as $choice) {
            expect($choice['label'])->not->toBe('')
                ->and($choice['hint'])->not->toBe('');
        }
    }
});

it('gives every look control exactly one home on the page', function () {
    // skin is deliberately absent from the groups: it has the preset strip and
    // the skin picker above them. Everything else has to land in a group, or a
    // fourteenth control would be added to the overlay and silently have no
    // knob.
    $grouped = collect(ChatDesigner::GROUPS)->flatMap(fn (array $group) => $group['keys'])->all();
    $expected = collect(ChatPresets::KEYS)->reject(fn (string $key) => $key === 'skin')->values()->all();

    expect(collect($grouped)->sort()->values()->all())->toBe(collect($expected)->sort()->values()->all())
        ->and(count($grouped))->toBe(count(array_unique($grouped)));

    // Anything with no closed vocabulary is rendered from its own type, so a
    // choice list for a key that is not a text control would never be reached.
    foreach (array_keys(ChatDesigner::CHOICES) as $key) {
        expect($expected)->toContain($key);
    }
});

it('hands the page every control the overlay has, with the presets and the window cap', function () {
    $user = designerUser();
    $template = designerInstall($user);

    $this->actingAs($user)->get('/products/twitch_chat/design')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/design')
            ->where('product.slug', 'twitch_chat')
            ->where('overlay.id', $template->id)
            ->where('overlay.slug', $template->slug)
            ->has('controls', 13)
            ->where('controls.skin.value', 'clean')
            ->where('controls.font_size.type', 'number')
            ->where('controls.font_size.config.max', 72)
            ->has('presets', 10)
            ->where('presets.1.key', 'terminal')
            ->where('presets.1.values.font', 'JetBrains Mono')
            ->has('skins', 10)
            ->has('choices.font', 6)
            ->has('groups', 5)
            ->where('chat_window', 50)
            ->where('chat_window_max', User::FOREACH_CAP_MAX)
        );
});

it('frames the real overlay, reading sample chat, on this origin', function () {
    $user = designerUser();
    $template = designerInstall($user);

    $url = $this->actingAs($user)->get('/products/twitch_chat/design')
        ->viewData('page')['props']['preview_url'];

    // Relative on purpose: the hosted-overlay origin (overlabels.net on prod)
    // would put the frame cross-origin for nothing, and the designer talks to
    // the frame.
    expect($url)->toStartWith("/overlay/{$template->slug}?chat=sample#")
        ->and($url)->not->toStartWith('http');

    $token = substr($url, strpos($url, '#') + 1);
    expect($token)->toMatch('/^[0-9a-f]{64}$/')
        ->and(OverlayAccessToken::findByToken($token)?->user_id)->toBe($user->id);
});

it('keeps one preview token, marked and short-lived, across renders', function () {
    $user = designerUser();
    designerInstall($user);

    $first = $this->actingAs($user)->get('/products/twitch_chat/design')->viewData('page')['props']['preview_url'];
    $second = $this->actingAs($user)->get('/products/twitch_chat/design')->viewData('page')['props']['preview_url'];

    // Minting per render would swap the frame's src and reload the preview
    // mid-design, and would invalidate the token the frame on screen is using.
    expect($second)->toBe($first);

    $tokens = OverlayAccessToken::where('user_id', $user->id)->get();
    expect($tokens)->toHaveCount(1)
        ->and($tokens->first()->name)->toBe(ChatDesigner::TOKEN_NAME)
        // The marker is what lets a future "we saw OBS load it" check tell a
        // preview apart from a browser source: both serve the same slug.
        ->and($tokens->first()->metadata['purpose'])->toBe(ChatDesigner::TOKEN_PURPOSE)
        ->and($tokens->first()->expires_at->isFuture())->toBeTrue()
        ->and($tokens->first()->expires_at->lessThan(now()->addDays(2)))->toBeTrue();
});

it('mints a fresh one, and drops the stale one, when the held token is gone', function () {
    $user = designerUser();
    designerInstall($user);

    $first = $this->actingAs($user)->get('/products/twitch_chat/design')->viewData('page')['props']['preview_url'];

    // Whatever ends a token - expiry, a revoke, a new browser - the next open
    // gets a working one rather than a frame that cannot render.
    OverlayAccessToken::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);
    $this->flushSession();

    $second = $this->actingAs($user)->get('/products/twitch_chat/design')->viewData('page')['props']['preview_url'];

    expect($second)->not->toBe($first)
        ->and(OverlayAccessToken::where('user_id', $user->id)->count())->toBe(1);
});

it('refuses another product and an account with no install', function () {
    $user = designerUser();

    $this->actingAs($user)->get('/products/twitch_chat/design')->assertNotFound();
    $this->actingAs($user)->get('/products/chat_tower/design')->assertNotFound();

    designerInstall($user);
    $this->actingAs($user)->get('/products/twitch_chat/design')->assertOk();

    // Someone else's install is not a designer for this account.
    $this->actingAs(designerUser())->get('/products/twitch_chat/design')->assertNotFound();
});

it('requires a login', function () {
    $this->get('/products/twitch_chat/design')->assertRedirect();
});

it('applies a preset without leaving the page when the designer asks for JSON', function () {
    $user = designerUser();
    $template = designerInstall($user);

    // Navigating would reload the frame and throw away the chat in it, so the
    // designer takes the values back instead of a redirect.
    $this->actingAs($user)->postJson('/products/twitch_chat/presets/terminal')
        ->assertOk()
        ->assertJsonPath('values.skin', 'terminal')
        ->assertJsonPath('values.font', 'JetBrains Mono');

    expect($template->controls()->where('key', 'skin')->value('value'))->toBe('terminal');
});

it('writes the chat window as a preference and answers JSON', function () {
    $user = designerUser();
    designerInstall($user);

    $this->actingAs($user)->patchJson('/settings/foreach-caps', [
        'subscribers' => 10, 'goals' => 3, 'followers' => 5, 'followed' => 5, 'chat' => 12, 'checkins' => 50,
    ])
        ->assertOk()
        ->assertJsonPath('foreach_caps.chat', 12);

    expect($user->fresh()->foreachCaps()['chat'])->toBe(12);

    $this->actingAs($user)->get('/products/twitch_chat/design')
        ->assertInertia(fn (Assert $page) => $page->where('chat_window', 12));
});
