<?php

use App\Events\ControlValueUpdated;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Controls\ExpressionEngineClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function lwUser(string $login = 'streamer_lw'): User
{
    return User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => $login],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function lwTemplate(User $user): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
    ]);
}

function lwList(User $user, array $overrides = []): OptionSet
{
    return OptionSet::create(array_merge([
        'user_id' => $user->id,
        'slug' => 'recorded',
        'items' => [],
        'min_items' => 0,
        'user_editable' => true,
    ], $overrides));
}

function lwCounter(User $user, OverlayTemplate $template, string $key = 'wins'): OverlayControl
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

function lwService(User $user, string $source = 'kofi', string $key = 'latest_donor_name'): OverlayControl
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

function lwExpression(User $user, OverlayTemplate $template, string $expression, string $key = 'computed', array $deps = []): OverlayControl
{
    return OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => $key,
        'type' => 'expression',
        'value' => '',
        'config' => ['expression' => $expression, 'dependencies' => $deps],
        'sort_order' => 0,
    ]);
}

function lwWriter(User $user, OverlayTemplate $template, OverlayControl $source, OptionSet $target, string $key = 'logger'): OverlayControl
{
    return OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $user->id,
        'key' => $key,
        'type' => 'list_writer',
        'value' => null,
        'config' => [
            'source_control_id' => $source->id,
            'target_list_id' => $target->id,
        ],
        'sort_order' => 0,
    ]);
}

/**
 * Test double for the expression engine sidecar. Records calls and
 * returns canned values. Bound into the container for the duration of
 * each test that needs it.
 */
class FakeExpressionEngineClient extends ExpressionEngineClient
{
    /** @var array<int,array{expression:string,data:array<string,string>}> */
    public array $calls = [];

    /** @var array<string,string|null> Map of expression string -> value to return. */
    public array $responses = [];

    public function __construct() {}

    public function evaluate(string $expression, array $data): ?string
    {
        $this->calls[] = ['expression' => $expression, 'data' => $data];

        return $this->responses[$expression] ?? null;
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// list_writer: basic flow with raw source
// ──────────────────────────────────────────────────────────────────────────────

it('appends source value to target list when source updates', function () {
    $user = lwUser();
    $template = lwTemplate($user);
    $source = lwCounter($user, $template, 'wins');
    $list = lwList($user, ['slug' => 'wins_log']);
    lwWriter($user, $template, $source, $list);

    ControlValueUpdated::dispatch(
        $template->slug,
        'wins',
        'counter',
        '7',
        (string) $user->twitch_id,
    );

    expect($list->fresh()->items)->toBe(['7']);
});

it('appends a service-managed control value via namespaced broadcast key', function () {
    $user = lwUser();
    $template = lwTemplate($user);
    $source = lwService($user, 'kofi', 'latest_donor_name');
    $list = lwList($user, ['slug' => 'kofi_donors']);
    lwWriter($user, $template, $source, $list);

    ControlValueUpdated::dispatch(
        '',
        'kofi:latest_donor_name',
        'text',
        'Alice',
        (string) $user->twitch_id,
    );

    expect($list->fresh()->items)->toBe(['Alice']);
});

it('FIFO drops oldest when max_items is exceeded', function () {
    $user = lwUser();
    $template = lwTemplate($user);
    $source = lwCounter($user, $template, 'mood');
    $list = lwList($user, [
        'slug' => 'mood_log',
        'items' => ['a', 'b', 'c'],
        'max_items' => 3,
    ]);
    lwWriter($user, $template, $source, $list);

    ControlValueUpdated::dispatch($template->slug, 'mood', 'counter', 'd', (string) $user->twitch_id);

    expect($list->fresh()->items)->toBe(['b', 'c', 'd']);
});

it('skips disabled lists', function () {
    $user = lwUser();
    $template = lwTemplate($user);
    $source = lwCounter($user, $template, 'wins');
    $list = lwList($user, ['slug' => 'paused', 'disabled_at' => now()]);
    lwWriter($user, $template, $source, $list);

    ControlValueUpdated::dispatch($template->slug, 'wins', 'counter', '5', (string) $user->twitch_id);

    expect($list->fresh()->items)->toBe([]);
});

it('does nothing when no list_writer points at the updated control', function () {
    $user = lwUser();
    $template = lwTemplate($user);
    lwCounter($user, $template, 'wins');
    $list = lwList($user, ['slug' => 'unrelated']);

    ControlValueUpdated::dispatch($template->slug, 'wins', 'counter', '5', (string) $user->twitch_id);

    expect($list->fresh()->items)->toBe([]);
});

it('refuses to write to a list owned by a different user', function () {
    $owner = lwUser('owner');
    $other = lwUser('other');
    $template = lwTemplate($owner);
    $source = lwCounter($owner, $template, 'wins');
    $foreignList = lwList($other, ['slug' => 'their_list']);

    // Forcibly construct a list_writer pointing at a cross-user list
    // (the dashboard would prevent this, but defence in depth).
    OverlayControl::create([
        'overlay_template_id' => $template->id,
        'user_id' => $owner->id,
        'key' => 'evil',
        'type' => 'list_writer',
        'value' => null,
        'config' => ['source_control_id' => $source->id, 'target_list_id' => $foreignList->id],
        'sort_order' => 0,
    ]);

    ControlValueUpdated::dispatch($template->slug, 'wins', 'counter', '999', (string) $owner->twitch_id);

    expect($foreignList->fresh()->items)->toBe([]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Recompute listener + sidecar integration (via FakeExpressionEngineClient)
// ──────────────────────────────────────────────────────────────────────────────

it('recomputes an Expression Control when its dependency updates and appends to a bound list', function () {
    $fake = new FakeExpressionEngineClient;
    app()->instance(ExpressionEngineClient::class, $fake);

    $user = lwUser();
    $template = lwTemplate($user);

    $raw = lwCounter($user, $template, 'wins');
    $raw->value = '5';
    $raw->save();

    $expr = lwExpression($user, $template, 'c.wins * 2', 'doubled', ['wins']);

    $list = lwList($user, ['slug' => 'doubled_log']);
    lwWriter($user, $template, $expr, $list, 'doubled_logger');

    $fake->responses['c.wins * 2'] = '10';

    ControlValueUpdated::dispatch($template->slug, 'wins', 'counter', '5', (string) $user->twitch_id);

    expect($expr->fresh()->value)->toBe('10')
        ->and($list->fresh()->items)->toBe(['10'])
        ->and($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['expression'])->toBe('c.wins * 2');
});

it('cascades through chained expression controls (A -> B)', function () {
    $fake = new FakeExpressionEngineClient;
    app()->instance(ExpressionEngineClient::class, $fake);

    $user = lwUser();
    $template = lwTemplate($user);

    $raw = lwCounter($user, $template, 'wins');
    $raw->value = '3';
    $raw->save();

    $a = lwExpression($user, $template, 'c.wins + 1', 'a', ['wins']);
    $b = lwExpression($user, $template, 'c.a * 10', 'b', ['a']);

    $list = lwList($user, ['slug' => 'b_log']);
    lwWriter($user, $template, $b, $list, 'b_logger');

    $fake->responses['c.wins + 1'] = '4';
    $fake->responses['c.a * 10'] = '40';

    ControlValueUpdated::dispatch($template->slug, 'wins', 'counter', '3', (string) $user->twitch_id);

    expect($a->fresh()->value)->toBe('4')
        ->and($b->fresh()->value)->toBe('40')
        ->and($list->fresh()->items)->toBe(['40']);
});

it('skips recompute when sidecar returns null (down or error)', function () {
    $fake = new FakeExpressionEngineClient;
    app()->instance(ExpressionEngineClient::class, $fake);

    $user = lwUser();
    $template = lwTemplate($user);
    $raw = lwCounter($user, $template, 'wins');
    $raw->value = '5';
    $raw->save();

    $expr = lwExpression($user, $template, 'c.wins * 2', 'doubled', ['wins']);
    // Pre-set the value so we can verify it doesn't change.
    $expr->value = 'previous';
    $expr->save();

    $list = lwList($user, ['slug' => 'will_stay_empty']);
    lwWriter($user, $template, $expr, $list);

    // No response configured -> fake returns null (simulating sidecar down).
    ControlValueUpdated::dispatch($template->slug, 'wins', 'counter', '5', (string) $user->twitch_id);

    expect($expr->fresh()->value)->toBe('previous')
        ->and($list->fresh()->items)->toBe([]);
});

it('bails on alreadyRecomputed cascade events to prevent self-walk', function () {
    $fake = new FakeExpressionEngineClient;
    app()->instance(ExpressionEngineClient::class, $fake);

    $user = lwUser();
    $template = lwTemplate($user);
    lwExpression($user, $template, 'c.something + 1', 'derived', ['something']);

    ControlValueUpdated::dispatch(
        $template->slug,
        'something',
        'expression',
        '42',
        (string) $user->twitch_id,
        null,
        null,
        null,
        true, // alreadyRecomputed
    );

    // The listener should have bailed; no sidecar calls made.
    expect($fake->calls)->toBe([]);
});
