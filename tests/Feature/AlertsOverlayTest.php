<?php

use App\Events\AlertTriggered;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\Kit;
use App\Models\OverlayAccessToken;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Alerts\AlertsOverlay;
use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/*
 * One Alerts overlay per account. An untargeted alert renders only there, but
 * only once OBS has loaded it; before that it renders in every static overlay
 * as it always did. Explicit targeting is untouched. A kit holding its
 * author's Alerts overlay merges that overlay's controls into the copier's.
 */

beforeEach(function () {
    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('getExtendedUserData')->andReturn([]);
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($t, $e) => $e);
    });
});

function aoUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'access_token' => 'fake-twitch-token',
    ]);
}

function aoToken(User $user): string
{
    $plain = bin2hex(random_bytes(32));
    OverlayAccessToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 8),
        'name' => 'alerts-overlay-test',
        'is_active' => true,
    ]);

    return $plain;
}

function aoTemplate(User $user, string $type): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => $type,
        'head' => '',
        'html' => '<div>[[[event.from_name]]]</div>',
        'css' => '',
        'slug' => $type.'-'.fake()->unique()->lexify('????????'),
        'metadata' => null,
    ]);
}

/** A Ko-fi tip through the real webhook, mapped to $alert. */
function aoFireKofi(User $user, OverlayTemplate $alert): void
{
    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'enabled' => true,
        'credentials' => Crypt::encryptString(json_encode(['verification_token' => 'tok'])),
    ]);
    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'overlay_template_id' => $alert->id,
        'enabled' => true,
        'duration_ms' => 8000,
    ]);

    test()->post("/api/webhooks/kofi/{$integration->webhook_token}", ['data' => json_encode([
        'verification_token' => 'tok',
        'kofi_transaction_id' => 'txn-'.fake()->uuid(),
        'from_name' => 'Bob',
        'message' => 'Hi!',
        'amount' => '5.00',
        'currency' => 'USD',
        'type' => 'Donation',
        'is_subscription_payment' => false,
        'is_first_subscription_payment' => false,
    ])]);
}

function aoRender(User $user, OverlayTemplate $static): array
{
    return test()->postJson('/api/overlay/render', ['slug' => $static->slug, 'token' => aoToken($user)])
        ->assertOk()
        ->json();
}

test('ensure creates one transparent static Alerts overlay and returns it again after that', function () {
    $user = aoUser();
    $service = app(AlertsOverlay::class);

    $first = $service->ensure($user);
    $second = $service->ensure($user->fresh());

    expect($first->id)->toBe($second->id)
        ->and($first->type)->toBe('static')
        ->and($first->owner_id)->toBe($user->id)
        ->and($first->is_public)->toBeFalse()
        ->and($user->fresh()->alerts_overlay_id)->toBe($first->id)
        ->and(OverlayTemplate::where('owner_id', $user->id)->count())->toBe(1);
});

test('without an Alerts overlay an untargeted alert still renders everywhere', function () {
    Event::fake([AlertTriggered::class]);
    $user = aoUser();
    $alert = aoTemplate($user, 'alert');

    aoFireKofi($user, $alert);

    Event::assertDispatched(AlertTriggered::class, fn (AlertTriggered $e) => $e->targetOverlaySlugs === null);
});

test('an Alerts overlay OBS has not loaded yet changes nothing', function () {
    Event::fake([AlertTriggered::class]);
    $user = aoUser();
    $alert = aoTemplate($user, 'alert');
    app(AlertsOverlay::class)->ensure($user);

    aoFireKofi($user, $alert);

    Event::assertDispatched(AlertTriggered::class, fn (AlertTriggered $e) => $e->targetOverlaySlugs === null);
});

test('loading the Alerts overlay marks it live, loading another overlay does not', function () {
    $user = aoUser();
    $other = aoTemplate($user, 'static');
    $host = app(AlertsOverlay::class)->ensure($user);

    aoRender($user, $other);
    expect($user->fresh()->alerts_overlay_live_at)->toBeNull();

    aoRender($user, $host);
    expect($user->fresh()->alerts_overlay_live_at)->not->toBeNull();
});

test('once the Alerts overlay is live an untargeted alert renders there only', function () {
    Event::fake([AlertTriggered::class]);
    $user = aoUser();
    $alert = aoTemplate($user, 'alert');
    $host = app(AlertsOverlay::class)->ensure($user);
    aoRender($user, $host);

    aoFireKofi($user->fresh(), $alert);

    Event::assertDispatched(AlertTriggered::class, fn (AlertTriggered $e) => $e->targetOverlaySlugs === [$host->slug]);
});

test('an alert with explicit targets keeps them when the Alerts overlay is live', function () {
    Event::fake([AlertTriggered::class]);
    $user = aoUser();
    $scene = aoTemplate($user, 'static');
    $alert = aoTemplate($user, 'alert');
    $alert->targetStaticOverlays()->attach($scene->id);
    $host = app(AlertsOverlay::class)->ensure($user);
    aoRender($user, $host);

    aoFireKofi($user->fresh(), $alert);

    Event::assertDispatched(AlertTriggered::class, fn (AlertTriggered $e) => $e->targetOverlaySlugs === [$scene->slug]);
});

test('alert sources follow the same rule: a live Alerts overlay takes untargeted alerts off other overlays', function () {
    $user = aoUser();
    $scene = aoTemplate($user, 'static');
    $alert = aoTemplate($user, 'alert');
    $host = app(AlertsOverlay::class)->ensure($user);

    // Not live yet: the scene still carries the alert.
    expect(aoRender($user, $scene)['alert_sources'])->toHaveKey($alert->slug);

    aoRender($user, $host);

    expect(aoRender($user, $host)['alert_sources'])->toHaveKey($alert->slug)
        ->and((array) aoRender($user, $scene)['alert_sources'])->not->toHaveKey($alert->slug);
});

test('a new Alerts overlay after the old one was deleted is not live until OBS loads it', function () {
    $user = aoUser();
    $service = app(AlertsOverlay::class);
    $old = $service->ensure($user);
    aoRender($user, $old);
    $old->delete();

    $new = $service->ensure($user->fresh());

    expect($new->id)->not->toBe($old->id)
        ->and($user->fresh()->alerts_overlay_live_at)->toBeNull();
});

test('copying a kit that holds its author\'s Alerts overlay merges its controls into the copier\'s', function () {
    $author = aoUser();
    $service = app(AlertsOverlay::class);
    $authorHost = $service->ensure($author);
    OverlayControl::create([
        'overlay_template_id' => $authorHost->id,
        'user_id' => $author->id,
        'key' => 'subtle_position',
        'label' => 'Position',
        'type' => 'text',
        'value' => 'bottom-left',
        'config' => ['choices' => [['value' => 'bottom-left', 'label' => 'Bottom left']]],
        'sort_order' => 0,
    ]);
    $alert = aoTemplate($author, 'alert');
    $kit = Kit::factory()->create(['owner_id' => $author->id, 'is_public' => true]);
    $kit->templates()->attach([$alert->id, $authorHost->id]);

    $copier = aoUser();
    $copy = $kit->fresh()->fork($copier);
    $copierHost = $copier->fresh()->alertsOverlay;

    expect($copierHost)->not->toBeNull()
        ->and($copierHost->id)->not->toBe($authorHost->id)
        // One alert copied plus the copier's own Alerts overlay, no second static overlay.
        ->and(OverlayTemplate::where('owner_id', $copier->id)->where('type', 'static')->count())->toBe(1)
        ->and($copy->templates()->pluck('overlay_templates.id')->all())->toContain($copierHost->id)
        ->and(OverlayControl::where('overlay_template_id', $copierHost->id)->pluck('key')->all())->toBe(['subtle_position'])
        ->and(OverlayControl::where('overlay_template_id', $copierHost->id)->first()->config['choices'][0]['value'])->toBe('bottom-left');

    // A second copy does not duplicate the control.
    $kit->fresh()->fork($copier->fresh());
    expect(OverlayControl::where('overlay_template_id', $copierHost->id)->count())->toBe(1);
});

test('a kit static overlay that is not the author\'s Alerts overlay is still copied as its own overlay', function () {
    $author = aoUser();
    app(AlertsOverlay::class)->ensure($author);
    $scene = aoTemplate($author, 'static');
    $kit = Kit::factory()->create(['owner_id' => $author->id, 'is_public' => true]);
    $kit->templates()->attach([$scene->id]);

    $copier = aoUser();
    $kit->fresh()->fork($copier);

    expect(OverlayTemplate::where('owner_id', $copier->id)->where('type', 'static')->count())->toBe(1)
        ->and($copier->fresh()->alerts_overlay_id)->toBeNull();
});

test('the create route makes the Alerts overlay and is safe to call twice', function () {
    $user = aoUser();

    $this->actingAs($user)->post(route('alerts-overlay.store'))->assertRedirect();
    $this->actingAs($user)->post(route('alerts-overlay.store'))->assertRedirect();

    expect(OverlayTemplate::where('owner_id', $user->id)->where('name', AlertsOverlay::NAME)->count())->toBe(1)
        ->and($user->fresh()->alerts_overlay_id)->not->toBeNull();
});

test('a control cannot be added to an alert, and still can to a static overlay', function () {
    $user = aoUser();
    $alert = aoTemplate($user, 'alert');
    $static = aoTemplate($user, 'static');
    $this->actingAs($user);

    $this->postJson("/templates/{$alert->id}/controls", ['key' => 'corner', 'type' => 'text'])->assertStatus(422);
    $this->postJson("/templates/{$static->id}/controls", ['key' => 'corner', 'type' => 'text'])->assertStatus(201);

    expect(OverlayControl::where('overlay_template_id', $alert->id)->exists())->toBeFalse();
});

test('controls cannot be imported onto an alert', function () {
    $user = aoUser();
    $alert = aoTemplate($user, 'alert');
    $this->actingAs($user);

    $this->postJson("/templates/{$alert->id}/controls/import", [
        'controls' => [['action' => 'create', 'key' => 'corner', 'type' => 'text', 'value' => 'x']],
    ])->assertStatus(422);

    expect(OverlayControl::where('overlay_template_id', $alert->id)->exists())->toBeFalse();
});

test('alert pages carry the Alerts overlay state, static pages do not', function () {
    $user = aoUser();
    $alert = aoTemplate($user, 'alert');
    $static = aoTemplate($user, 'static');
    $host = app(AlertsOverlay::class)->ensure($user);
    $this->actingAs($user->fresh());

    $this->get("/templates/{$alert->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('alertsOverlay.overlay.id', $host->id)
        ->where('alertsOverlay.live', false));
    $this->get("/templates/{$alert->id}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('alertsOverlay.overlay.slug', $host->slug));
    $this->get("/templates/{$static->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('alertsOverlay', null));
    $this->get('/templates/create')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('alertsOverlay.overlay.id', $host->id));
});
