<?php

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipes\RecipeInstaller;
use App\Services\Recipes\RecipeManifestValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/**
 * The two ingredients a recipe was missing: which event makes an installed
 * alert fire, and which installed static overlays it fires on. Before these,
 * an install left the streamer to set both by hand on the Triggers and
 * Targeting tabs - steps 9 and 10 of the eleven.
 *
 * The installer reads overlay documents off disk from resources/recipes/<slug>/,
 * and no shipped product has an alert overlay yet, so these tests write a
 * fixture pair there and take it away again. Only the .md files are written,
 * never a manifest.json: RecipeCatalog globs for manifests, so it never sees
 * the fixture and no other test's view of the catalogue changes.
 */
const ALERT_WIRING_SLUG = 'alert_wiring_fixture';

function alertWiringDir(): string
{
    return RecipeInstaller::directoryFor(ALERT_WIRING_SLUG);
}

function alertWiringDocument(string $name, string $type): string
{
    $article = $type === 'alert' ? 'alert' : 'static';

    return <<<MD
        ---
        name: "{$name}"
        type: {$type}
        author: Overlabels
        ---

        # {$name}

        A fixture overlay used by the alert wiring tests.

        An Overlabels **{$article} overlay** by Overlabels.

        ## Source

        ### `html`

        The markup.

        ```html
        <div class="fixture">[[[channel_name]]]</div>
        ```

        MD;
}

beforeEach(function () {
    $dir = alertWiringDir();
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir.DIRECTORY_SEPARATOR.'fixture_alert.md', alertWiringDocument('Fixture tip alert', 'alert'));
    file_put_contents($dir.DIRECTORY_SEPARATOR.'fixture_stage.md', alertWiringDocument('Fixture stage', 'static'));
});

afterEach(function () {
    $dir = alertWiringDir();
    foreach (glob($dir.DIRECTORY_SEPARATOR.'*.md') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir($dir)) {
        rmdir($dir);
    }
});

/**
 * A manifest that installs the fixture overlays plus whatever wiring the case
 * is about. Everything else is the smallest thing the schema accepts.
 *
 * @param  array<string, mixed>  $wiring
 * @return array<string, mixed>
 */
function alertWiringManifest(array $wiring = []): array
{
    return [
        'recipe_format_version' => 1,
        'slug' => ALERT_WIRING_SLUG,
        'name' => 'Alert wiring fixture',
        'version' => 1,
        'description' => 'Fixture product for the alert trigger and targeting ingredients.',
        'author' => ['name' => 'Overlabels'],
        'installs' => array_merge([
            'overlays' => [
                ['ref' => 'tip_alert', 'file' => 'fixture_alert.md'],
                ['ref' => 'stage', 'file' => 'fixture_stage.md'],
            ],
        ], $wiring),
    ];
}

/**
 * @param  array<string, mixed>  $wiring
 */
function alertWiringRecipe(array $wiring = []): Recipe
{
    $manifest = alertWiringManifest($wiring);

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

function alertWiringUser(): User
{
    return User::factory()->create();
}

/**
 * @param  list<array{pointer: string, message: string}>  $errors
 */
function alertWiringErrorAt(array $errors, string $pointer): ?array
{
    foreach ($errors as $error) {
        if ($error['pointer'] === $pointer) {
            return $error;
        }
    }

    return null;
}

// ---------------------------------------------------------------------------
// The manifest
// ---------------------------------------------------------------------------

it('accepts a manifest that wires a trigger and a target', function () {
    $result = (new RecipeManifestValidator)->validate(alertWiringManifest([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'donation', 'duration_ms' => 8000],
        ],
        'alert_targets' => [
            ['alert' => 'tip_alert', 'overlays' => ['stage']],
        ],
    ]));

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBe([]);
});

it('flags a trigger on an overlay the manifest does not install', function () {
    $result = (new RecipeManifestValidator)->validate(alertWiringManifest([
        'alert_triggers' => [
            ['overlay' => 'nope', 'service' => 'streamlabs', 'event_type' => 'donation'],
        ],
    ]));

    $error = alertWiringErrorAt($result['errors'], '/installs/alert_triggers/0/overlay');
    expect($result['valid'])->toBeFalse()
        ->and($error)->not->toBeNull()
        ->and($error['message'])->toContain('nope');
});

it('flags a Twitch event type the platform does not have', function () {
    $result = (new RecipeManifestValidator)->validate(alertWiringManifest([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'twitch', 'event_type' => 'channel.applause'],
        ],
    ]));

    expect($result['valid'])->toBeFalse()
        ->and(alertWiringErrorAt($result['errors'], '/installs/alert_triggers/0/event_type'))->not->toBeNull();
});

it('flags an event type the named service does not serve', function () {
    // Ko-fi has a subscription event; Streamlabs only ever has donation.
    $result = (new RecipeManifestValidator)->validate(alertWiringManifest([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'subscription'],
        ],
    ]));

    expect($result['valid'])->toBeFalse()
        ->and(alertWiringErrorAt($result['errors'], '/installs/alert_triggers/0/event_type'))->not->toBeNull();
});

it('flags a service that is not registered', function () {
    $result = (new RecipeManifestValidator)->validate(alertWiringManifest([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamelements', 'event_type' => 'donation'],
        ],
    ]));

    expect($result['valid'])->toBeFalse()
        ->and(alertWiringErrorAt($result['errors'], '/installs/alert_triggers/0/service'))->not->toBeNull();
});

it('flags two triggers claiming the same event', function () {
    $result = (new RecipeManifestValidator)->validate(alertWiringManifest([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'donation'],
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'donation'],
        ],
    ]));

    expect($result['valid'])->toBeFalse()
        ->and(alertWiringErrorAt($result['errors'], '/installs/alert_triggers/1/event_type'))->not->toBeNull();
});

it('flags a target naming an overlay the manifest does not install', function () {
    $result = (new RecipeManifestValidator)->validate(alertWiringManifest([
        'alert_targets' => [
            ['alert' => 'tip_alert', 'overlays' => ['ghost']],
        ],
    ]));

    expect($result['valid'])->toBeFalse()
        ->and(alertWiringErrorAt($result['errors'], '/installs/alert_targets/0/overlays/0'))->not->toBeNull();
});

it('flags a duplicate overlay ref, now that refs decide where a trigger lands', function () {
    $manifest = alertWiringManifest();
    $manifest['installs']['overlays'][] = ['ref' => 'stage', 'file' => 'fixture_alert.md'];

    $result = (new RecipeManifestValidator)->validate($manifest);

    expect($result['valid'])->toBeFalse()
        ->and(alertWiringErrorAt($result['errors'], '/installs/overlays/2/ref'))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// The install
// ---------------------------------------------------------------------------

it('writes an external mapping row pointing at the installed alert', function () {
    $user = alertWiringUser();
    $recipe = alertWiringRecipe([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'donation', 'duration_ms' => 8000],
        ],
    ]);

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    $mapping = ExternalEventTemplateMapping::where('user_id', $user->id)->first();
    expect($mapping)->not->toBeNull()
        ->and($mapping->service)->toBe('streamlabs')
        ->and($mapping->event_type)->toBe('donation')
        ->and($mapping->overlay_template_id)->toBe($instance->primitive_map['overlays']['tip_alert'])
        ->and($mapping->enabled)->toBeTrue()
        ->and($mapping->duration_ms)->toBe(8000)
        ->and($mapping->condition_type)->toBeNull();
});

it('writes a Twitch mapping row when the service is twitch', function () {
    $user = alertWiringUser();
    $recipe = alertWiringRecipe([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'twitch', 'event_type' => 'channel.cheer'],
        ],
    ]);

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    $mapping = EventTemplateMapping::where('user_id', $user->id)->first();
    expect($mapping)->not->toBeNull()
        ->and($mapping->event_type)->toBe('channel.cheer')
        ->and($mapping->template_id)->toBe($instance->primitive_map['overlays']['tip_alert'])
        // Omitted in the manifest, so it lands on the column default.
        ->and($mapping->duration_ms)->toBe(5000)
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->count())->toBe(0);
});

it('records both kinds of trigger in the primitive map', function () {
    $user = alertWiringUser();
    $recipe = alertWiringRecipe([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'twitch', 'event_type' => 'channel.follow'],
            ['overlay' => 'tip_alert', 'service' => 'kofi', 'event_type' => 'donation'],
        ],
    ]);

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    expect($instance->primitive_map['alert_triggers']['twitch'])->toHaveKey('channel.follow')
        ->and($instance->primitive_map['alert_triggers']['external'])->toHaveKey('kofi:donation');
});

it('leaves the map alone for a recipe that declares no triggers', function () {
    $instance = app(RecipeInstaller::class)->install(alertWiringRecipe(), alertWiringUser(), 'main');

    expect($instance->primitive_map)->not->toHaveKey('alert_triggers');
});

it('targets the alert at the static overlay it installed alongside it', function () {
    $user = alertWiringUser();
    $recipe = alertWiringRecipe([
        'alert_targets' => [
            ['alert' => 'tip_alert', 'overlays' => ['stage']],
        ],
    ]);

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    $alert = OverlayTemplate::find($instance->primitive_map['overlays']['tip_alert']);
    expect($alert->targetStaticOverlays()->pluck('overlay_templates.id')->all())
        ->toBe([$instance->primitive_map['overlays']['stage']]);
});

it('leaves the pivot empty when no targeting is declared, so the alert fires everywhere', function () {
    $user = alertWiringUser();

    $instance = app(RecipeInstaller::class)->install(alertWiringRecipe(), $user, 'main');

    $alert = OverlayTemplate::find($instance->primitive_map['overlays']['tip_alert']);
    expect($alert->targetStaticOverlays()->count())->toBe(0);
});

it('refuses a trigger pointed at a static overlay', function () {
    $recipe = alertWiringRecipe([
        'alert_triggers' => [
            ['overlay' => 'stage', 'service' => 'streamlabs', 'event_type' => 'donation'],
        ],
    ]);

    expect(fn () => app(RecipeInstaller::class)->install($recipe, alertWiringUser(), 'main'))
        ->toThrow(RuntimeException::class, 'not alert');
});

it('refuses targeting that points at an alert instead of a static overlay', function () {
    $recipe = alertWiringRecipe([
        'alert_targets' => [
            ['alert' => 'tip_alert', 'overlays' => ['tip_alert']],
        ],
    ]);

    expect(fn () => app(RecipeInstaller::class)->install($recipe, alertWiringUser(), 'main'))
        ->toThrow(RuntimeException::class, 'not static');
});

// ---------------------------------------------------------------------------
// Refusing to take an event the streamer already uses
// ---------------------------------------------------------------------------

it('refuses to claim an external event the streamer already has an alert on, and creates nothing', function () {
    $user = alertWiringUser();
    $mine = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'name' => 'My tip alert']);
    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'overlay_template_id' => $mine->id,
        'service' => 'streamlabs',
        'event_type' => 'donation',
        'enabled' => true,
    ]);

    $recipe = alertWiringRecipe([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'donation'],
        ],
    ]);

    expect(fn () => app(RecipeInstaller::class)->install($recipe, $user, 'main'))
        ->toThrow(RuntimeException::class, 'My tip alert');

    // The refusal happens before the transaction opens, so not even the
    // overlays the recipe would have created exist.
    expect(OverlayTemplate::where('owner_id', $user->id)->count())->toBe(1)
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->count())->toBe(1);
});

it('refuses to claim a Twitch event the streamer already has an alert on', function () {
    $user = alertWiringUser();
    $mine = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'name' => 'My follow alert']);
    EventTemplateMapping::create([
        'user_id' => $user->id,
        'template_id' => $mine->id,
        'event_type' => 'channel.follow',
        'enabled' => true,
    ]);

    $recipe = alertWiringRecipe([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'twitch', 'event_type' => 'channel.follow'],
        ],
    ]);

    expect(fn () => app(RecipeInstaller::class)->install($recipe, $user, 'main'))
        ->toThrow(RuntimeException::class, 'My follow alert');
});

it('ignores another account holding the same event', function () {
    $other = alertWiringUser();
    $theirs = OverlayTemplate::factory()->create(['owner_id' => $other->id, 'type' => 'alert']);
    ExternalEventTemplateMapping::create([
        'user_id' => $other->id,
        'overlay_template_id' => $theirs->id,
        'service' => 'streamlabs',
        'event_type' => 'donation',
        'enabled' => true,
    ]);

    $recipe = alertWiringRecipe([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'donation'],
        ],
    ]);

    $instance = app(RecipeInstaller::class)->install($recipe, alertWiringUser(), 'main');

    expect($instance->primitive_map['alert_triggers']['external'])->toHaveKey('streamlabs:donation');
});

it('installs over a mapping whose overlay has already been deleted', function () {
    $user = alertWiringUser();
    $mine = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert']);
    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'overlay_template_id' => $mine->id,
        'service' => 'streamlabs',
        'event_type' => 'donation',
        'enabled' => true,
    ]);
    // nullOnDelete: the row outlives the overlay, pointing at nothing. It can
    // never fire, so refusing on it would name an alert that no longer exists.
    $mine->delete();

    $recipe = alertWiringRecipe([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'donation'],
        ],
    ]);

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    expect($instance->primitive_map['alert_triggers']['external'])->toHaveKey('streamlabs:donation');
});

// ---------------------------------------------------------------------------
// Uninstall
// ---------------------------------------------------------------------------

it('names the trigger in the uninstall confirmation', function () {
    $user = alertWiringUser();
    $recipe = alertWiringRecipe([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'donation'],
            ['overlay' => 'tip_alert', 'service' => 'twitch', 'event_type' => 'channel.cheer'],
        ],
    ]);

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    expect(app(RecipeInstaller::class)->removals($instance))
        ->toContain('The Streamlabs Donation alert trigger')
        ->toContain('The Bits Cheer alert trigger');
});

it('takes both mapping rows away on uninstall, leaving no orphan behind', function () {
    $user = alertWiringUser();
    $recipe = alertWiringRecipe([
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'donation'],
            ['overlay' => 'tip_alert', 'service' => 'twitch', 'event_type' => 'channel.cheer'],
        ],
        'alert_targets' => [
            ['alert' => 'tip_alert', 'overlays' => ['stage']],
        ],
    ]);

    $instance = app(RecipeInstaller::class)->install($recipe, $user, 'main');
    $alertId = $instance->primitive_map['overlays']['tip_alert'];

    app(RecipeInstaller::class)->uninstall($instance);

    expect(ExternalEventTemplateMapping::where('user_id', $user->id)->count())->toBe(0)
        ->and(EventTemplateMapping::where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('alert_template_static_overlays')->where('alert_template_id', $alertId)->count())->toBe(0);
});

it('reinstalls cleanly after an uninstall', function () {
    $user = alertWiringUser();
    $wiring = [
        'alert_triggers' => [
            ['overlay' => 'tip_alert', 'service' => 'streamlabs', 'event_type' => 'donation'],
        ],
    ];

    $recipe = alertWiringRecipe($wiring);
    $first = app(RecipeInstaller::class)->install($recipe, $user, 'main');
    app(RecipeInstaller::class)->uninstall($first);

    $second = app(RecipeInstaller::class)->install($recipe, $user, 'main');

    expect($second->primitive_map['alert_triggers']['external'])->toHaveKey('streamlabs:donation')
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->count())->toBe(1);
});
