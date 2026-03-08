<?php

use App\Events\ControlValueUpdated;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

function makeTemplateAndServiceControl(): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'slug' => 'test-'.fake()->unique()->lexify('????????'),
    ]);
    $control = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'kofis_received',
        'label' => 'Ko-fi Donations Received',
        'type' => 'counter',
        'value' => '5',
        'source' => 'kofi',
        'source_managed' => true,
        'sort_order' => 0,
    ]);

    return [$user, $template, $control];
}

test('setValue returns 403 for source_managed control', function () {
    [$user, $template, $control] = makeTemplateAndServiceControl();

    $this->actingAs($user);

    $this->postJson("/templates/{$template->id}/controls/{$control->id}/value", [
        'action' => 'increment',
    ])->assertStatus(403);
});

test('update returns 403 for source_managed control', function () {
    [$user, $template, $control] = makeTemplateAndServiceControl();

    $this->actingAs($user);

    $this->putJson("/templates/{$template->id}/controls/{$control->id}", [
        'label' => 'New label',
    ])->assertStatus(403);
});

test('broadcastKey includes source prefix for service-managed control', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $control = new OverlayControl([
        'key' => 'kofis_received',
        'source' => 'kofi',
        'source_managed' => true,
    ]);

    expect($control->broadcastKey())->toBe('kofi:kofis_received');
});

test('broadcastKey returns plain key for user-managed control', function () {
    $control = new OverlayControl([
        'key' => 'goal',
        'source' => null,
        'source_managed' => false,
    ]);

    expect($control->broadcastKey())->toBe('goal');
});

test('provisionServiceControl creates user-scoped control', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $control = OverlayControl::provisionServiceControl($user, 'kofi', [
        'key' => 'kofis_received',
        'type' => 'counter',
        'label' => 'Test',
        'value' => '0',
    ]);

    expect($control->overlay_template_id)->toBeNull();
    expect($control->source)->toBe('kofi');
    expect($control->source_managed)->toBeTrue();
    expect($control->user_id)->toBe($user->id);
});

test('disabling test mode resets kofis_received to seed value', function () {
    Event::fake([ControlValueUpdated::class]);

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'test_mode' => true,
        'settings' => [
            'enabled_events' => ['donation'],
            'kofis_seed_set' => true,
            'kofis_seed_value' => 42,
        ],
    ]);

    $control = OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'kofis_received',
        'label' => 'Ko-fi Donations Received',
        'type' => 'counter',
        'value' => '50',
        'source' => 'kofi',
        'source_managed' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->patchJson('/settings/integrations/kofi/test-mode', ['test_mode' => false])
        ->assertOk()
        ->assertJson(['test_mode' => false]);

    expect($control->fresh()->value)->toBe('42');
    Event::assertDispatched(ControlValueUpdated::class);
});

test('disabling test mode resets kofis_received to 0 when no seed set', function () {
    Event::fake([ControlValueUpdated::class]);

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'test_mode' => true,
        'settings' => ['enabled_events' => ['donation']],
    ]);

    $control = OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'kofis_received',
        'label' => 'Ko-fi Donations Received',
        'type' => 'counter',
        'value' => '15',
        'source' => 'kofi',
        'source_managed' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->patchJson('/settings/integrations/kofi/test-mode', ['test_mode' => false])
        ->assertOk();

    expect($control->fresh()->value)->toBe('0');
});

test('enabling test mode does not reset kofis_received', function () {
    Event::fake([ControlValueUpdated::class]);

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $integration = ExternalIntegration::factory()->create([
        'user_id' => $user->id,
        'service' => 'kofi',
        'test_mode' => false,
        'settings' => ['enabled_events' => ['donation']],
    ]);

    $control = OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'kofis_received',
        'label' => 'Ko-fi Donations Received',
        'type' => 'counter',
        'value' => '15',
        'source' => 'kofi',
        'source_managed' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->patchJson('/settings/integrations/kofi/test-mode', ['test_mode' => true])
        ->assertOk();

    expect($control->fresh()->value)->toBe('15');
    Event::assertNotDispatched(ControlValueUpdated::class);
});

test('provisionServiceControl is idempotent', function () {
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $def = ['key' => 'latest_donor_name', 'type' => 'text', 'label' => 'Name', 'value' => ''];

    OverlayControl::provisionServiceControl($user, 'kofi', $def);
    OverlayControl::provisionServiceControl($user, 'kofi', $def);

    $count = OverlayControl::where('user_id', $user->id)
        ->where('source', 'kofi')
        ->where('key', 'latest_donor_name')
        ->count();

    expect($count)->toBe(1);
});
