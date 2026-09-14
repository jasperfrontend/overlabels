<?php

use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeIngredients;
use App\Services\Recipes\RecipeInstaller;
use App\Services\Recipes\RecipeManifestValidator;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;

uses(DatabaseTransactions::class);

/**
 * A recipe is a form: each ingredient is one question with fixed choices and
 * a default, and the answer is written wherever the manifest or an overlay
 * document says {{key}}. The first ingredient is "which donation service",
 * which feeds three places at once - the integration to connect, the alert
 * trigger's service, and the c:<service>: namespace in the overlay's tags.
 *
 * Like RecipeAlertWiringTest, the fixture overlays are written to
 * resources/recipes/<slug>/ and removed again; no manifest.json is written,
 * so RecipeCatalog never sees the fixture.
 */
const INGREDIENTS_SLUG = 'ingredients_fixture';

function ingredientsDir(): string
{
    return RecipeInstaller::directoryFor(INGREDIENTS_SLUG);
}

function ingredientsDocument(string $name, string $type, string $html): string
{
    $article = $type === 'alert' ? 'alert' : 'static';

    return <<<MD
        ---
        name: "{$name}"
        type: {$type}
        author: Overlabels
        ---

        # {$name}

        A fixture overlay used by the ingredients tests.

        An Overlabels **{$article} overlay** by Overlabels.

        ## Source

        ### `html`

        The markup.

        ```html
        {$html}
        ```

        MD;
}

function ingredientsWriteDocs(string $stageHtml = '<p>[[[c:{{service}}:donations_received]]] tips, [[[c:{{service}}:total_received]]] total</p>'): void
{
    $dir = ingredientsDir();
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir.DIRECTORY_SEPARATOR.'fixture_alert.md', ingredientsDocument('Fixture tip alert', 'alert', '<p>[[[event.from_name]]] tipped</p>'));
    file_put_contents($dir.DIRECTORY_SEPARATOR.'fixture_stage.md', ingredientsDocument('Fixture stage', 'static', $stageHtml));
}

beforeEach(fn () => ingredientsWriteDocs());

afterEach(function () {
    $dir = ingredientsDir();
    foreach (glob($dir.DIRECTORY_SEPARATOR.'*.md') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir($dir)) {
        rmdir($dir);
    }
});

/**
 * The reference shape: one question, the answer written into the
 * integration, the trigger and (via the documents) the stage's tags.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ingredientsManifest(array $overrides = []): array
{
    return array_replace_recursive([
        'recipe_format_version' => 1,
        'slug' => INGREDIENTS_SLUG,
        'name' => 'Ingredients fixture',
        'version' => 1,
        'description' => 'Fixture product for the ingredient tests.',
        'author' => ['name' => 'Overlabels'],
        'ingredients' => [
            [
                'key' => 'service',
                'question' => 'Where do your donations come in?',
                'choices' => [
                    ['value' => 'streamlabs', 'label' => 'Streamlabs'],
                    ['value' => 'kofi', 'label' => 'Ko-fi'],
                ],
                'default' => 'streamlabs',
            ],
        ],
        'installs' => [
            'overlays' => [
                ['ref' => 'tip_alert', 'file' => 'fixture_alert.md'],
                ['ref' => 'stage', 'file' => 'fixture_stage.md'],
            ],
            'integrations' => ['{{service}}'],
            'alert_triggers' => [
                ['overlay' => 'tip_alert', 'service' => '{{service}}', 'event_type' => 'donation'],
            ],
            'alert_targets' => [
                ['alert' => 'tip_alert', 'overlays' => ['stage']],
            ],
        ],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function ingredientsRecipe(array $overrides = []): Recipe
{
    $manifest = ingredientsManifest($overrides);

    return Recipe::create([
        'slug' => $manifest['slug'],
        'version' => $manifest['version'],
        'name' => $manifest['name'],
        'description' => $manifest['description'],
        'author_name' => $manifest['author']['name'],
        'manifest' => $manifest,
        'is_first_party' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array{valid: bool, errors: list<array{pointer: string, message: string}>}
 */
function ingredientsValidate(array $overrides = []): array
{
    return (new RecipeManifestValidator)->validate(ingredientsManifest($overrides), ingredientsDir());
}

/**
 * @param  list<array{pointer: string, message: string}>  $errors
 * @return list<string>
 */
function ingredientsMessagesAt(array $errors, string $pointer): array
{
    return array_values(array_map(
        fn (array $e) => $e['message'],
        array_filter($errors, fn (array $e) => $e['pointer'] === $pointer),
    ));
}

function ingredientsInstall(User $user, array $answers = [], array $overrides = []): RecipeInstance
{
    return app(RecipeInstaller::class)->install(ingredientsRecipe($overrides), $user, 'main', null, $answers);
}

// ---------------------------------------------------------------------------
// RecipeIngredients: the pure part
// ---------------------------------------------------------------------------

it('fills placeholders in text and leaves unknown ones as they stand', function () {
    $filled = RecipeIngredients::fill('[[[c:{{service}}:total]]] and {{other}} and {{ service }}', ['service' => 'kofi']);

    expect($filled)->toBe('[[[c:kofi:total]]] and {{other}} and {{ service }}');
});

it('resolves every string leaf of a manifest', function () {
    $resolved = RecipeIngredients::resolve(ingredientsManifest(), ['service' => 'kofi']);

    expect($resolved['installs']['integrations'])->toBe(['kofi'])
        ->and($resolved['installs']['alert_triggers'][0]['service'])->toBe('kofi')
        ->and($resolved['ingredients'][0]['key'])->toBe('service');
});

it('takes the default for an unanswered question', function () {
    expect(RecipeIngredients::answers(ingredientsManifest(), []))->toBe(['service' => 'streamlabs']);
});

it('takes a given answer that is one of the choices', function () {
    expect(RecipeIngredients::answers(ingredientsManifest(), ['service' => 'kofi']))->toBe(['service' => 'kofi']);
});

it('refuses an answer that is not one of the choices', function () {
    RecipeIngredients::answers(ingredientsManifest(), ['service' => 'paypal']);
})->throws(RuntimeException::class, "'paypal' is not one of the choices");

it('refuses an answer to a question the recipe does not ask', function () {
    RecipeIngredients::answers(ingredientsManifest(), ['colour' => 'red']);
})->throws(RuntimeException::class, "does not ask 'colour'");

it('enumerates every combination of choices', function () {
    $manifest = ingredientsManifest([
        'ingredients' => [
            1 => [
                'key' => 'size',
                'question' => 'How big?',
                'choices' => [['value' => 'small', 'label' => 'Small'], ['value' => 'large', 'label' => 'Large']],
                'default' => 'small',
            ],
        ],
    ]);

    expect(RecipeIngredients::combinations($manifest))->toBe([
        ['service' => 'streamlabs', 'size' => 'small'],
        ['service' => 'streamlabs', 'size' => 'large'],
        ['service' => 'kofi', 'size' => 'small'],
        ['service' => 'kofi', 'size' => 'large'],
    ]);
});

it('admits exactly one empty combination for a manifest with no ingredients', function () {
    expect(RecipeIngredients::combinations(['slug' => 'x']))->toBe([[]]);
});

// ---------------------------------------------------------------------------
// The validator
// ---------------------------------------------------------------------------

it('accepts a manifest whose ingredient feeds an integration, a trigger and an overlay document', function () {
    $result = ingredientsValidate();

    expect($result['errors'])->toBe([])
        ->and($result['valid'])->toBeTrue();
});

it('flags a default that is not one of the choices', function () {
    $result = ingredientsValidate(['ingredients' => [0 => ['default' => 'paypal']]]);

    expect($result['valid'])->toBeFalse()
        ->and(ingredientsMessagesAt($result['errors'], '/ingredients/0/default'))->toContain('Default "paypal" is not one of the choices.');
});

it('flags duplicate ingredient keys', function () {
    $result = ingredientsValidate([
        'ingredients' => [
            1 => [
                'key' => 'service',
                'question' => 'Again?',
                'choices' => [['value' => 'kofi', 'label' => 'Ko-fi'], ['value' => 'throne', 'label' => 'Throne']],
                'default' => 'kofi',
            ],
        ],
    ]);

    expect($result['valid'])->toBeFalse()
        ->and(ingredientsMessagesAt($result['errors'], '/ingredients/1/key'))->toContain('Duplicate ingredient key "service".');
});

it('flags a duplicate choice value', function () {
    $result = ingredientsValidate(['ingredients' => [0 => ['choices' => [1 => ['value' => 'streamlabs', 'label' => 'Twice']]]]]);

    expect($result['valid'])->toBeFalse()
        ->and(ingredientsMessagesAt($result['errors'], '/ingredients/0/choices/1/value'))->toContain('Duplicate choice "streamlabs".');
});

it('flags a placeholder in the manifest that names no ingredient', function () {
    $result = ingredientsValidate(['installs' => ['integrations' => ['{{provider}}']]]);

    expect($result['valid'])->toBeFalse()
        ->and(ingredientsMessagesAt($result['errors'], '/installs/integrations/0'))->toContain('Placeholder {{provider}} names no ingredient.');
});

it('flags a placeholder in an overlay document that names no ingredient', function () {
    ingredientsWriteDocs('<p>[[[c:{{provider}}:total_received]]]</p>');

    $result = ingredientsValidate();

    expect($result['valid'])->toBeFalse()
        ->and(ingredientsMessagesAt($result['errors'], '/installs/overlays/1/file'))
        ->toContain('fixture_stage.md says {{provider}}, which names no ingredient.');
});

it('flags an ingredient nothing uses', function () {
    ingredientsWriteDocs('<p>[[[channel_name]]]</p>');

    $result = ingredientsValidate(['installs' => [
        'integrations' => ['kofi'],
        'alert_triggers' => [0 => ['service' => 'kofi']],
    ]]);

    expect($result['valid'])->toBeFalse()
        ->and(ingredientsMessagesAt($result['errors'], '/ingredients/0/key'))->toContain('Ingredient "service" is asked but nothing uses its answer.');
});

it('does not judge use when it is not told where the documents are', function () {
    ingredientsWriteDocs('<p>[[[channel_name]]]</p>');

    $result = (new RecipeManifestValidator)->validate(ingredientsManifest(['installs' => [
        'integrations' => ['kofi'],
        'alert_triggers' => [0 => ['service' => 'kofi']],
    ]]));

    expect($result['valid'])->toBeTrue();
});

it('flags a choice whose service does not have the trigger event type', function () {
    // Ko-fi serves subscription; Streamlabs never has.
    $result = ingredientsValidate(['installs' => ['alert_triggers' => [0 => ['event_type' => 'subscription']]]]);

    expect($result['valid'])->toBeFalse()
        ->and(ingredientsMessagesAt($result['errors'], '/installs/alert_triggers/0/event_type'))
        ->toContain('With service = streamlabs: service "streamlabs" has no event type "subscription".')
        ->and(ingredientsMessagesAt($result['errors'], '/installs/alert_triggers/0/event_type'))
        ->not->toContain('With service = kofi: service "kofi" has no event type "subscription".');
});

it('flags a choice that is not a registered service', function () {
    $result = ingredientsValidate(['ingredients' => [0 => ['choices' => [1 => ['value' => 'paypal', 'label' => 'PayPal']]]]]);

    expect($result['valid'])->toBeFalse()
        ->and(ingredientsMessagesAt($result['errors'], '/installs/integrations/0'))->toContain('With service = paypal: unknown external service "paypal".')
        ->and(ingredientsMessagesAt($result['errors'], '/installs/alert_triggers/0/service'))->toContain('With service = paypal: unknown external service "paypal".');
});

it('flags a choice whose service does not provision a control the overlay reads', function () {
    // Throne provisions latest_item_name; Ko-fi does not.
    ingredientsWriteDocs('<p>[[[c:{{service}}:latest_item_name]]] [[[if:c:{{service}}:total_received > 0]]]yes[[[endif]]]</p>');

    $result = ingredientsValidate(['ingredients' => [0 => ['choices' => [0 => ['value' => 'throne', 'label' => 'Throne']], 'default' => 'throne']]]);

    $messages = ingredientsMessagesAt($result['errors'], '/installs/overlays/1/file');
    expect($result['valid'])->toBeFalse()
        ->and($messages)->toContain('With service = kofi: fixture_stage.md reads [[[c:kofi:latest_item_name]]], which Ko-fi does not provision.')
        ->and($messages)->not->toContain('With service = throne: fixture_stage.md reads [[[c:throne:latest_item_name]]], which Throne does not provision.');
});

it('checks a control read only inside a condition', function () {
    ingredientsWriteDocs('<p>[[[if:c:{{service}}:latest_item_name]]]gift[[[endif]]]</p>');

    $result = ingredientsValidate();

    expect(ingredientsMessagesAt($result['errors'], '/installs/overlays/1/file'))
        ->toContain('With service = kofi: fixture_stage.md reads [[[c:kofi:latest_item_name]]], which Ko-fi does not provision.');
});

it('still refuses an unregistered literal service beside a placeholder', function () {
    $result = ingredientsValidate(['installs' => ['alert_triggers' => [1 => ['overlay' => 'tip_alert', 'service' => 'paypal', 'event_type' => 'donation']]]]);

    expect($result['valid'])->toBeFalse()
        ->and(ingredientsMessagesAt($result['errors'], '/installs/alert_triggers/1/service'))->toContain('Unknown external service "paypal".');
});

// ---------------------------------------------------------------------------
// The installer
// ---------------------------------------------------------------------------

it('installs with the default when no answer is given', function () {
    $user = User::factory()->create();

    $instance = ingredientsInstall($user);

    $stage = OverlayTemplate::find($instance->primitive_map['overlays']['stage']);
    expect($instance->ingredients)->toBe(['service' => 'streamlabs'])
        ->and(ExternalIntegration::where('user_id', $user->id)->where('service', 'streamlabs')->exists())->toBeTrue()
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->value('service'))->toBe('streamlabs')
        ->and($stage->html)->toContain('[[[c:streamlabs:donations_received]]]')
        ->and($stage->html)->not->toContain('{{');
});

it('installs with the given answer written into the integration, the trigger and the overlay', function () {
    $user = User::factory()->create();

    $instance = ingredientsInstall($user, ['service' => 'kofi']);

    $stage = OverlayTemplate::find($instance->primitive_map['overlays']['stage']);
    expect($instance->ingredients)->toBe(['service' => 'kofi'])
        ->and(ExternalIntegration::where('user_id', $user->id)->pluck('service')->all())->toBe(['kofi'])
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->value('service'))->toBe('kofi')
        ->and($stage->html)->toContain('[[[c:kofi:donations_received]]]')
        ->and($stage->html)->toContain('[[[c:kofi:total_received]]]')
        ->and($stage->template_tags)->toContain('c:kofi:donations_received');
});

it('leaves the document on disk untouched', function () {
    ingredientsInstall(User::factory()->create(), ['service' => 'kofi']);

    expect(file_get_contents(ingredientsDir().DIRECTORY_SEPARATOR.'fixture_stage.md'))->toContain('{{service}}');
});

it('refuses an answer that is not a choice and creates nothing', function () {
    $user = User::factory()->create();

    try {
        ingredientsInstall($user, ['service' => 'paypal']);
        $this->fail('Expected the install to refuse.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain("'paypal' is not one of the choices");
    }

    expect(RecipeInstance::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(OverlayTemplate::where('owner_id', $user->id)->exists())->toBeFalse()
        ->and(ExternalIntegration::where('user_id', $user->id)->exists())->toBeFalse();
});

it('refuses an install answering a question the recipe does not ask', function () {
    ingredientsInstall(User::factory()->create(), ['colour' => 'red']);
})->throws(RuntimeException::class, "does not ask 'colour'");

it('resolves the instance manifest with its answers', function () {
    $instance = ingredientsInstall(User::factory()->create(), ['service' => 'kofi']);

    $resolved = $instance->fresh('recipe')->resolvedManifest();
    expect($resolved['installs']['integrations'])->toBe(['kofi'])
        ->and($resolved['installs']['alert_triggers'][0]['service'])->toBe('kofi')
        ->and($instance->recipe->manifest['installs']['integrations'])->toBe(['{{service}}']);
});

it('reads the chosen service on the product integration wire', function () {
    $user = User::factory()->create();
    $instance = ingredientsInstall($user, ['service' => 'kofi']);

    $wireState = fn (): string => WiringFacts::productSubject($instance->fresh(['recipe', 'user']))['states']['product.integration'];

    // A Ko-fi row with no verification token is connected but not working.
    expect($wireState())->toBe(WiringCatalog::MISSING);

    // Authorizing STREAMLABS, the default this install did not pick, changes nothing.
    ExternalIntegration::create([
        'user_id' => $user->id,
        'service' => 'streamlabs',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['socket_token' => 's', 'listener_secret' => 'l'])),
    ]);
    expect($wireState())->toBe(WiringCatalog::MISSING);

    // Authorizing the chosen one satisfies the wire.
    ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')
        ->update(['credentials' => Crypt::encryptString(json_encode(['verification_token' => 'v']))]);
    expect($wireState())->toBe(WiringCatalog::SATISFIED);
});

it('uninstalls the trigger and the overlays and leaves the chosen integration', function () {
    $user = User::factory()->create();
    $instance = ingredientsInstall($user, ['service' => 'kofi']);

    app(RecipeInstaller::class)->uninstall($instance->fresh(['recipe', 'user']));

    expect(ExternalEventTemplateMapping::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(OverlayTemplate::where('owner_id', $user->id)->exists())->toBeFalse()
        ->and(ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')->exists())->toBeTrue();
});

it('installs a recipe with no ingredients exactly as before', function () {
    $user = User::factory()->create();
    ingredientsWriteDocs('<p>[[[c:kofi:donations_received]]]</p>');

    $manifest = ingredientsManifest(['installs' => ['integrations' => ['kofi'], 'alert_triggers' => [0 => ['service' => 'kofi']]]]);
    unset($manifest['ingredients']);
    $recipe = Recipe::create([
        'slug' => $manifest['slug'],
        'version' => $manifest['version'],
        'name' => $manifest['name'],
        'description' => $manifest['description'],
        'author_name' => $manifest['author']['name'],
        'manifest' => $manifest,
        'is_first_party' => true,
    ]);

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    // jsonb hands keys back in its own order, so equality, not identity.
    expect($instance->ingredients)->toBe([])
        ->and($instance->resolvedManifest())->toEqual($manifest)
        ->and(ExternalIntegration::where('user_id', $user->id)->pluck('service')->all())->toBe(['kofi']);
});
