<?php

use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\BunnyFonts;
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
    return ChatPresets::overlayFor(app(RecipeInstaller::class)->install(designerRecipe(), $user, RecipeInstance::instanceSlugFrom(ChatPresets::PRODUCT)));
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

it('offers only layouts and backgrounds the overlay styles', function () {
    $doc = designerDocument();

    foreach (ChatDesigner::CHOICES['layout'] as $choice) {
        expect($doc['css'])->toContain('.layout-'.$choice['value'].' ');
    }

    foreach (ChatDesigner::CHOICES['background'] as $choice) {
        expect($doc['css'])->toContain('.bg-'.$choice['value'].' ');
    }

    foreach (ChatDesigner::CHOICES as $choices) {
        foreach ($choices as $choice) {
            expect($choice['label'])->not->toBe('')
                ->and($choice['hint'])->not->toBe('');
        }
    }

    // The font row is not one of these. Its vocabulary is open, so it has its
    // own test below rather than a list held against the overlay's head.
    expect(ChatDesigner::CHOICES)->not->toHaveKey('font');
});

it('suggests only fonts Bunny actually serves, and loads them from the control not the head', function () {
    $doc = designerDocument();

    // A suggested family Bunny does not serve is a row that writes a value the
    // value endpoint refuses - a picker that looks fine and does nothing.
    foreach (ChatDesigner::SUGGESTED_FONTS as $suggestion) {
        expect(BunnyFonts::has($suggestion['value']))->toBeTrue()
            ->and(BunnyFonts::canonical($suggestion['value']))->toBe($suggestion['value'])
            ->and($suggestion['hint'])->not->toBe('');
    }

    // The head must NOT carry a font link. One there is what caps the overlay
    // at the families it shipped with, which is the whole thing this replaced.
    expect($doc['head'])->not->toContain('fonts.googleapis.com')
        ->and($doc['head'])->not->toContain('/css?family=')
        ->and($doc['head'])->not->toContain('/css2?family=');

    // ...because the control is declared a webfont, which is what puts its key
    // in the render payload and makes the overlay load the family itself.
    $font = collect($doc['controls'])->firstWhere('key', 'font');
    expect($font['config']['webfont'] ?? null)->toBeTrue()
        ->and(BunnyFonts::has($font['value']))->toBeTrue();
});

it('installs the font control with the webfont declaration actually on the row', function () {
    // The declaration being right in the recipe is not the same as it reaching
    // the database. When it did not, the head had already lost its font link
    // and the control was not allowed to load one, so the designer wrote a
    // value that changed nothing on the overlay and nothing anywhere errored.
    $control = designerInstall(designerUser())->controls()->where('key', 'font')->first();

    expect($control->config['webfont'] ?? null)->toBeTrue()
        ->and(BunnyFonts::has($control->value))->toBeTrue();
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

    $this->actingAs($user)->get('/products/twitch-chat-overlay/design')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/design')
            ->where('product.slug', 'twitch-chat-overlay')
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
            // The font row is a search over the catalogue, so the page ships
            // the shortlist and the URL to fetch the rest from, not a vocabulary.
            ->has('suggested_fonts', 6)
            ->where('fonts_url', asset(BunnyFonts::CATALOGUE_PATH))
            ->has('groups', 5)
            ->where('chat_window', 50)
            ->where('chat_window_max', User::FOREACH_CAP_MAX)
        );
});

it('frames the real overlay, reading sample chat, on this origin', function () {
    $user = designerUser();
    $template = designerInstall($user);

    $url = $this->actingAs($user)->get('/products/twitch-chat-overlay/design')
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

    $first = $this->actingAs($user)->get('/products/twitch-chat-overlay/design')->viewData('page')['props']['preview_url'];
    $second = $this->actingAs($user)->get('/products/twitch-chat-overlay/design')->viewData('page')['props']['preview_url'];

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

    $first = $this->actingAs($user)->get('/products/twitch-chat-overlay/design')->viewData('page')['props']['preview_url'];

    // Whatever ends a token - expiry, a revoke, a new browser - the next open
    // gets a working one rather than a frame that cannot render.
    OverlayAccessToken::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);
    $this->flushSession();

    $second = $this->actingAs($user)->get('/products/twitch-chat-overlay/design')->viewData('page')['props']['preview_url'];

    expect($second)->not->toBe($first)
        ->and(OverlayAccessToken::where('user_id', $user->id)->count())->toBe(1);
});

it('lets a second session mint its own token without killing the first one', function () {
    $user = designerUser();
    designerInstall($user);

    $first = $this->actingAs($user)->get('/products/twitch-chat-overlay/design')->viewData('page')['props']['preview_url'];
    $firstToken = substr($first, strpos($first, '#') + 1);

    // A second browser: same account, no held token in its session, and the
    // first session's frame still open on the first token. Until 2026-09-22
    // this mint swept the first token and that frame died mid-design.
    $this->flushSession();
    $second = $this->actingAs($user)->get('/products/twitch-chat-overlay/design')->viewData('page')['props']['preview_url'];

    expect($second)->not->toBe($first)
        ->and(OverlayAccessToken::findByToken($firstToken))->not->toBeNull()
        ->and(OverlayAccessToken::where('user_id', $user->id)->where('metadata->purpose', ChatDesigner::TOKEN_PURPOSE)->count())->toBe(2);

    // Once the first one runs out, the next mint from anywhere clears it.
    OverlayAccessToken::where('token_hash', hash('sha256', $firstToken))->update(['expires_at' => now()->subMinute()]);
    $this->flushSession();
    $this->actingAs($user)->get('/products/twitch-chat-overlay/design');

    expect(OverlayAccessToken::findByToken($firstToken))->toBeNull()
        ->and(OverlayAccessToken::where('user_id', $user->id)->where('metadata->purpose', ChatDesigner::TOKEN_PURPOSE)->count())->toBe(2);
});

it('refuses another product and an account with no install', function () {
    $user = designerUser();

    $this->actingAs($user)->get('/products/twitch-chat-overlay/design')->assertNotFound();
    $this->actingAs($user)->get('/products/chat-tower/design')->assertNotFound();

    designerInstall($user);
    $this->actingAs($user)->get('/products/twitch-chat-overlay/design')->assertOk();

    // Someone else's install is not a designer for this account.
    $this->actingAs(designerUser())->get('/products/twitch-chat-overlay/design')->assertNotFound();
});

it('requires a login', function () {
    $this->get('/products/twitch-chat-overlay/design')->assertRedirect();
});

it('applies a preset without leaving the page when the designer asks for JSON', function () {
    $user = designerUser();
    $template = designerInstall($user);

    // Navigating would reload the frame and throw away the chat in it, so the
    // designer takes the values back instead of a redirect.
    $this->actingAs($user)->postJson('/products/twitch-chat-overlay/presets/terminal')
        ->assertOk()
        ->assertJsonPath('values.skin', 'terminal')
        ->assertJsonPath('values.font', 'JetBrains Mono');

    expect($template->controls()->where('key', 'skin')->value('value'))->toBe('terminal');
});

it('hands over the account chat filters, which are not appended to a serialised user', function () {
    $user = designerUser();
    designerInstall($user);
    $user->setPreference('chat_filters.hide_commands', true);
    $user->setPreference('chat_filters.hidden_logins', ['spambot', 'anotherbot']);
    $user->save();

    $this->actingAs($user)->get('/products/twitch-chat-overlay/design')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('chat_filters.hide_commands', true)
            ->where('chat_filters.hidden_logins', ['spambot', 'anotherbot'])
            ->where('max_hidden_logins', User::MAX_HIDDEN_LOGINS)
        );
});

it('writes both chat filters from the designer and answers JSON with the normalised list', function () {
    $user = designerUser();
    designerInstall($user);

    // What is typed and what is kept differ: the endpoint lowercases, strips a
    // leading @, drops anything that is not a Twitch login and dedupes. The
    // designer shows the count that comes back rather than the one it counted.
    $this->actingAs($user)->patchJson('/settings/chat', [
        'hide_commands' => true,
        'hidden_logins' => "@SpamBot\nspambot\nnot a login!\nanotherbot",
    ])
        ->assertOk()
        ->assertJsonPath('chat_filters.hide_commands', true)
        ->assertJsonPath('chat_filters.hidden_logins', ['spambot', 'anotherbot']);

    expect($user->fresh()->chatFilters())->toBe(['hide_commands' => true, 'hidden_logins' => ['spambot', 'anotherbot']]);

    $this->actingAs($user)->get('/products/twitch-chat-overlay/design')
        ->assertInertia(fn (Assert $page) => $page
            ->where('chat_filters.hide_commands', true)
            ->where('chat_filters.hidden_logins', ['spambot', 'anotherbot'])
        );
});

it('still redirects the chat settings page, which asks for no JSON', function () {
    $user = designerUser();

    $this->actingAs($user)->patch('/settings/chat', ['hide_commands' => false, 'hidden_logins' => 'spambot'])
        ->assertRedirect()
        ->assertSessionHas('success');
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

    $this->actingAs($user)->get('/products/twitch-chat-overlay/design')
        ->assertInertia(fn (Assert $page) => $page->where('chat_window', 12));
});
