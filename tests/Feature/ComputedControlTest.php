<?php

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\ComputedControlService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

uses(DatabaseTransactions::class);

function makeComputedTestFixture(): array
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'slug' => 'test-'.fake()->unique()->lexify('????????'),
    ]);

    return [$user, $template];
}

test('creating computed control with valid formula stores initial value', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $template] = makeComputedTestFixture();

    // Create the dependency first
    OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'deaths',
        'type' => 'counter',
        'value' => '15',
        'sort_order' => 0,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'danger_mode',
        'label' => 'Danger Mode',
        'type' => 'computed',
        'config' => [
            'formula' => [
                'watch_key' => 'deaths',
                'watch_source' => null,
                'operator' => '>=',
                'compare_value' => '10',
                'then_value' => 'true',
                'else_value' => 'false',
            ],
        ],
    ]);

    $response->assertStatus(201);
    $control = OverlayControl::where('key', 'danger_mode')
        ->where('overlay_template_id', $template->id)
        ->first();

    expect($control)->not->toBeNull();
    expect($control->type)->toBe('computed');
    expect($control->value)->toBe('true'); // deaths=15 >= 10

    Event::assertDispatched(ControlValueUpdated::class);
});

test('creating computed control with invalid watch_key returns 422', function () {
    [$user, $template] = makeComputedTestFixture();

    $this->actingAs($user);

    $response = $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'broken',
        'label' => 'Broken',
        'type' => 'computed',
        'config' => [
            'formula' => [
                'watch_key' => 'nonexistent',
                'watch_source' => null,
                'operator' => '>=',
                'compare_value' => '10',
                'then_value' => 'true',
                'else_value' => 'false',
            ],
        ],
    ]);

    $response->assertStatus(422);
});

test('creating computed control that creates cycle returns 422', function () {
    [$user, $template] = makeComputedTestFixture();

    // Create B that watches A
    OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'control_b',
        'type' => 'computed',
        'value' => null,
        'config' => ['formula' => [
            'watch_key' => 'control_a',
            'watch_source' => null,
            'operator' => '==',
            'compare_value' => '1',
            'then_value' => 'yes',
            'else_value' => 'no',
        ]],
        'sort_order' => 1,
    ]);

    $this->actingAs($user);

    // Try to create A that watches B - should be rejected
    $response = $this->postJson("/templates/{$template->id}/controls", [
        'key' => 'control_a',
        'label' => 'A',
        'type' => 'computed',
        'config' => [
            'formula' => [
                'watch_key' => 'control_b',
                'watch_source' => null,
                'operator' => '==',
                'compare_value' => '1',
                'then_value' => 'yes',
                'else_value' => 'no',
            ],
        ],
    ]);

    $response->assertStatus(422);

    // Ensure the control was not persisted
    expect(OverlayControl::where('key', 'control_a')->where('overlay_template_id', $template->id)->exists())->toBeFalse();
});

test('setValue returns 403 for computed control', function () {
    [$user, $template] = makeComputedTestFixture();

    $control = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'comp',
        'type' => 'computed',
        'value' => 'true',
        'config' => ['formula' => [
            'watch_key' => 'deaths',
            'watch_source' => null,
            'operator' => '>=',
            'compare_value' => '10',
            'then_value' => 'true',
            'else_value' => 'false',
        ]],
        'sort_order' => 0,
    ]);

    $this->actingAs($user);

    $this->postJson("/templates/{$template->id}/controls/{$control->id}/value", [
        'value' => 'manual',
    ])->assertStatus(403);
});

test('changing watched control triggers re-evaluation and broadcast', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $template] = makeComputedTestFixture();

    $deaths = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'deaths',
        'type' => 'counter',
        'value' => '5',
        'sort_order' => 0,
    ]);

    $computed = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'danger',
        'type' => 'computed',
        'value' => 'false',
        'config' => ['formula' => [
            'watch_key' => 'deaths',
            'watch_source' => null,
            'operator' => '>=',
            'compare_value' => '10',
            'then_value' => 'true',
            'else_value' => 'false',
        ]],
        'sort_order' => 1,
    ]);

    $this->actingAs($user);

    // Increment deaths past 10
    $this->postJson("/templates/{$template->id}/controls/{$deaths->id}/value", [
        'action' => 'increment',
    ]);

    // Keep incrementing past 10
    for ($i = 0; $i < 5; $i++) {
        $this->postJson("/templates/{$template->id}/controls/{$deaths->id}/value", [
            'action' => 'increment',
        ]);
    }

    // The computed value should now be 'true' (deaths went from 5 to 11)
    expect($computed->fresh()->value)->toBe('true');

    // Broadcast should have been dispatched for the computed control
    Event::assertDispatched(ControlValueUpdated::class, function ($event) {
        return $event->key === 'danger' && $event->value === 'true';
    });
});

test('cascade propagates through multiple levels', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $template] = makeComputedTestFixture();

    $base = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'base',
        'type' => 'counter',
        'value' => '0',
        'sort_order' => 0,
    ]);

    $level1 = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'level_one',
        'type' => 'computed',
        'value' => 'low',
        'config' => ['formula' => [
            'watch_key' => 'base',
            'watch_source' => null,
            'operator' => '>=',
            'compare_value' => '5',
            'then_value' => 'high',
            'else_value' => 'low',
        ]],
        'sort_order' => 1,
    ]);

    $level2 = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'level_two',
        'type' => 'computed',
        'value' => 'no',
        'config' => ['formula' => [
            'watch_key' => 'level_one',
            'watch_source' => null,
            'operator' => '==',
            'compare_value' => 'high',
            'then_value' => 'yes',
            'else_value' => 'no',
        ]],
        'sort_order' => 2,
    ]);

    $service = app(ComputedControlService::class);

    // Simulate base changing to 10
    $base->update(['value' => '10']);
    $service->cascade($user, $base->fresh(), $template->slug);

    expect($level1->fresh()->value)->toBe('high');
    expect($level2->fresh()->value)->toBe('yes');
});

test('computed control does NOT broadcast when value unchanged', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $template] = makeComputedTestFixture();

    $deaths = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'deaths',
        'type' => 'counter',
        'value' => '3',
        'sort_order' => 0,
    ]);

    OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'danger',
        'type' => 'computed',
        'value' => 'false',
        'config' => ['formula' => [
            'watch_key' => 'deaths',
            'watch_source' => null,
            'operator' => '>=',
            'compare_value' => '10',
            'then_value' => 'true',
            'else_value' => 'false',
        ]],
        'sort_order' => 1,
    ]);

    $service = app(ComputedControlService::class);

    // Change deaths from 3 to 4 - still below 10, so computed stays 'false'
    $deaths->update(['value' => '4']);
    $service->cascade($user, $deaths->fresh(), $template->slug);

    // No ControlValueUpdated should have been dispatched for the computed control
    Event::assertNotDispatched(ControlValueUpdated::class, function ($event) {
        return $event->key === 'danger';
    });
});

test('computed control referencing user-scoped service control works', function () {
    Event::fake([ControlValueUpdated::class]);

    [$user, $template] = makeComputedTestFixture();

    // Create a user-scoped Ko-fi control
    $kofiControl = OverlayControl::create([
        'overlay_template_id' => null,
        'user_id' => $user->id,
        'key' => 'kofis_received',
        'type' => 'counter',
        'value' => '8',
        'source' => 'kofi',
        'source_managed' => true,
        'sort_order' => 0,
    ]);

    // Create a computed control on the template that watches the Ko-fi control
    $computed = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'bonus_active',
        'type' => 'computed',
        'value' => 'true',
        'config' => ['formula' => [
            'watch_key' => 'kofis_received',
            'watch_source' => 'kofi',
            'operator' => '>=',
            'compare_value' => '5',
            'then_value' => 'true',
            'else_value' => 'false',
        ]],
        'sort_order' => 1,
    ]);

    $service = app(ComputedControlService::class);

    // Simulate Ko-fi control changing to 3 (below threshold)
    $kofiControl->update(['value' => '3']);
    $service->cascade($user, $kofiControl->fresh(), '');

    expect($computed->fresh()->value)->toBe('false');

    Event::assertDispatched(ControlValueUpdated::class, function ($event) {
        return $event->key === 'bonus_active' && $event->value === 'false';
    });
});
