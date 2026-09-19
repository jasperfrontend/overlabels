<?php

use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OverlayAccessLog;
use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Support\ProductSetup;
use App\Support\WiringCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

/**
 * One product per donation service, five products. Each installs one alert
 * with one trigger, its own service, targeted at nothing so it renders
 * inside every static overlay the person has in OBS, and connects that one
 * service. No question at install, no stage of its own: the person's
 * overlays are the stage. A single "Donation Alerts" wrapping all five was
 * built and reversed on 2026-09-15; these pin the split.
 */
const DONATION_PRODUCTS = [
    'streamlabs-alerts' => ['service' => 'streamlabs', 'name' => 'Streamlabs Alerts', 'fires_on' => 'Streamlabs Donation', 'alert' => 'Streamlabs alert'],
    'ko-fi-alerts' => ['service' => 'kofi', 'name' => 'Ko-fi Alerts', 'fires_on' => 'Ko-fi Donation', 'alert' => 'Ko-fi alert'],
    'buy-me-a-coffee-alerts' => ['service' => 'bmac', 'name' => 'Buy Me a Coffee Alerts', 'fires_on' => 'Buy Me a Coffee Donation', 'alert' => 'Buy Me a Coffee alert'],
    'fourthwall-alerts' => ['service' => 'fourthwall', 'name' => 'Fourthwall Alerts', 'fires_on' => 'Fourthwall Donation', 'alert' => 'Fourthwall alert'],
    'throne-alerts' => ['service' => 'throne', 'name' => 'Throne Alerts', 'fires_on' => 'Throne Gift or Contribution', 'alert' => 'Throne alert'],
];

function donationProductUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'donationtester'.fake()->unique()->randomNumber(5)],
    ]);
}

function donationProductInstance(User $user, string $slug): RecipeInstance
{
    return ProductSetup::instanceFor($user->fresh(), $slug);
}

function donationProducts(): array
{
    $slugs = array_keys(DONATION_PRODUCTS);

    return array_combine($slugs, $slugs);
}

it('is one product per service, with no question, no hero and no stage', function (string $slug) {
    $product = DONATION_PRODUCTS[$slug];

    $this->get("/products/{$slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.name', $product['name'])
            ->where('product.hero', null)
            ->where('product.requires_bot', false)
            ->where('product.ingredients', [])
            ->where('product.integrations', [$product['service']])
            ->has('product.overlays', 1)
            ->where('product.overlays.0.name', $product['alert'])
            ->where('product.overlays.0.type', 'alert')
            ->where('product.commands', [])
            ->where('installed', null)
        );
})->with(donationProducts());

it('declares one trigger for its own service, targets nothing, and claims no service on the integrations page', function (string $slug) {
    $product = DONATION_PRODUCTS[$slug];
    $manifest = app(RecipeCatalog::class)->find($slug);

    expect($manifest['installs']['alert_triggers'])->toBe([['overlay' => 'tip_alert', 'service' => $product['service'], 'event_type' => 'donation']])
        ->and($manifest['installs'])->not->toHaveKey('alert_targets')
        ->and($manifest)->not->toHaveKey('ingredients')
        ->and($manifest)->not->toHaveKey('requires_integrations')
        ->and($manifest['max_instances_per_user'])->toBe(1);
})->with(donationProducts());

it('installs the alert, its one trigger and its integration, targeted at every static overlay', function (string $slug) {
    $product = DONATION_PRODUCTS[$slug];
    $user = donationProductUser();

    $this->actingAs($user)
        ->post("/products/{$slug}/install")
        ->assertRedirect("/products/{$slug}")
        ->assertSessionHas('success', $product['name'].' is installed.');

    $instance = donationProductInstance($user, $slug);
    $alert = OverlayTemplate::find($instance->primitive_map['overlays']['tip_alert']);
    $triggers = ExternalEventTemplateMapping::where('user_id', $user->id)->get();

    expect($instance->primitive_map['overlays'])->toHaveCount(1)
        ->and($alert->type)->toBe('alert')
        ->and($alert->name)->toBe($product['alert'])
        ->and($alert->css)->toContain('.donation-alert')
        ->and($alert->targetStaticOverlays()->count())->toBe(0)
        ->and($triggers->count())->toBe(1)
        ->and($triggers->first()->service)->toBe($product['service'])
        ->and($triggers->first()->event_type)->toBe('donation')
        ->and($triggers->first()->enabled)->toBeTrue()
        ->and($triggers->first()->overlay_template_id)->toBe($alert->id)
        ->and(ExternalIntegration::where('user_id', $user->id)->pluck('service')->all())->toBe([$product['service']]);
})->with(donationProducts());

it('refuses to install over an alert already firing on its own service, even switched off, and names itself', function (string $slug) {
    $product = DONATION_PRODUCTS[$slug];
    $user = donationProductUser();
    $mine = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'name' => 'My own alert']);
    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'overlay_template_id' => $mine->id,
        'service' => $product['service'],
        'event_type' => 'donation',
        'duration_ms' => 5000,
        'enabled' => false,
    ]);

    $this->actingAs($user)
        ->post("/products/{$slug}/install")
        ->assertRedirect("/products/{$slug}")
        ->assertSessionHasErrors(['install' => "{$product['name']} fires on {$product['fires_on']} too, and your alert 'My own alert' already does. Delete that trigger on its Triggers tab, switching it off is not enough, then install again."]);

    expect(RecipeInstance::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(ExternalIntegration::where('user_id', $user->id)->exists())->toBeFalse();
})->with(donationProducts());

it('installs beside an alert on another service, and two products install side by side', function () {
    $user = donationProductUser();
    $mine = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'name' => 'My Throne alert']);
    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'overlay_template_id' => $mine->id,
        'service' => 'throne',
        'event_type' => 'donation',
        'duration_ms' => 5000,
        'enabled' => true,
    ]);

    $this->actingAs($user)->post('/products/ko-fi-alerts/install')->assertSessionHasNoErrors();
    $this->actingAs($user)->post('/products/streamlabs-alerts/install')->assertSessionHasNoErrors();
    $this->actingAs($user)->post('/products/throne-alerts/install')->assertSessionHasErrors('install');

    expect(RecipeInstance::where('user_id', $user->id)->count())->toBe(2)
        ->and(OverlayTemplate::where('owner_id', $user->id)->where('type', 'alert')->count())->toBe(3)
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->pluck('service')->sort()->values()->all())->toBe(['kofi', 'streamlabs', 'throne'])
        ->and(ExternalIntegration::where('user_id', $user->id)->pluck('service')->sort()->values()->all())->toBe(['kofi', 'streamlabs']);
});

it('shows the alert wired to its one service, the person\'s own overlays for OBS, one service row and its test guide', function () {
    $user = donationProductUser();
    OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'static', 'name' => 'My scene']);
    $this->actingAs($user)->post('/products/ko-fi-alerts/install');

    $this->actingAs($user->fresh())
        ->get('/products/ko-fi-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.ingredients', [])
            ->has('installed.overlays', 1)
            ->where('installed.overlays.0.name', 'Ko-fi alert')
            ->where('installed.overlays.0.type', 'alert')
            ->where('installed.overlays.0.fires_on', ['Ko-fi Donation'])
            ->where('installed.overlays.0.targets', [])
            ->where('installed.overlays.0.more_events', ['Ko-fi Subscription', 'Ko-fi Shop Order', 'Ko-fi Commission'])
            ->where('installed.your_overlays.loaded', false)
            ->has('installed.your_overlays.overlays', 1)
            ->where('installed.your_overlays.overlays.0.name', 'My scene')
            ->where('installed.your_overlays.total', 1)
            ->has('installed.services', 1)
            ->where('installed.services.0.service', 'kofi')
            ->where('installed.services.0.kind', 'token')
            ->where('installed.test_guide.service', 'kofi')
            ->where('installed.test_guide.url', 'https://ko-fi.com/manage/webhooks')
            ->has('installed.test_guide.steps', 3)
            ->where('installed.landed', null)
        );
});

it('has nothing more to offer for a service with one event type, and no overlays to offer when there are none', function () {
    $user = donationProductUser();
    $this->actingAs($user)->post('/products/streamlabs-alerts/install');

    $this->actingAs($user->fresh())
        ->get('/products/streamlabs-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.overlays.0.more_events', [])
            ->where('installed.your_overlays', ['loaded' => false, 'overlays' => [], 'total' => 0])
            ->where('installed.test_guide.service', 'streamlabs')
        );
});

it('offers no overlays of the person\'s own for a product that ships its own stage', function () {
    $user = donationProductUser();
    OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'static', 'name' => 'My scene']);
    $this->actingAs($user)->post('/products/chat-tower/install');

    $this->actingAs($user->fresh())
        ->get('/products/chat-tower')
        ->assertInertia(fn (Assert $page) => $page->where('installed.your_overlays', ['loaded' => false, 'overlays' => [], 'total' => 0]));
});

it('names the overlays an overlay link has served lately, and otherwise offers the five most recently edited', function () {
    $user = donationProductUser();
    $scenes = collect(range(1, 7))->map(fn (int $i) => OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'type' => 'static',
        'name' => "Scene {$i}",
        'updated_at' => now()->subMinutes(10 - $i),
    ]));
    $this->actingAs($user)->post('/products/ko-fi-alerts/install');

    // Nothing served yet: the five newest, and a count of the rest.
    $this->actingAs($user->fresh())
        ->get('/products/ko-fi-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.your_overlays.loaded', false)
            ->has('installed.your_overlays.overlays', 5)
            ->where('installed.your_overlays.overlays.0.name', 'Scene 7')
            ->where('installed.your_overlays.total', 7)
        );

    // A link served Scene 2 this month and Scene 5 long ago: only Scene 2 counts.
    $token = OverlayAccessToken::create([
        'user_id' => $user->id,
        'name' => 'OBS',
        'token_hash' => hash('sha256', 'plain-'.$user->id),
        'token_prefix' => 'plain',
        'is_active' => true,
    ]);
    OverlayAccessLog::create(['token_id' => $token->id, 'template_slug' => $scenes[1]->slug, 'accessed_at' => now()->subDays(3)]);
    OverlayAccessLog::create(['token_id' => $token->id, 'template_slug' => $scenes[4]->slug, 'accessed_at' => now()->subDays(40)]);

    $this->actingAs($user->fresh())
        ->get('/products/ko-fi-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.your_overlays.loaded', true)
            ->has('installed.your_overlays.overlays', 1)
            ->where('installed.your_overlays.overlays.0.name', 'Scene 2')
            ->where('installed.your_overlays.total', 7)
        );
});

it('says the tip landed for its own service, and not for another service or another event type', function () {
    $user = donationProductUser();
    $this->actingAs($user)->post('/products/ko-fi-alerts/install');

    foreach ([['throne', 'donation', 'gift-1'], ['kofi', 'subscription', 'sub-1']] as [$service, $type, $id]) {
        ExternalEvent::create([
            'user_id' => $user->id,
            'service' => $service,
            'event_type' => $type,
            'message_id' => $id,
            'raw_payload' => [],
            'normalized_payload' => ['event.from_name' => 'Sam', 'event.formatted_amount' => 'EUR 3,00'],
        ]);
    }
    $this->actingAs($user->fresh())
        ->get('/products/ko-fi-alerts')
        ->assertInertia(fn (Assert $page) => $page->where('installed.landed', null));

    ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'message_id' => 'tip-1',
        'raw_payload' => [],
        'normalized_payload' => ['event.from_name' => 'Jo', 'event.formatted_amount' => 'EUR 5,00'],
    ]);
    $this->actingAs($user->fresh())
        ->get('/products/ko-fi-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.landed.from_name', 'Jo')
            ->where('installed.landed.formatted_amount', 'EUR 5,00')
        );
});

it('does not count a tip that arrived before the install', function () {
    $user = donationProductUser();
    $before = ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'message_id' => 'old-1',
        'raw_payload' => [],
        'normalized_payload' => ['event.from_name' => 'Old', 'event.formatted_amount' => 'EUR 1,00'],
    ]);
    $before->forceFill(['created_at' => now()->subDay()])->saveQuietly();

    $this->actingAs($user)->post('/products/ko-fi-alerts/install');

    $this->actingAs($user->fresh())
        ->get('/products/ko-fi-alerts')
        ->assertInertia(fn (Assert $page) => $page->where('installed.landed', null));
});

it('reads the alert wiring live, so a trigger switched off drops out of the page and takes its service row with it', function () {
    $user = donationProductUser();
    $this->actingAs($user)->post('/products/ko-fi-alerts/install');
    ExternalEventTemplateMapping::where('user_id', $user->id)->update(['enabled' => false]);

    $this->actingAs($user->fresh())
        ->get('/products/ko-fi-alerts')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.overlays.0.fires_on', [])
            ->where('installed.services', [])
        );
});

it('sends the setup banner to its own service on the integrations page, lit up', function () {
    $user = donationProductUser();
    $this->actingAs($user)->post('/products/ko-fi-alerts/install');

    $banner = ProductSetup::banner($user->fresh(), app(RecipeCatalog::class));

    // No bot wires apply, so the first thing missing is the Ko-fi token.
    expect($banner['next']['label'])->toBe(WiringCatalog::wire('product.integration')['label'])
        ->and($banner['next']['target'])->toBe('integration-kofi')
        ->and($banner['next']['url'])->toBe(route(WiringCatalog::wire('product.integration')['route']).'#el-integration-kofi');
});

it('lets a streamer who already has the service connected install without a second row', function () {
    $user = donationProductUser();
    ExternalIntegration::create(['user_id' => $user->id, 'service' => 'kofi', 'enabled' => false]);

    $this->actingAs($user)->post('/products/ko-fi-alerts/install')->assertRedirect('/products/ko-fi-alerts');

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')->count())->toBe(1)
        ->and(ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')->value('enabled'))->toBeTrue()
        ->and(donationProductInstance($user, 'ko-fi-alerts')->primitive_map['integrations']['kofi']['created'])->toBeFalse();
});

it('uninstalls the alert and its trigger, leaves the connection, and the page goes back to not installed', function () {
    $user = donationProductUser();
    $this->actingAs($user)->post('/products/ko-fi-alerts/install');
    $alertId = donationProductInstance($user, 'ko-fi-alerts')->primitive_map['overlays']['tip_alert'];

    $this->actingAs($user)->post('/products/ko-fi-alerts/uninstall')->assertRedirect('/products/ko-fi-alerts');

    expect(OverlayTemplate::find($alertId))->toBeNull()
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(RecipeInstance::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')->exists())->toBeTrue();

    $this->actingAs($user->fresh())
        ->get('/products/ko-fi-alerts')
        ->assertInertia(fn (Assert $page) => $page->where('installed', null));
});
