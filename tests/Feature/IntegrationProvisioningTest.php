<?php

use App\Http\Controllers\Settings\DonationIntegrationController;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\External\ExternalServiceRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(DatabaseTransactions::class);

/**
 * Connecting a donation integration must provision its controls.
 *
 * This is the test that did not exist while three of the five integrations
 * quietly failed to provision anything. Ko-fi, Buy Me a Coffee and Throne each
 * gave you a working webhook, verified signatures and events landing in the
 * table, with no controls to read them from - so a hand-typed
 * `[[[c:throne:latest_donor_name]]]` resolved to nothing at all, because the
 * render payload is built from control rows that were never created.
 *
 * Every assertion here failed for those three before the shared
 * DonationIntegrationController existed. Keep them.
 */

/**
 * Services whose connect flow is a plain authenticated POST. The OAuth pair are
 * covered separately because they need their token exchange faked.
 */
const DIRECT_CONNECT_SERVICES = [
    'kofi' => ['verification_token' => 'test-verification-token'],
    'bmac' => ['webhook_secret' => 'test-webhook-secret'],
    'throne' => [],
];

function connectingUser(): User
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    test()->actingAs($user);

    return $user;
}

function serviceControlKeys(User $user, string $service): array
{
    return OverlayControl::where('user_id', $user->id)
        ->where('source', $service)
        ->where('source_managed', true)
        ->pluck('key')
        ->sort()
        ->values()
        ->all();
}

function expectedControlKeys(string $service): array
{
    return collect(ExternalServiceRegistry::driver($service)->getAutoProvisionedControls())
        ->pluck('key')
        ->sort()
        ->values()
        ->all();
}

test('connecting a service provisions exactly the controls its driver declares', function (string $service, array $payload) {
    $user = connectingUser();

    expect(serviceControlKeys($user, $service))->toBe([]);

    $this->post("/settings/integrations/{$service}", $payload)->assertRedirect();

    expect(serviceControlKeys($user, $service))->toBe(expectedControlKeys($service));
})->with(collect(DIRECT_CONNECT_SERVICES)->map(fn ($p, $s) => [$s, $p])->values()->all());

test('provisioned controls are user-scoped and service-managed', function () {
    $user = connectingUser();

    $this->post('/settings/integrations/throne')->assertRedirect();

    $controls = OverlayControl::where('user_id', $user->id)->where('source', 'throne')->get();

    expect($controls)->not->toBeEmpty();

    foreach ($controls as $control) {
        // User-scoped, so the tag resolves in every overlay the user owns
        // without any per-template setup. See OverlayTemplateController's
        // render query, which unions template-scoped with these.
        expect($control->overlay_template_id)->toBeNull()
            ->and($control->source_managed)->toBeTrue();
    }
});

test('reconnecting does not duplicate controls', function () {
    $user = connectingUser();

    $this->post('/settings/integrations/kofi', ['verification_token' => 'first'])->assertRedirect();
    $first = serviceControlKeys($user, 'kofi');

    $this->post('/settings/integrations/kofi', ['verification_token' => 'second'])->assertRedirect();

    expect(serviceControlKeys($user, 'kofi'))->toBe($first)
        ->and(OverlayControl::where('user_id', $user->id)->where('source', 'kofi')->count())
        ->toBe(count($first));
});

test('disconnecting removes the provisioned controls', function () {
    $user = connectingUser();

    $this->post('/settings/integrations/throne')->assertRedirect();
    expect(serviceControlKeys($user, 'throne'))->not->toBe([]);

    $this->delete('/settings/integrations/throne')->assertRedirect();

    expect(serviceControlKeys($user, 'throne'))->toBe([]);
});

test('the streamlabs oauth callback provisions on connect', function () {
    $user = connectingUser();

    Http::fake([
        'streamlabs.com/api/v1.0/token' => Http::response(['access_token' => 'fake-access-token']),
        'streamlabs.com/api/v1.0/socket/token' => Http::response(['socket_token' => 'fake-socket-token']),
    ]);

    expect(serviceControlKeys($user, 'streamlabs'))->toBe([]);

    $this->get('/auth/callback/streamlabs?code=fake-code')->assertRedirect();

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'streamlabs')->exists())->toBeTrue()
        ->and(serviceControlKeys($user, 'streamlabs'))->toBe(expectedControlKeys('streamlabs'));
});

/**
 * The shared show() collapsed the old connected/disconnected branches into one
 * array. Every settings page reads these props, so a dropped key is a broken
 * page rather than a failing test - assert the shape directly, in both states.
 */
test('every settings page renders with the full prop shape, connected or not', function (string $service, array $payload) {
    connectingUser();

    $expected = ['connected', 'enabled', 'test_mode', 'last_received_at', 'settings', 'donations_seed_set', 'donations_seed_value'];

    $this->get("/settings/integrations/{$service}")
        ->assertOk()
        ->assertInertia(function ($page) use ($service, $expected) {
            $page->component("settings/integrations/{$service}")
                ->where('integration.connected', false);
            foreach ($expected as $key) {
                $page->has("integration.{$key}");
            }
        });

    $this->post("/settings/integrations/{$service}", $payload)->assertRedirect();

    $this->get("/settings/integrations/{$service}")
        ->assertOk()
        ->assertInertia(function ($page) use ($service, $expected) {
            $page->component("settings/integrations/{$service}")
                ->where('integration.connected', true);
            foreach ($expected as $key) {
                $page->has("integration.{$key}");
            }
        });
})->with(collect(DIRECT_CONNECT_SERVICES)->map(fn ($p, $s) => [$s, $p])->values()->all());

test('the webhook url is present for webhook services and absent for the oauth pair', function () {
    connectingUser();

    $this->post('/settings/integrations/throne')->assertRedirect();
    $this->get('/settings/integrations/throne')
        ->assertInertia(fn ($page) => $page->has('integration.webhook_url'));

    // Streamlabs is pulled over a socket and Fourthwall registers its own
    // webhook through the API, so neither has a URL for the user to copy.
    $this->get('/settings/integrations/streamlabs')
        ->assertInertia(fn ($page) => $page->missing('integration.webhook_url'));
    $this->get('/settings/integrations/fourthwall')
        ->assertInertia(fn ($page) => $page->missing('integration.webhook_url'));
});

/**
 * Structural guard: a sixth donation integration added with a hand-rolled
 * controller would reintroduce exactly the bug this file exists to prevent.
 * Extending the base class is what makes provisioning automatic, so assert the
 * inheritance rather than trusting the next author to remember.
 */
test('every donation integration settings route is served by the shared base controller', function () {
    $donationServices = collect(ExternalServiceRegistry::services())
        ->reject(fn (string $s) => $s === 'gps')
        ->values();

    expect($donationServices)->not->toBeEmpty();

    foreach ($donationServices as $service) {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === "settings.integrations.{$service}.show");

        expect($route)->not->toBeNull("no show route registered for {$service}");

        $controller = explode('@', $route->getActionName())[0];

        expect(is_subclass_of($controller, DonationIntegrationController::class))
            ->toBeTrue("{$controller} must extend DonationIntegrationController so connecting provisions its controls");
    }
});
