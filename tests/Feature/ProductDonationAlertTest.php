<?php

use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
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
 * Donation Alerts is the fourth product and the first with an ingredient:
 * one question, which donation service to connect first, answered on the
 * product page and written into the integration the install connects. The
 * alert itself is not tied to the answer: it gets a trigger for every one
 * of the five donation services, each waiting until that service is
 * connected, and the stage counts tips and names the latest donor across
 * all of them through two expression controls of its own. It is also the
 * first listed product whose integration needs authorizing, so it is the
 * first to exercise the setup banner's integration step.
 */
const DONATION_SERVICES = ['streamlabs', 'kofi', 'bmac', 'fourthwall', 'throne'];

function donationUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'donationtester'.fake()->unique()->randomNumber(5)],
    ], $attrs));
}

function donationInstance(User $user): RecipeInstance
{
    return ProductSetup::instanceFor($user->fresh(), 'donation_alert');
}

it('is listed between tower and bowling, without a hero', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.2.slug', 'donation_alert')
            ->where('products.2.hero', null)
            ->where('products.2.requires_bot', false)
        );
});

it('asks one question with five services and Streamlabs picked, and installs no bot command', function () {
    $this->get('/products/donation_alert')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('product.ingredients', 1)
            ->where('product.ingredients.0.key', 'service')
            ->where('product.ingredients.0.question', 'Which service should we connect first?')
            ->where('product.ingredients.0.default', 'streamlabs')
            ->has('product.ingredients.0.choices', 5)
            ->where('product.ingredients.0.choices.1', ['value' => 'kofi', 'label' => 'Ko-fi'])
            ->where('product.integrations', ['{{service}}'])
            ->where('product.overlays.0.name', 'Donation stage')
            ->where('product.overlays.0.type', 'static')
            ->where('product.overlays.1.name', 'Donation alert')
            ->where('product.overlays.1.type', 'alert')
            ->where('product.commands', [])
            ->where('installed', null)
        );
});

it('does not claim any service as its own, so every one stays under External Integrations', function () {
    $manifest = app(RecipeCatalog::class)->find('donation_alert');

    expect($manifest)->not->toHaveKey('requires_integrations');
});

it('writes a donation trigger for every service in the manifest, and connects only the one picked', function () {
    $manifest = app(RecipeCatalog::class)->find('donation_alert');
    $triggers = $manifest['installs']['alert_triggers'];

    expect(array_column($triggers, 'service'))->toBe(DONATION_SERVICES)
        ->and(array_unique(array_column($triggers, 'event_type')))->toBe(['donation'])
        ->and(array_unique(array_column($triggers, 'overlay')))->toBe(['tip_alert'])
        ->and($manifest['installs']['integrations'])->toBe(['{{service}}']);
});

it('installs the picked service into the integration, five triggers for the alert, and the targeting', function () {
    $user = donationUser();

    $this->actingAs($user)
        ->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']])
        ->assertRedirect('/products/donation_alert')
        ->assertSessionHas('success', 'Donation Alerts is installed.');

    $instance = donationInstance($user);
    $stage = OverlayTemplate::find($instance->primitive_map['overlays']['stage']);
    $alert = OverlayTemplate::find($instance->primitive_map['overlays']['tip_alert']);
    $triggers = ExternalEventTemplateMapping::where('user_id', $user->id)->orderBy('id')->get();

    expect($instance->ingredients)->toBe(['service' => 'kofi'])
        ->and(ExternalIntegration::where('user_id', $user->id)->pluck('service')->all())->toBe(['kofi'])
        ->and(ExternalIntegration::where('user_id', $user->id)->value('enabled'))->toBeTrue()
        ->and($triggers->pluck('service')->all())->toBe(DONATION_SERVICES)
        ->and($triggers->pluck('event_type')->unique()->all())->toBe(['donation'])
        ->and($triggers->pluck('overlay_template_id')->unique()->all())->toBe([$alert->id])
        ->and($triggers->pluck('enabled')->unique()->all())->toBe([true])
        ->and($alert->targetStaticOverlays()->pluck('overlay_templates.id')->all())->toBe([$stage->id])
        ->and($alert->tts_message)->toBe('[[[event.from_name]]] tipped [[[event.formatted_amount]]]')
        ->and($alert->tts_delay_ms)->toBe(3000);
});

it('gives the stage two expression controls that read every service, and no service tag of its own', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']]);

    $stage = OverlayTemplate::find(donationInstance($user)->primitive_map['overlays']['stage']);
    $controls = OverlayControl::where('overlay_template_id', $stage->id)->orderBy('sort_order')->get()->keyBy('key');

    expect($stage->html)->toContain('[[[c:tips_total]]]')
        ->and($stage->html)->toContain('[[[c:newest_donor]]]')
        ->and($stage->html)->not->toContain('c:kofi:')
        ->and($stage->html)->not->toContain('{{')
        ->and($controls->keys()->all())->toBe(['tips_total', 'newest_donor'])
        ->and($controls->pluck('type')->unique()->all())->toBe(['expression'])
        ->and($controls['tips_total']->config['expression'])->toStartWith('sum(')
        ->and($controls['newest_donor']->config['expression'])->toStartWith('latest(');

    // The dependencies are what the renderer follows to re-evaluate, so
    // each control has to name every service, not only the one picked.
    foreach (DONATION_SERVICES as $service) {
        expect($controls['tips_total']->config['dependencies'])->toContain("{$service}:donations_received")
            ->and($controls['newest_donor']->config['dependencies'])->toContain("{$service}:donations_received")
            ->and($controls['newest_donor']->config['dependencies'])->toContain("{$service}:latest_donor_name");
    }
});

it('installs Streamlabs when the form sends no answer', function () {
    $user = donationUser();

    $this->actingAs($user)->post('/products/donation_alert/install')->assertRedirect('/products/donation_alert');

    expect(donationInstance($user)->ingredients)->toBe(['service' => 'streamlabs'])
        ->and(ExternalIntegration::where('user_id', $user->id)->pluck('service')->all())->toBe(['streamlabs'])
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->count())->toBe(5);
});

it('refuses a service that is not a choice, on the page, with nothing created', function () {
    $user = donationUser();

    $this->actingAs($user)
        ->post('/products/donation_alert/install', ['ingredients' => ['service' => 'paypal']])
        ->assertRedirect('/products/donation_alert')
        ->assertSessionHasErrors('install');

    expect(RecipeInstance::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(OverlayTemplate::where('owner_id', $user->id)->exists())->toBeFalse()
        ->and(ExternalIntegration::where('user_id', $user->id)->exists())->toBeFalse();
});

it('refuses to install over an alert already firing on any donation service, picked or not', function () {
    $user = donationUser();
    $mine = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'type' => 'alert', 'name' => 'My Throne alert']);
    ExternalEventTemplateMapping::create([
        'user_id' => $user->id,
        'overlay_template_id' => $mine->id,
        'service' => 'throne',
        'event_type' => 'donation',
        'duration_ms' => 5000,
        'enabled' => true,
    ]);

    $this->actingAs($user)
        ->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']])
        ->assertRedirect('/products/donation_alert')
        ->assertSessionHasErrors(['install' => "Your alert 'My Throne alert' already fires on Throne Gift or Contribution. Remove that trigger, then install again."]);

    expect(RecipeInstance::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(ExternalIntegration::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->count())->toBe(1);
});

it('shows what was answered once installed, and how the alert is wired instead of an OBS step for it', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'throne']]);

    $this->actingAs($user->fresh())
        ->get('/products/donation_alert')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.ingredients.service', 'throne')
            ->has('installed.overlays', 2)
            ->where('installed.overlays.0.name', 'Donation stage')
            ->where('installed.overlays.0.type', 'static')
            ->missing('installed.overlays.0.fires_on')
            ->where('installed.overlays.1.name', 'Donation alert')
            ->where('installed.overlays.1.type', 'alert')
            ->where('installed.overlays.1.fires_on', [
                'Streamlabs Donation',
                'Ko-fi Donation',
                'Buy Me a Coffee Donation',
                'Fourthwall Donation',
                'Throne Gift or Contribution',
            ])
            ->where('installed.overlays.1.targets', ['Donation stage'])
        );
});

it('hands the finished page the test guide for the picked service, and what the service can also send', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']]);

    $this->actingAs($user->fresh())
        ->get('/products/donation_alert')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.test_guide.service', 'kofi')
            ->where('installed.test_guide.service_label', 'Ko-fi')
            ->where('installed.test_guide.url', 'https://ko-fi.com/manage/webhooks')
            ->where('installed.test_guide.settings_url', route('settings.integrations.kofi.show'))
            ->has('installed.test_guide.steps', 3)
            ->where('installed.landed', null)
            ->where('installed.overlays.1.more_events', ['Ko-fi Subscription', 'Ko-fi Shop Order', 'Ko-fi Commission'])
        );
});

it('has nothing more to offer for a service with one event type', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'streamlabs']]);

    $this->actingAs($user->fresh())
        ->get('/products/donation_alert')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.test_guide.service', 'streamlabs')
            ->where('installed.overlays.1.more_events', [])
        );
});

it('says the tip landed once an event the alert fires on has arrived since the install', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']]);

    // A Ko-fi subscription is not what the alert fires on, so it is not a landing.
    ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'subscription',
        'message_id' => 'sub-1',
        'raw_payload' => [],
        'normalized_payload' => ['event.from_name' => 'Sam', 'event.formatted_amount' => 'EUR 3,00'],
    ]);
    $this->actingAs($user->fresh())
        ->get('/products/donation_alert')
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
        ->get('/products/donation_alert')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.landed.from_name', 'Jo')
            ->where('installed.landed.formatted_amount', 'EUR 5,00')
        );
});

it('says the tip landed when it came through a service other than the one picked', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']]);

    ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'throne',
        'event_type' => 'donation',
        'message_id' => 'gift-1',
        'raw_payload' => [],
        'normalized_payload' => ['event.from_name' => 'Marijke', 'event.formatted_amount' => 'EUR 12,00'],
    ]);

    $this->actingAs($user->fresh())
        ->get('/products/donation_alert')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.landed.from_name', 'Marijke')
            ->where('installed.landed.formatted_amount', 'EUR 12,00')
        );
});

it('does not count a tip that arrived before the install', function () {
    $user = donationUser();
    $before = ExternalEvent::create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'event_type' => 'donation',
        'message_id' => 'old-1',
        'raw_payload' => [],
        'normalized_payload' => ['event.from_name' => 'Old', 'event.formatted_amount' => 'EUR 1,00'],
    ]);
    $before->forceFill(['created_at' => now()->subDay()])->saveQuietly();

    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']]);

    $this->actingAs($user->fresh())
        ->get('/products/donation_alert')
        ->assertInertia(fn (Assert $page) => $page->where('installed.landed', null));
});

it('reads the alert wiring live, so a trigger switched off drops out of the page', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']]);
    ExternalEventTemplateMapping::where('user_id', $user->id)->where('service', '!=', 'kofi')->update(['enabled' => false]);

    $this->actingAs($user->fresh())
        ->get('/products/donation_alert')
        ->assertInertia(fn (Assert $page) => $page->where('installed.overlays.1.fires_on', ['Ko-fi Donation']));
});

it('sends the setup banner to the picked service on the integrations page, lit up', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']]);

    $banner = ProductSetup::banner($user->fresh(), app(RecipeCatalog::class));

    // No bot wires apply, so the first thing missing is the Ko-fi token,
    // and the step points at the Ko-fi row - not at Streamlabs, the default
    // this install did not pick.
    expect($banner['next']['label'])->toBe(WiringCatalog::wire('product.integration')['label'])
        ->and($banner['next']['target'])->toBe('integration-kofi')
        ->and($banner['next']['url'])->toBe(route(WiringCatalog::wire('product.integration')['route']).'#el-integration-kofi');
});

it('lets a streamer who already has the service connected install without a second row', function () {
    $user = donationUser();
    ExternalIntegration::create(['user_id' => $user->id, 'service' => 'kofi', 'enabled' => false]);

    $this->actingAs($user)
        ->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']])
        ->assertRedirect('/products/donation_alert');

    expect(ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')->count())->toBe(1)
        ->and(ExternalIntegration::where('user_id', $user->id)->where('service', 'kofi')->value('enabled'))->toBeTrue()
        ->and(donationInstance($user)->primitive_map['integrations']['kofi']['created'])->toBeFalse();
});

it('uninstalls the alert, the stage, all five triggers and the stage controls, and leaves the connection', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'bmac']]);
    $stageId = donationInstance($user)->primitive_map['overlays']['stage'];

    $this->actingAs($user->fresh())
        ->post('/products/donation_alert/uninstall')
        ->assertRedirect('/products/donation_alert');

    expect(RecipeInstance::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(OverlayTemplate::where('owner_id', $user->id)->exists())->toBeFalse()
        ->and(OverlayControl::where('overlay_template_id', $stageId)->exists())->toBeFalse()
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(ExternalIntegration::where('user_id', $user->id)->where('service', 'bmac')->exists())->toBeTrue();
});
