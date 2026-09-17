<?php

use App\Events\ControlValueUpdated;
use App\Listeners\RecomputeExpressionControls;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Bot\BotCommandResolver;
use App\Services\LivingTitleService;
use App\Services\Messages\AlertMessageRenderer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config([
        'services.expression_engine.url' => 'http://expression-engine.test',
        'services.expression_engine.secret' => 'test-secret',
    ]);
});

function hydrationUser(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function expressionControl(User $user, string $key, string $expression, ?string $value = null): OverlayControl
{
    return OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => $key,
        'label' => $key,
        'type' => 'expression',
        'value' => $value,
        'config' => [
            'expression' => $expression,
            'dependencies' => OverlayControl::extractExpressionDependencies($expression),
        ],
        'sort_order' => 0,
        // A user-authored control carries no source, so broadcastKey() is the
        // bare key - which is what `c.inner` resolves to in the engine.
        'source' => null,
        'source_managed' => false,
    ]);
}

/** Sidecar that answers every evaluate call with the same value. */
function fakeEngine(string $value): void
{
    Http::fake([
        '*/evaluate' => Http::response(['ok' => true, 'value' => $value]),
    ]);
}

test('an expression over a twitch tag renders in a bot reply', function () {
    $user = hydrationUser();
    // The reported case: depends on no control, so nothing ever recomputed
    // it and the row keeps the NULL it was created with.
    $control = expressionControl($user, 'subs_plus_1', 't.subscribers_total + 1');

    expect($control->config['dependencies'])->toBe([]);
    expect($control->value)->toBeNull();

    fakeEngine('5');

    expect(app(BotCommandResolver::class)->resolve($user, 'we are at [[[c:subs_plus_1]]] subs'))
        ->toBe('we are at 5 subs');
});

test('an expression over a twitch tag renders in the living title', function () {
    $user = hydrationUser();
    expressionControl($user, 'subs_plus_1', 't.subscribers_total + 1');

    fakeEngine('5');

    expect(app(LivingTitleService::class)->renderTemplate($user, '[[[c:subs_plus_1]]] subscribers. Promised!'))
        ->toBe('5 subscribers. Promised!');
});

test('an expression over a twitch tag renders in an alert message', function () {
    $user = hydrationUser();
    expressionControl($user, 'subs_plus_1', 't.subscribers_total + 1');

    fakeEngine('5');

    expect(app(AlertMessageRenderer::class)->render($user, 'now at [[[c:subs_plus_1]]]', []))
        ->toBe('now at 5');
});

test('a stale stored value is replaced by the freshly evaluated one', function () {
    $user = hydrationUser();
    // Recomputed once, long ago, when the dependency last moved.
    expressionControl($user, 'subs_plus_1', 't.subscribers_total + 1', '2');

    fakeEngine('5');

    expect(app(BotCommandResolver::class)->resolve($user, '[[[c:subs_plus_1]]]'))->toBe('5');
});

test('the sidecar is never called for a template naming no expression control', function () {
    $user = hydrationUser();
    expressionControl($user, 'subs_plus_1', 't.subscribers_total + 1');

    fakeEngine('5');

    app(BotCommandResolver::class)->resolve($user, 'no expressions here at all');

    Http::assertNothingSent();
});

test('a sidecar failure leaves the stored value rather than emptying it', function () {
    $user = hydrationUser();
    expressionControl($user, 'subs_plus_1', 't.subscribers_total + 1', '2');

    Http::fake(['*/evaluate' => Http::response(['ok' => false], 500)]);

    // Never worse than the behaviour this replaced, which always printed the
    // stored scalar.
    expect(app(BotCommandResolver::class)->resolve($user, '[[[c:subs_plus_1]]]'))->toBe('2');
});

test('an expression built on another expression sees the fresh value', function () {
    $user = hydrationUser();
    $inner = expressionControl($user, 'inner', 't.subscribers_total + 1');
    expressionControl($user, 'outer', 'c.inner * 2');

    expect($inner->config['dependencies'])->toBe([]);

    // The sidecar is stubbed per expression so the ordering is observable:
    // `outer` must be evaluated after `inner`, with inner's fresh value in
    // the context it is handed.
    Http::fake(function ($request) {
        $body = json_decode($request->body(), true);

        if ($body['expression'] === 't.subscribers_total + 1') {
            return Http::response(['ok' => true, 'value' => '5']);
        }

        return Http::response([
            'ok' => true,
            'value' => (string) (((int) ($body['data']['c:inner'] ?? 0)) * 2),
        ]);
    });

    expect(app(BotCommandResolver::class)->resolve($user, '[[[c:outer]]]'))->toBe('10');
});

test('a dry run does not reach the sidecar', function () {
    $user = hydrationUser();
    expressionControl($user, 'subs_plus_1', 't.subscribers_total + 1');

    fakeEngine('5');

    // dryRun powers the builder preview and the save-time validator; it exists
    // to skip exactly this kind of fetch.
    app(BotCommandResolver::class)->resolve($user, '[[[c:subs_plus_1]]]', [], true);

    Http::assertNothingSent();
});

test('the _at companion reaches the sidecar', function () {
    $user = hydrationUser();
    $minutes = OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'minutes',
        'label' => 'minutes',
        'type' => 'counter',
        'value' => '7',
        'config' => [],
        'sort_order' => 0,
        'source' => null,
        'source_managed' => false,
    ]);
    expressionControl($user, 'since', 'now() - c.minutes_at');

    $seen = [];
    Http::fake(function ($request) use (&$seen) {
        $seen = json_decode($request->body(), true)['data'] ?? [];

        return Http::response(['ok' => true, 'value' => '42']);
    });

    app(BotCommandResolver::class)->resolve($user, '[[[c:since]]]');

    // The engine coerces an unknown identifier rather than erroring, so a
    // missing `_at` does not fail loudly - it silently evaluates to 0. The
    // key has to be present and has to be the timestamp.
    expect($seen)->toHaveKey('c:minutes_at');
    expect($seen['c:minutes_at'])->toBe((string) $minutes->updated_at->timestamp);
});

test('the _at companion is namespaced for a service-managed control', function () {
    $user = hydrationUser();
    $control = OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'donations_received',
        'label' => 'donations_received',
        'type' => 'counter',
        'value' => '3',
        'config' => [],
        'sort_order' => 0,
        'source' => 'kofi',
        'source_managed' => true,
    ]);
    expressionControl($user, 'since_tip', 'now() - c.kofi.donations_received_at');

    $seen = [];
    Http::fake(function ($request) use (&$seen) {
        $seen = json_decode($request->body(), true)['data'] ?? [];

        return Http::response(['ok' => true, 'value' => '42']);
    });

    app(BotCommandResolver::class)->resolve($user, '[[[c:since_tip]]]');

    // `c:kofi:donations_received_at` is what `c.kofi.donations_received_at`
    // resolves to once the engine expands the namespace.
    expect($seen)->toHaveKey('c:kofi:donations_received_at');
    expect($seen['c:kofi:donations_received_at'])->toBe((string) $control->updated_at->timestamp);
});

test('a control with a source but not source-managed is named by its bare key', function () {
    $user = hydrationUser();
    // The shape no row has today. broadcastKey() would call it `user:goal`,
    // which no `c.` reference names, so the engine would coerce it to 0 in
    // silence - the same failure `_at` had. One rule, one place.
    $control = OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'goal',
        'label' => 'goal',
        'type' => 'counter',
        'value' => '9',
        'config' => [],
        'sort_order' => 0,
        'source' => 'user',
        'source_managed' => false,
    ]);

    expect($control->broadcastKey())->toBe('user:goal');
    expect($control->tagIdentifier())->toBe('goal');

    expressionControl($user, 'goal_plus', 'c.goal + 1');

    $seen = [];
    Http::fake(function ($request) use (&$seen) {
        $seen = json_decode($request->body(), true)['data'] ?? [];

        return Http::response(['ok' => true, 'value' => '10']);
    });

    app(BotCommandResolver::class)->resolve($user, '[[[c:goal_plus]]]');

    expect($seen)->toHaveKey('c:goal');
    expect($seen['c:goal'])->toBe('9');
    expect($seen)->toHaveKey('c:goal_at');
});

test('a service-managed control is still named by its namespaced key', function () {
    $user = hydrationUser();
    $control = OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'donations_received',
        'label' => 'donations_received',
        'type' => 'counter',
        'value' => '3',
        'config' => [],
        'sort_order' => 0,
        'source' => 'kofi',
        'source_managed' => true,
    ]);

    expect($control->tagIdentifier())->toBe('kofi:donations_received');
});

/**
 * A sourced, non-managed expression control: broadcastKey() says `user:mid`,
 * tagIdentifier() says `mid`, and the data map is keyed by the latter. Found by
 * the OL-2609-060 audit (C7): the resolved value went in under the former, so
 * a dependent expression read the stale stored value under `c:mid`.
 */
function sourcedExpressionControl(User $user, string $key, string $expression, ?string $value = null): OverlayControl
{
    return OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => $key,
        'label' => $key,
        'type' => 'expression',
        'value' => $value,
        'config' => [
            'expression' => $expression,
            'dependencies' => OverlayControl::extractExpressionDependencies($expression),
        ],
        'sort_order' => 0,
        'source' => 'user',
        'source_managed' => false,
    ]);
}

test('a resolved dependency lands under the key its dependents read, not the broadcast key', function () {
    $user = hydrationUser();
    $mid = sourcedExpressionControl($user, 'mid', 't.subscribers_total + 1', '1');
    expect($mid->broadcastKey())->toBe('user:mid');
    expressionControl($user, 'top', 'c.mid + 1');

    $seenByTop = null;
    Http::fake(function ($request) use (&$seenByTop) {
        $body = json_decode($request->body(), true);
        if ($body['expression'] === 't.subscribers_total + 1') {
            return Http::response(['ok' => true, 'value' => '5']);
        }
        $seenByTop = $body['data'] ?? [];

        return Http::response(['ok' => true, 'value' => '6']);
    });

    app(BotCommandResolver::class)->resolve($user, '[[[c:top]]]');

    expect($seenByTop)->not->toBeNull()
        ->and($seenByTop['c:mid'])->toBe('5')
        ->and($seenByTop)->not->toHaveKey('c:user:mid');
});

test('a cascaded recompute lands under the key its dependents read, not the broadcast key', function () {
    $user = hydrationUser();
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'seed',
        'label' => 'seed',
        'type' => 'counter',
        'value' => '4',
        'config' => [],
        'sort_order' => 0,
        'source' => null,
        'source_managed' => false,
    ]);
    sourcedExpressionControl($user, 'mid', 'c.seed + 1', '1');
    expressionControl($user, 'top', 'c.mid + 1', '2');

    $seenByTop = null;
    Http::fake(function ($request) use (&$seenByTop) {
        $body = json_decode($request->body(), true);
        if ($body['expression'] === 'c.seed + 1') {
            return Http::response(['ok' => true, 'value' => '5']);
        }
        $seenByTop = $body['data'] ?? [];

        return Http::response(['ok' => true, 'value' => '6']);
    });

    app(RecomputeExpressionControls::class)->handle(
        new ControlValueUpdated('', 'seed', 'counter', '4', $user->twitch_id),
    );

    expect($seenByTop)->not->toBeNull()
        ->and($seenByTop['c:mid'])->toBe('5')
        ->and($seenByTop)->not->toHaveKey('c:user:mid');
});
