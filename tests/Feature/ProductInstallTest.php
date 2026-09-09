<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayAccessToken;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Services\Recipes\RecipeManifestValidator;
use App\Support\OverlayMarkdown;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * A product is a listed recipe whose manifest has an `installs` section.
 * Chat Checkin is the first: one overlay from a markdown file next to the
 * manifest, one integration connected. The install is one POST; what is
 * left for the streamer is the product's wiring circuit on the same page.
 */
function productUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'producttester'.fake()->unique()->randomNumber(5)],
    ], $attrs));
}

function chatCheckinRecipe(): Recipe
{
    $catalog = app(RecipeCatalog::class);

    return $catalog->sync($catalog->find('chat_checkin'));
}

// ---------------------------------------------------------------------------
// The catalogue
// ---------------------------------------------------------------------------

it('lists chat_checkin as a product and keeps the picker recipes unlisted', function () {
    $listed = app(RecipeCatalog::class)->listed();

    expect(array_keys($listed))->toBe(['chat_checkin', 'follower_bowling'])
        ->and(array_keys(app(RecipeCatalog::class)->all()))->toContain('coin_flip', 'dice');
});

it('parses every overlay document a listed product ships', function () {
    foreach (app(RecipeCatalog::class)->listed() as $slug => $manifest) {
        foreach ($manifest['installs']['overlays'] ?? [] as $overlay) {
            $path = RecipeInstaller::directoryFor($slug).DIRECTORY_SEPARATOR.$overlay['file'];
            expect(is_file($path))->toBeTrue("{$slug} names a missing overlay file {$overlay['file']}");

            // A formatter that touches these files breaks them quietly: a
            // single-quoted name parses WITH its quotes, and a padded table
            // parses to zero controls. Both are how prettier reformats markdown.
            $doc = OverlayMarkdown::parse(file_get_contents($path));
            expect($doc['name'])->not->toBe('')
                ->and($doc['name'])->not->toMatch('/^[\x27"]/')
                ->and($doc['description'])->not->toBeNull()
                ->and($doc['html'])->not->toBe('');
        }
    }
});

it('rejects an overlay file name with a path separator', function () {
    $manifest = app(RecipeCatalog::class)->find('chat_checkin');
    $manifest['installs']['overlays'][0]['file'] = '../globe.md';

    $result = app(RecipeManifestValidator::class)->validate($manifest);

    expect($result['valid'])->toBeFalse();
});

it('still installs a picker-only manifest with no installs section', function () {
    $catalog = app(RecipeCatalog::class);
    $recipe = $catalog->sync($catalog->find('dice'));
    $user = productUser();

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    expect($instance->primitive_map)->not->toHaveKey('overlays')
        ->and(OverlayTemplate::where('owner_id', $user->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// The install
// ---------------------------------------------------------------------------

it('creates the globe overlay with its two controls on install', function () {
    $user = productUser();

    $instance = app(RecipeInstaller::class)->install(chatCheckinRecipe(), $user, 'chat_checkin');

    $templateId = $instance->primitive_map['overlays']['globe'] ?? null;
    expect($templateId)->not->toBeNull();

    $template = OverlayTemplate::find($templateId);
    expect($template)->not->toBeNull()
        ->and($template->owner_id)->toBe($user->id)
        ->and($template->type)->toBe('static')
        ->and($template->is_public)->toBeFalse()
        ->and($template->html)->toContain('[[[checkin_globe]]]')
        ->and($template->template_tags)->toContain('checkin_globe');

    $keys = OverlayControl::where('overlay_template_id', $template->id)->pluck('key')->sort()->values()->all();
    expect($keys)->toBe(['toggle_hud', 'toggle_pinlist']);
});

it('connects the checkin integration and provisions its controls on install', function () {
    $user = productUser();

    app(RecipeInstaller::class)->install(chatCheckinRecipe(), $user, 'chat_checkin');

    $integration = ExternalIntegration::where('user_id', $user->id)->where('service', 'checkin')->first();
    expect($integration)->not->toBeNull()
        ->and($integration->enabled)->toBeTrue();

    $provisioned = OverlayControl::where('user_id', $user->id)
        ->where('source', 'checkin')
        ->where('source_managed', true)
        ->pluck('key')
        ->all();
    expect($provisioned)->toContain('checkins_total', 'latest_checkin_place', 'farthest_checkin_this_stream');
});

it('re-enables a checkin integration the streamer had switched off', function () {
    $user = productUser();
    ExternalIntegration::create(['user_id' => $user->id, 'service' => 'checkin', 'enabled' => false]);

    app(RecipeInstaller::class)->install(chatCheckinRecipe(), $user, 'chat_checkin');

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'checkin')->count())->toBe(1)
        ->and(ExternalIntegration::where('user_id', $user->id)->where('service', 'checkin')->value('enabled'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// The pages
// ---------------------------------------------------------------------------

it('shows the product list to a visitor without an account', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->has('products', 2)
            ->where('products.0.slug', 'chat_checkin')
            ->where('products.0.installed', false)
        );
});

it('shows a product page to a visitor without an account', function () {
    $this->get('/products/chat_checkin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/show')
            ->where('product.name', 'Chat Checkin')
            ->where('product.requires_bot', true)
            ->where('product.integrations', ['checkin'])
            ->where('product.overlays.0.name', 'Chat Checkin globe')
            ->where('installed', null)
        );
});

it('404s an unlisted recipe and an unknown slug on the product page', function () {
    $this->get('/products/dice')->assertNotFound();
    $this->get('/products/nope')->assertNotFound();
});

it('sends a visitor to the login page when they try to install', function () {
    $this->post('/products/chat_checkin/install')->assertRedirect();

    expect(RecipeInstance::count())->toBe(0);
});

it('installs the product on POST and shows the page in its installed state', function () {
    $user = productUser();

    $this->actingAs($user)
        ->post('/products/chat_checkin/install')
        ->assertRedirect('/products/chat_checkin')
        ->assertSessionHas('success', 'Chat Checkin is installed.');

    $instance = RecipeInstance::where('user_id', $user->id)->first();
    expect($instance)->not->toBeNull()
        ->and($instance->recipe->slug)->toBe('chat_checkin');

    $this->actingAs($user)
        ->get('/products/chat_checkin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/show')
            ->where('installed.overlays.0.name', 'Chat Checkin globe')
            ->where('installed.subject.key', 'product:'.$instance->id)
            ->has('installed.subject.wires', 8)
        );

    $this->actingAs($user)
        ->get('/products')
        ->assertInertia(fn (Assert $page) => $page->where('products.0.installed', true));
});

it('does not install a second copy when the button is pressed again', function () {
    $user = productUser();

    $this->actingAs($user)->post('/products/chat_checkin/install');
    $this->actingAs($user)->post('/products/chat_checkin/install')->assertRedirect('/products/chat_checkin');

    expect(RecipeInstance::where('user_id', $user->id)->count())->toBe(1)
        ->and(OverlayTemplate::where('owner_id', $user->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// The circuit
// ---------------------------------------------------------------------------

it('reports the human half of the install as missing right after installing', function () {
    $user = productUser(['bot_enabled' => false]);
    $instance = app(RecipeInstaller::class)->install(chatCheckinRecipe(), $user, 'chat_checkin');

    $states = WiringFacts::productSubject($instance)['states'];

    expect($states['product.bot_on'])->toBe(WiringCatalog::MISSING)
        ->and($states['product.bot_hears'])->toBe(WiringCatalog::NOT_APPLICABLE)
        ->and($states['product.integration'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.overlay'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.token'])->toBe(WiringCatalog::MISSING);
});

it('satisfies the token wire with an active token and the bot wire with the toggle', function () {
    $user = productUser(['bot_enabled' => true]);
    $instance = app(RecipeInstaller::class)->install(chatCheckinRecipe(), $user, 'chat_checkin');

    OverlayAccessToken::create([
        'user_id' => $user->id,
        'name' => 'OBS',
        'token_hash' => hash('sha256', str_repeat('a', 64)),
        'token_prefix' => 'aaaaaaaa',
        'is_active' => true,
    ]);

    $states = WiringFacts::productSubject($instance->fresh())['states'];

    expect($states['product.bot_on'])->toBe(WiringCatalog::SATISFIED)
        ->and($states['product.token'])->toBe(WiringCatalog::SATISFIED);
});

it('reports the overlay wire missing once the created overlay is deleted', function () {
    $user = productUser();
    $instance = app(RecipeInstaller::class)->install(chatCheckinRecipe(), $user, 'chat_checkin');

    OverlayTemplate::find($instance->primitive_map['overlays']['globe'])->delete();

    expect(WiringFacts::productSubject($instance->fresh())['states']['product.overlay'])->toBe(WiringCatalog::MISSING);
});

it('reports the integration wire missing once the integration is disabled', function () {
    $user = productUser();
    $instance = app(RecipeInstaller::class)->install(chatCheckinRecipe(), $user, 'chat_checkin');

    ExternalIntegration::where('user_id', $user->id)->where('service', 'checkin')->update(['enabled' => false]);

    expect(WiringFacts::productSubject($instance->fresh())['states']['product.integration'])->toBe(WiringCatalog::MISSING);
});

it('lists installed products on the wiring page and leaves picker recipes off it', function () {
    $user = productUser();
    app(RecipeInstaller::class)->install(chatCheckinRecipe(), $user, 'chat_checkin');

    $catalog = app(RecipeCatalog::class);
    app(RecipeInstaller::class)->install($catalog->sync($catalog->find('dice')), $user, 'main');

    $subjects = WiringFacts::for($user)['products'];

    expect($subjects)->toHaveCount(1)
        ->and($subjects[0]['label'])->toBe('Chat Checkin');
});
