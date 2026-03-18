<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\ComputedControlService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function makeComputedControl(array $formula, ?int $templateId = null, ?int $userId = null): OverlayControl
{
    return new OverlayControl([
        'id' => fake()->unique()->randomNumber(5),
        'overlay_template_id' => $templateId,
        'user_id' => $userId ?? 1,
        'key' => 'test_computed',
        'type' => 'computed',
        'value' => null,
        'config' => ['formula' => $formula],
    ]);
}

test('evaluate numeric >= returns then_value when true', function () {
    $service = new ComputedControlService;

    $control = makeComputedControl([
        'watch_key' => 'deaths',
        'watch_source' => null,
        'operator' => '>=',
        'compare_value' => '10',
        'then_value' => '50',
        'else_value' => '10',
    ]);

    expect($service->evaluate($control, '15'))->toBe('50');
});

test('evaluate numeric >= returns else_value when false', function () {
    $service = new ComputedControlService;

    $control = makeComputedControl([
        'watch_key' => 'deaths',
        'watch_source' => null,
        'operator' => '>=',
        'compare_value' => '10',
        'then_value' => '50',
        'else_value' => '10',
    ]);

    expect($service->evaluate($control, '5'))->toBe('10');
});

test('evaluate string == comparison', function () {
    $service = new ComputedControlService;

    $control = makeComputedControl([
        'watch_key' => 'status',
        'watch_source' => null,
        'operator' => '==',
        'compare_value' => 'active',
        'then_value' => 'yes',
        'else_value' => 'no',
    ]);

    expect($service->evaluate($control, 'active'))->toBe('yes');
    expect($service->evaluate($control, 'inactive'))->toBe('no');
});

test('evaluate with null watched value returns else_value', function () {
    $service = new ComputedControlService;

    $control = makeComputedControl([
        'watch_key' => 'deaths',
        'watch_source' => null,
        'operator' => '>=',
        'compare_value' => '10',
        'then_value' => '50',
        'else_value' => '10',
    ]);

    expect($service->evaluate($control, null))->toBe('10');
});

test('detectCycle returns false for simple A -> B', function () {
    $service = new ComputedControlService;

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
    ]);

    // Create a non-computed control B
    $controlB = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'deaths',
        'type' => 'counter',
        'value' => '0',
        'sort_order' => 0,
    ]);

    // Control A (computed) wants to watch B
    $controlA = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'danger_mode',
        'type' => 'computed',
        'value' => null,
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

    $hasCycle = $service->detectCycle($controlA, [
        'watch_key' => 'deaths',
        'watch_source' => null,
    ], $template->id);

    expect($hasCycle)->toBeFalse();
});

test('detectCycle returns true for A -> B -> A', function () {
    $service = new ComputedControlService;

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
    ]);

    // B watches A (already saved)
    $controlB = OverlayControl::create([
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

    // A wants to watch B -> this would create A -> B -> A
    $controlA = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'control_a',
        'type' => 'computed',
        'value' => null,
        'config' => ['formula' => [
            'watch_key' => 'control_b',
            'watch_source' => null,
            'operator' => '==',
            'compare_value' => '1',
            'then_value' => 'yes',
            'else_value' => 'no',
        ]],
        'sort_order' => 0,
    ]);

    $hasCycle = $service->detectCycle($controlA, [
        'watch_key' => 'control_b',
        'watch_source' => null,
    ], $template->id);

    expect($hasCycle)->toBeTrue();
});

test('detectCycle returns true for self-reference', function () {
    $service = new ComputedControlService;

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
    ]);

    $control = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'self_ref',
        'type' => 'computed',
        'value' => null,
        'config' => [],
        'sort_order' => 0,
    ]);

    $hasCycle = $service->detectCycle($control, [
        'watch_key' => 'self_ref',
        'watch_source' => null,
    ], $template->id);

    expect($hasCycle)->toBeTrue();
});

test('detectCycle returns false for chain within depth', function () {
    $service = new ComputedControlService;

    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
    $template = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
    ]);

    // Create a chain: base (counter) -> B (computed) -> C (computed, new)
    OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'base',
        'type' => 'counter',
        'value' => '0',
        'sort_order' => 0,
    ]);

    OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'level_b',
        'type' => 'computed',
        'value' => null,
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

    $controlC = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'level_c',
        'type' => 'computed',
        'value' => null,
        'config' => [],
        'sort_order' => 2,
    ]);

    $hasCycle = $service->detectCycle($controlC, [
        'watch_key' => 'level_b',
        'watch_source' => null,
    ], $template->id);

    expect($hasCycle)->toBeFalse();
});
