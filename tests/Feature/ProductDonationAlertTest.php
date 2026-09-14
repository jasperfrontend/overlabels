<?php

use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
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
 * one question, which donation service, answered on the product page and
 * written into the integration the install connects, the alert trigger it
 * writes, and the c:<service>: tags in the stage overlay. It is also the
 * first listed product whose integration needs authorizing, so it is the
 * first to exercise the setup banner's integration step.
 */
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
            ->where('product.ingredients.0.question', 'Where do your donations come in?')
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

it('installs the picked service into the integration, the trigger, the targeting and the stage', function () {
    $user = donationUser();

    $this->actingAs($user)
        ->post('/products/donation_alert/install', ['ingredients' => ['service' => 'kofi']])
        ->assertRedirect('/products/donation_alert')
        ->assertSessionHas('success', 'Donation Alerts is installed.');

    $instance = donationInstance($user);
    $stage = OverlayTemplate::find($instance->primitive_map['overlays']['stage']);
    $alert = OverlayTemplate::find($instance->primitive_map['overlays']['tip_alert']);
    $trigger = ExternalEventTemplateMapping::where('user_id', $user->id)->first();

    expect($instance->ingredients)->toBe(['service' => 'kofi'])
        ->and(ExternalIntegration::where('user_id', $user->id)->pluck('service')->all())->toBe(['kofi'])
        ->and(ExternalIntegration::where('user_id', $user->id)->value('enabled'))->toBeTrue()
        ->and($trigger->service)->toBe('kofi')
        ->and($trigger->event_type)->toBe('donation')
        ->and($trigger->overlay_template_id)->toBe($alert->id)
        ->and($alert->targetStaticOverlays()->pluck('overlay_templates.id')->all())->toBe([$stage->id])
        ->and($stage->html)->toContain('[[[c:kofi:donations_received]]]')
        ->and($stage->html)->toContain('[[[c:kofi:latest_donor_name]]]')
        ->and($stage->html)->not->toContain('{{')
        ->and($alert->tts_message)->toBe('[[[event.from_name]]] tipped [[[event.formatted_amount]]]')
        ->and($alert->tts_delay_ms)->toBe(3000);
});

it('installs Streamlabs when the form sends no answer', function () {
    $user = donationUser();

    $this->actingAs($user)->post('/products/donation_alert/install')->assertRedirect('/products/donation_alert');

    expect(donationInstance($user)->ingredients)->toBe(['service' => 'streamlabs'])
        ->and(ExternalIntegration::where('user_id', $user->id)->pluck('service')->all())->toBe(['streamlabs'])
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->value('service'))->toBe('streamlabs');
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

it('shows what was answered once installed', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'throne']]);

    $this->actingAs($user->fresh())
        ->get('/products/donation_alert')
        ->assertInertia(fn (Assert $page) => $page
            ->where('installed.ingredients.service', 'throne')
            ->has('installed.overlays', 2)
        );
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

it('uninstalls the alert, the stage and the trigger, and leaves the connection', function () {
    $user = donationUser();
    $this->actingAs($user)->post('/products/donation_alert/install', ['ingredients' => ['service' => 'bmac']]);

    $this->actingAs($user->fresh())
        ->post('/products/donation_alert/uninstall')
        ->assertRedirect('/products/donation_alert');

    expect(RecipeInstance::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(OverlayTemplate::where('owner_id', $user->id)->exists())->toBeFalse()
        ->and(ExternalEventTemplateMapping::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(ExternalIntegration::where('user_id', $user->id)->where('service', 'bmac')->exists())->toBeTrue();
});
