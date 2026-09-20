<?php

use App\Models\OverlayAccessToken;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use App\Support\BunnyFonts;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($t, $e) => $e);
    });
});

/**
 * A font control: a control whose value is a family name the overlay loads for
 * itself, instead of a family named by a <link> baked into the head.
 *
 * The head is injected once at load and never again, so a link written there
 * caps an overlay at the fonts it shipped with - which is why the chat overlay
 * offered six. `webfont=true` moves the loading to the control, and these pin
 * the three things that makes true: the key travels in the render payload, the
 * written value is an allowlist, and the catalogue keeps the two rules the
 * overlay derives a URL from.
 */
function webfontOwner(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'fake-twitch-token',
    ]);
}

function webfontTemplate(User $user): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'static',
        'head' => '',
        'html' => '<div>[[[c:font]]]</div>',
        'css' => ':root { --font: [[[c:font]]]; }',
        'slug' => 'webfont-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);
}

function webfontControl(OverlayTemplate $template, string $value = 'Albert Sans'): OverlayControl
{
    return OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $template->owner_id,
        'key' => 'font',
        'label' => 'Font',
        'type' => 'text',
        'value' => $value,
        'config' => ['webfont' => true],
    ]);
}

it('keeps the catalogue on disk and both rules the overlay depends on', function () {
    $catalogue = BunnyFonts::all();

    expect($catalogue)->not->toBeEmpty();

    // Rule 1: OverlayRenderer builds the font URL from the family name alone,
    // with no catalogue shipped to the overlay. If the slug ever stops being
    // the name lowercased and hyphenated, every overlay silently renders its
    // fallback and nothing on the server would notice.
    foreach ($catalogue as $slug => $entry) {
        expect(BunnyFonts::slug($entry['name']))->toBe($slug);
    }

    // Rule 2: one fixed weight pair is asked for on both sides, so no family
    // may be in here that has neither of them - the stylesheet would come back
    // with no @font-face at all.
    expect(BunnyFonts::WEIGHTS)->toBe('400,700')
        ->and($catalogue)->not->toHaveKey('buda')
        ->and($catalogue)->not->toHaveKey('coda-caption');
});

it('builds a Bunny URL for a known family and refuses to guess for an unknown one', function () {
    expect(BunnyFonts::cssUrl('Space Grotesk'))
        ->toBe('https://fonts.bunny.net/css?family=space-grotesk:400,700&display=swap');

    // Null, not a best-effort URL: Bunny answers an unknown family with HTTP
    // 200 and a CSS comment, so a link built for one looks like it worked.
    expect(BunnyFonts::cssUrl('Definitely Not A Font'))->toBeNull()
        ->and(BunnyFonts::has('Definitely Not A Font'))->toBeFalse();
});

it('ships a webfont control key in the render payload, and only that control', function () {
    $user = webfontOwner();
    $template = webfontTemplate($user);
    webfontControl($template);

    OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'label',
        'label' => 'Label',
        'type' => 'text',
        'value' => 'Albert Sans',
    ]);

    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'webfont-test',
        'is_active' => true,
    ]);

    // A plain text control holding the same string is not a font: the
    // declaration is what makes one, not the value looking font-shaped.
    $this->postJson('/api/overlay/render', ['slug' => $template->slug, 'token' => $plain])
        ->assertOk()
        ->assertJsonPath('webfont_controls', ['font']);
});

it('refuses a font the catalogue does not name, and canonicalises one it does', function () {
    $user = webfontOwner();
    $template = webfontTemplate($user);
    $control = webfontControl($template);

    $this->actingAs($user);

    // The value becomes a URL the overlay fetches, so it is an allowlist.
    $this->postJson(route('controls.value', [$template, $control]), ['value' => 'Evil"><script>'])
        ->assertStatus(422);

    expect($control->fresh()->value)->toBe('Albert Sans');

    // Spelled the catalogue's way on the way in, because the value is also the
    // CSS family name and Bunny serves one exact spelling.
    $this->postJson(route('controls.value', [$template, $control]), ['value' => 'space grotesk'])
        ->assertOk()
        ->assertJsonPath('value', 'Space Grotesk');

    expect($control->fresh()->value)->toBe('Space Grotesk');
});

it('leaves controls that are not webfonts alone', function () {
    $user = webfontOwner();
    $template = webfontTemplate($user);

    $control = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'headline',
        'label' => 'Headline',
        'type' => 'text',
        'value' => 'hello',
    ]);

    $this->actingAs($user);

    // An ordinary text control still takes any string. The allowlist is a
    // property of the declaration, not a new rule for every text control.
    $this->postJson(route('controls.value', [$template, $control]), ['value' => 'Not A Font At All'])
        ->assertOk();

    expect($control->fresh()->value)->toBe('Not A Font At All');
});
