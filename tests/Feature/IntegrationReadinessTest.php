<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\ProductSetup;
use App\Support\WiringCatalog;
use App\Support\WiringFacts;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * Two facts that used to be one.
 *
 * A row existing is not a connection working. An install can create the row;
 * only the streamer can come back from an OAuth screen or paste a verification
 * token. The product circuit counted rows, so it called an unauthorized
 * Streamlabs connection done and skipped the single step between the streamer
 * and a working alert.
 *
 * And a third-party connection is not the product's to take back. It has its
 * own settings page and its own credentials, and it outlives every product the
 * way an overlay token and the bot toggle already do.
 */
function readinessUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'readiness'.fake()->unique()->randomNumber(5)],
    ]);
}

function readinessIntegration(User $user, string $service, array $credentials = []): ExternalIntegration
{
    $integration = ExternalIntegration::create([
        'user_id' => $user->id,
        'service' => $service,
        'enabled' => true,
    ]);

    if ($credentials !== []) {
        $integration->setCredentialsEncrypted($credentials);
        $integration->save();
    }

    return $integration;
}

/**
 * Chat Checkin's manifest with a THIRD-PARTY service in place of its own: the
 * install connects streamlabs, but nothing declares streamlabs as the
 * product's. The slug stays chat-checkin so the overlay document beside the
 * manifest still resolves.
 */
function readinessThirdPartyProduct(): Recipe
{
    $manifest = app(RecipeCatalog::class)->find('chat-checkin');
    $manifest['requires_integrations'] = [];
    $manifest['installs']['integrations'] = ['streamlabs'];
    // A donation product needs no bot, and leaving the bot wires in would put
    // three unrelated steps ahead of the integration in the checklist.
    $manifest['requires_bot'] = false;
    $manifest['version'] = 99;

    return Recipe::create([
        'slug' => $manifest['slug'],
        'version' => $manifest['version'],
        'name' => $manifest['name'],
        'description' => $manifest['description'],
        'author_name' => $manifest['author']['name'],
        'requires_integrations' => $manifest['requires_integrations'],
        'manifest' => $manifest,
        'is_first_party' => true,
    ]);
}

// ---------------------------------------------------------------------------
// Which services need finishing, and which are done on arrival
// ---------------------------------------------------------------------------

it('treats a connection with no credentials as unfinished for the services that need them', function (string $service) {
    $integration = readinessIntegration(readinessUser(), $service);

    expect($integration->isAuthenticated())->toBeFalse();
})->with(['streamlabs', 'kofi', 'bmac', 'fourthwall']);

it('treats a connection as finished once its credentials are there', function (string $service, array $credentials) {
    $integration = readinessIntegration(readinessUser(), $service, $credentials);

    expect($integration->isAuthenticated())->toBeTrue();
})->with([
    ['streamlabs', ['socket_token' => 'sock', 'listener_secret' => 'sec']],
    ['kofi', ['verification_token' => 'tok']],
    ['bmac', ['webhook_secret' => 'sec']],
    ['fourthwall', ['access_token' => 'tok']],
]);

it('needs every required credential, not just one of them', function () {
    // Streamlabs stores both halves in one go, so a row holding only one is a
    // half-written connection the listener could never use.
    $integration = readinessIntegration(readinessUser(), 'streamlabs', ['socket_token' => 'sock']);

    expect($integration->isAuthenticated())->toBeFalse();
});

it('counts a service with nothing to require as ready the moment the row exists', function (string $service) {
    $integration = readinessIntegration(readinessUser(), $service);

    expect($integration->isAuthenticated())->toBeTrue();
})->with(['checkin', 'tower', 'throne', 'gps']);

// ---------------------------------------------------------------------------
// The wire the streamer reads
// ---------------------------------------------------------------------------

it('reports the integration wire missing while an OAuth service is unauthorized', function () {
    $user = readinessUser();

    $instance = app(RecipeInstaller::class)->install(readinessThirdPartyProduct(), $user, 'main');

    // The install created and enabled the row. Nothing has been authorized.
    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'streamlabs')->value('enabled'))->toBeTrue()
        ->and(WiringFacts::productSubject($instance)['states']['product.integration'])->toBe(WiringCatalog::MISSING);
});

it('satisfies the integration wire once the streamer comes back authorized', function () {
    $user = readinessUser();
    $instance = app(RecipeInstaller::class)->install(readinessThirdPartyProduct(), $user, 'main');

    $integration = ExternalIntegration::where('user_id', $user->id)->where('service', 'streamlabs')->first();
    $integration->setCredentialsEncrypted(['socket_token' => 'sock', 'listener_secret' => 'sec']);
    $integration->save();

    expect(WiringFacts::productSubject($instance->fresh())['states']['product.integration'])->toBe(WiringCatalog::SATISFIED);
});

it('still reports the wire satisfied for a product whose own channel needs nothing', function () {
    $user = readinessUser();
    $catalog = app(RecipeCatalog::class);

    $instance = app(RecipeInstaller::class)->install($catalog->sync($catalog->find('chat-checkin')), $user, 'chat_checkin');

    expect(WiringFacts::productSubject($instance)['states']['product.integration'])->toBe(WiringCatalog::SATISFIED);
});

// ---------------------------------------------------------------------------
// Croutons: what an uninstall may and may not take
// ---------------------------------------------------------------------------

it('leaves a third-party connection and its controls alone on uninstall, even having created it', function () {
    $user = readinessUser();
    $instance = app(RecipeInstaller::class)->install(readinessThirdPartyProduct(), $user, 'main');

    // The install made the row, which under the old rule was licence to take it.
    expect($instance->primitive_map['integrations']['streamlabs']['created'])->toBeTrue();

    // Then the streamer authorized it and has been using it.
    $integration = ExternalIntegration::where('user_id', $user->id)->where('service', 'streamlabs')->first();
    $integration->setCredentialsEncrypted(['socket_token' => 'sock', 'listener_secret' => 'sec']);
    $integration->save();

    app(RecipeInstaller::class)->uninstall($instance);

    $survivor = ExternalIntegration::where('user_id', $user->id)->where('service', 'streamlabs')->first();
    expect($survivor)->not->toBeNull()
        ->and($survivor->getCredentialsDecrypted()['socket_token'])->toBe('sock')
        ->and(OverlayControl::where('user_id', $user->id)->where('source', 'streamlabs')->exists())->toBeTrue();
});

it('never offers to remove a third-party connection in the confirmation', function () {
    $user = readinessUser();
    $instance = app(RecipeInstaller::class)->install(readinessThirdPartyProduct(), $user, 'main');

    expect(app(RecipeInstaller::class)->removals($instance))
        ->not->toContain('The streamlabs integration connection and its controls');
});

it('still takes the product its own channel back', function () {
    $user = readinessUser();
    $catalog = app(RecipeCatalog::class);
    $instance = app(RecipeInstaller::class)->install($catalog->sync($catalog->find('chat-checkin')), $user, 'chat_checkin');

    expect(app(RecipeInstaller::class)->removals($instance))
        ->toContain('The checkin integration connection and its controls');

    app(RecipeInstaller::class)->uninstall($instance);

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'checkin')->exists())->toBeFalse();
});

it('points the banner at the one service still unfinished', function () {
    $user = readinessUser();
    app(RecipeInstaller::class)->install(readinessThirdPartyProduct(), $user, 'main');
    ProductSetup::start($user, 'chat-checkin');

    $banner = ProductSetup::banner($user->fresh(), app(RecipeCatalog::class));

    // One wire however many services a product declares, so the element it
    // names has to be worked out per install.
    expect($banner['next']['target'])->toBe('integration-streamlabs')
        ->and($banner['next']['url'])->toBe(route('settings.integrations.index').'#el-integration-streamlabs')
        ->and($banner['next']['todo'])->toBe('finish connecting its integration');
});
