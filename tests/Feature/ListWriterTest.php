<?php

use App\Events\ControlValuesBatchUpdated;
use App\Events\ControlValueUpdated;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Controls\ExpressionEngineClient;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use App\Support\ListItems;
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
    $values = $overrides['items'] ?? [];
    $built = ListItems::freshFromValues($values, 1);
    unset($overrides['items']);

    return OptionSet::create(array_merge([
        'user_id' => $user->id,
        'slug' => 'recorded',
        'items' => $built['items'],
        'next_item_id' => $built['next_id'],
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

/**
 * Bypasses the real Twitch API entirely - returns whatever the test sets.
 * Constructor is intentionally empty so the parent's dependency on tokens /
 * HTTP clients doesn't fire during tests.
 */
class FakeTwitchApiService extends TwitchApiService
{
    /** @var array<string,mixed> */
    public array $extendedData = [];

    public function __construct() {}

    public function getExtendedUserData(string $accessToken, string $userId): array
    {
        return $this->extendedData;
    }
}

/**
 * Bypasses mapForTemplate so tests don't need to know the mapper's full
 * tag-name conventions. Returns a flat map of tag => value verbatim.
 */
class FakeTemplateDataMapperService extends TemplateDataMapperService
{
    /** @var array<string,mixed> */
    public array $mapped = [];

    public function mapForTemplate(array $twitchData, string $overlayName, ?array $templateTags = null, ?array $eventData = null, ?array $caps = null): array
    {
        return $this->mapped;
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

    expect(ListItems::values($list->fresh()->items))->toBe(['7']);
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

    expect(ListItems::values($list->fresh()->items))->toBe(['Alice']);
});

it('appends a service control value delivered via a batched broadcast', function () {
    $user = lwUser();
    $template = lwTemplate($user);
    $source = lwService($user, 'kofi', 'latest_donor_name');
    $list = lwList($user, ['slug' => 'kofi_donors_batch']);
    lwWriter($user, $template, $source, $list);

    ControlValuesBatchUpdated::dispatch((string) $user->twitch_id, [
        ['overlay_slug' => '', 'key' => 'kofi:latest_donor_name', 'type' => 'text', 'value' => 'Bob'],
    ]);

    expect(ListItems::values($list->fresh()->items))->toBe(['Bob']);
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

    expect(ListItems::values($list->fresh()->items))->toBe(['b', 'c', 'd']);
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
        ->and(ListItems::values($list->fresh()->items))->toBe(['10'])
        ->and($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['expression'])->toBe('c.wins * 2');
});

it('recomputes an Expression Control from a batched service update', function () {
    $fake = new FakeExpressionEngineClient;
    app()->instance(ExpressionEngineClient::class, $fake);

    $user = lwUser();
    $template = lwTemplate($user);

    $donations = lwService($user, 'kofi', 'total_received');
    $donations->value = '30';
    $donations->save();

    $expr = lwExpression($user, $template, 'c["kofi:total_received"] / 100 * 100', 'goal_pct', ['kofi:total_received']);

    $list = lwList($user, ['slug' => 'goal_log']);
    lwWriter($user, $template, $expr, $list, 'goal_logger');

    $fake->responses['c["kofi:total_received"] / 100 * 100'] = '30';

    // A donation arrives as a batched broadcast - the expression depending on
    // the service control must still recompute and cascade to the list.
    ControlValuesBatchUpdated::dispatch((string) $user->twitch_id, [
        ['overlay_slug' => '', 'key' => 'kofi:total_received', 'type' => 'number', 'value' => '30'],
    ]);

    expect($expr->fresh()->value)->toBe('30')
        ->and(ListItems::values($list->fresh()->items))->toBe(['30']);
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
        ->and(ListItems::values($list->fresh()->items))->toBe(['40']);
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

// ──────────────────────────────────────────────────────────────────────────────
// Twitch tag (t.<tag>) data populates the recompute context
// ──────────────────────────────────────────────────────────────────────────────

it('ships t:<tag> data to the sidecar when the user has a Twitch access token', function () {
    $fakeEngine = new FakeExpressionEngineClient;
    $fakeTwitch = new FakeTwitchApiService;
    $fakeTwitch->extendedData = ['user' => ['display_name' => 'JasperDiscovers']]; // shape doesn't matter; mapper is faked too
    $fakeMapper = new FakeTemplateDataMapperService;
    $fakeMapper->mapped = [
        'followers_total' => 1234,
        'channel_name' => 'jasperdiscovers',
    ];

    app()->instance(ExpressionEngineClient::class, $fakeEngine);
    app()->instance(TwitchApiService::class, $fakeTwitch);
    app()->instance(TemplateDataMapperService::class, $fakeMapper);

    $user = lwUser();
    $user->access_token = 'pretend-this-is-a-token';
    $user->save();

    $template = lwTemplate($user);
    $raw = lwCounter($user, $template, 'bonus');
    $raw->value = '5';
    $raw->save();

    $expr = lwExpression($user, $template, 't.followers_total + c.bonus', 'total', ['bonus']);
    $fakeEngine->responses['t.followers_total + c.bonus'] = '1239';

    ControlValueUpdated::dispatch($template->slug, 'bonus', 'counter', '5', (string) $user->twitch_id);

    // The sidecar received both control AND twitch-tag data
    expect($fakeEngine->calls)->toHaveCount(1)
        ->and($fakeEngine->calls[0]['data'])->toHaveKey('c:bonus')
        ->and($fakeEngine->calls[0]['data'])->toHaveKey('t:followers_total')
        ->and($fakeEngine->calls[0]['data']['t:followers_total'])->toBe('1234')
        ->and($fakeEngine->calls[0]['data']['t:channel_name'])->toBe('jasperdiscovers');

    // And the expression's stored value updated as usual
    expect($expr->fresh()->value)->toBe('1239');
});

it('skips Twitch tag enrichment when user has no access_token', function () {
    $fakeEngine = new FakeExpressionEngineClient;
    $fakeTwitch = new FakeTwitchApiService;
    $fakeTwitch->extendedData = ['user' => ['display_name' => 'should not appear']];
    $fakeMapper = new FakeTemplateDataMapperService;
    $fakeMapper->mapped = ['followers_total' => 9999];

    app()->instance(ExpressionEngineClient::class, $fakeEngine);
    app()->instance(TwitchApiService::class, $fakeTwitch);
    app()->instance(TemplateDataMapperService::class, $fakeMapper);

    $user = lwUser();
    // access_token deliberately null/default
    $template = lwTemplate($user);
    $raw = lwCounter($user, $template, 'bonus');
    $expr = lwExpression($user, $template, 'c.bonus', 'doubled', ['bonus']);
    $fakeEngine->responses['c.bonus'] = '7';

    ControlValueUpdated::dispatch($template->slug, 'bonus', 'counter', '7', (string) $user->twitch_id);

    // Sidecar was called but t-tags were NOT in the data
    expect($fakeEngine->calls)->toHaveCount(1)
        ->and($fakeEngine->calls[0]['data'])->not->toHaveKey('t:followers_total');
});

it('survives Twitch API failure without breaking the cascade', function () {
    $fakeEngine = new FakeExpressionEngineClient;
    // Use Mockery-style fake via anonymous class to throw on the call.
    $brokenTwitch = new class extends TwitchApiService
    {
        public function __construct() {}

        public function getExtendedUserData(string $accessToken, string $userId): array
        {
            throw new RuntimeException('helix unavailable');
        }
    };
    $fakeMapper = new FakeTemplateDataMapperService;

    app()->instance(ExpressionEngineClient::class, $fakeEngine);
    app()->instance(TwitchApiService::class, $brokenTwitch);
    app()->instance(TemplateDataMapperService::class, $fakeMapper);

    $user = lwUser();
    $user->access_token = 'token';
    $user->save();
    $template = lwTemplate($user);
    $raw = lwCounter($user, $template, 'bonus');
    $expr = lwExpression($user, $template, 'c.bonus * 2', 'doubled', ['bonus']);
    $fakeEngine->responses['c.bonus * 2'] = '10';

    ControlValueUpdated::dispatch($template->slug, 'bonus', 'counter', '5', (string) $user->twitch_id);

    // Recompute still ran with the controls it could see, just no t-tags
    expect($fakeEngine->calls)->toHaveCount(1)
        ->and($fakeEngine->calls[0]['data'])->toHaveKey('c:bonus');
    expect($expr->fresh()->value)->toBe('10');
});
