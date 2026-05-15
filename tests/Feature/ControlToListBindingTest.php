<?php

use App\Events\ControlValueUpdated;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function bindingUser(string $login = 'streamer_c'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function bindingTemplate(User $user): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
    ]);
}

function bindingList(User $user, array $overrides = []): OptionSet
{
    return OptionSet::create(array_merge([
        'user_id' => $user->id,
        'slug' => 'donor_history',
        'items' => [],
        'min_items' => 0,
        'user_editable' => true,
    ], $overrides));
}

function bindingCounterControl(User $user, OverlayTemplate $template, string $key = 'wins'): OverlayControl
{
    return OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => $key,
        'type' => 'counter',
        'value' => '0',
        'sort_order' => 0,
    ]);
}

function bindingServiceControl(User $user, string $source = 'kofi', string $key = 'latest_donor_name'): OverlayControl
{
    return OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => $key,
        'type' => 'text',
        'value' => '',
        'sort_order' => 0,
        'source' => $source,
        'source_managed' => true,
    ]);
}

function bindingExpressionControl(User $user, OverlayTemplate $template, string $expression): OverlayControl
{
    return OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'computed',
        'type' => 'expression',
        'value' => '',
        'config' => ['expression' => $expression, 'dependencies' => []],
        'sort_order' => 0,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Listener: control update -> list append
// ──────────────────────────────────────────────────────────────────────────────

it('appends control value to the bound list when ControlValueUpdated fires', function () {
    $user = bindingUser();
    $template = bindingTemplate($user);
    $control = bindingCounterControl($user, $template, 'wins');
    $list = bindingList($user, ['slug' => 'wins_log']);
    $list->source_control_id = $control->id;
    $list->save();

    ControlValueUpdated::dispatch(
        $template->slug,
        'wins',
        'counter',
        '7',
        (string) $user->twitch_id,
    );

    expect($list->fresh()->items)->toBe(['7']);
});

it('appends a service-managed control value (namespaced broadcast key)', function () {
    $user = bindingUser();
    $control = bindingServiceControl($user, 'kofi', 'latest_donor_name');
    $list = bindingList($user, ['slug' => 'kofi_donors']);
    $list->source_control_id = $control->id;
    $list->save();

    ControlValueUpdated::dispatch(
        '',
        'kofi:latest_donor_name',
        'text',
        'Alice',
        (string) $user->twitch_id,
    );

    expect($list->fresh()->items)->toBe(['Alice']);
});

it('rolls FIFO when max_items is hit', function () {
    $user = bindingUser();
    $template = bindingTemplate($user);
    $control = bindingCounterControl($user, $template, 'mood');
    $list = bindingList($user, [
        'slug' => 'mood_log',
        'items' => ['a', 'b', 'c'],
        'max_items' => 3,
    ]);
    $list->source_control_id = $control->id;
    $list->save();

    ControlValueUpdated::dispatch(
        $template->slug,
        'mood',
        'counter',
        'd',
        (string) $user->twitch_id,
    );

    expect($list->fresh()->items)->toBe(['b', 'c', 'd']);
});

it('does not append when the bound list is disabled', function () {
    $user = bindingUser();
    $template = bindingTemplate($user);
    $control = bindingCounterControl($user, $template, 'wins');
    $list = bindingList($user, [
        'slug' => 'paused_log',
        'disabled_at' => now(),
    ]);
    $list->source_control_id = $control->id;
    $list->save();

    ControlValueUpdated::dispatch(
        $template->slug,
        'wins',
        'counter',
        '5',
        (string) $user->twitch_id,
    );

    expect($list->fresh()->items)->toBe([]);
});

it('does nothing when the updating control has no bound list', function () {
    $user = bindingUser();
    $template = bindingTemplate($user);
    bindingCounterControl($user, $template, 'wins');
    $list = bindingList($user, ['slug' => 'unrelated_list']);

    ControlValueUpdated::dispatch(
        $template->slug,
        'wins',
        'counter',
        '5',
        (string) $user->twitch_id,
    );

    expect($list->fresh()->items)->toBe([]);
});

it('nulls the binding when the source control is deleted', function () {
    $user = bindingUser();
    $template = bindingTemplate($user);
    $control = bindingCounterControl($user, $template, 'wins');
    $list = bindingList($user, ['slug' => 'cascade_test']);
    $list->source_control_id = $control->id;
    $list->save();

    $control->delete();

    expect($list->fresh()->source_control_id)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Validation: v1 limits
// ──────────────────────────────────────────────────────────────────────────────

it('rejects binding to an Expression Control', function () {
    $user = bindingUser();
    $template = bindingTemplate($user);
    $expr = bindingExpressionControl($user, $template, 'c.wins + 1');
    $list = bindingList($user, ['slug' => 'reject_me']);

    expect(fn () => $list->update(['source_control_id' => $expr->id]))
        ->toThrow(InvalidArgumentException::class, 'jsep');
});

it('rejects binding to a control owned by a different user', function () {
    $owner = bindingUser('owner');
    $other = bindingUser('other');
    $template = bindingTemplate($other);
    $foreignControl = bindingCounterControl($other, $template, 'wins');
    $list = bindingList($owner, ['slug' => 'cross_user']);

    expect(fn () => $list->update(['source_control_id' => $foreignControl->id]))
        ->toThrow(InvalidArgumentException::class, 'same user');
});

it('enforces one-control-to-one-list at the DB level', function () {
    $user = bindingUser();
    $template = bindingTemplate($user);
    $control = bindingCounterControl($user, $template, 'wins');

    $first = bindingList($user, ['slug' => 'first']);
    $first->source_control_id = $control->id;
    $first->save();

    $second = bindingList($user, ['slug' => 'second']);
    expect(fn () => $second->update(['source_control_id' => $control->id]))
        ->toThrow(QueryException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Cycle guard
// ──────────────────────────────────────────────────────────────────────────────

it('detects cycle when source control reads the target list directly', function () {
    $user = bindingUser();
    $template = bindingTemplate($user);
    // Hypothetical future: expression that reads c.list.<slug>. The
    // server-side evaluator doesn't honour this yet, but the cycle guard
    // catches it preemptively.
    $expr = bindingExpressionControl($user, $template, 'c.list.target_list + 1');

    $cycle = OptionSet::detectListBindingCycle($expr->id, 'target_list', $user->id);

    expect($cycle)->toBeTrue();
});

it('reports no cycle for a raw control source', function () {
    $user = bindingUser();
    $template = bindingTemplate($user);
    $counter = bindingCounterControl($user, $template, 'wins');

    $cycle = OptionSet::detectListBindingCycle($counter->id, 'anything', $user->id);

    expect($cycle)->toBeFalse();
});

it('detects cycle through an intermediate expression dependency', function () {
    $user = bindingUser();
    $template = bindingTemplate($user);

    // Y reads the target list (hypothetical future expression eval).
    $y = bindingExpressionControl($user, $template, 'c.list.target_list * 2');
    // Override key for distinctness.
    $y->key = 'y';
    $y->save();

    // X reads Y.
    $x = OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => 'x',
        'type' => 'expression',
        'value' => '',
        'config' => ['expression' => 'c.y + 1', 'dependencies' => []],
        'sort_order' => 0,
    ]);

    $cycle = OptionSet::detectListBindingCycle($x->id, 'target_list', $user->id);

    expect($cycle)->toBeTrue();
});
