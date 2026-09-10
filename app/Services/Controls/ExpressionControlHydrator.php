<?php

namespace App\Services\Controls;

use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Evaluates an Expression Control at READ time, for the PHP surfaces that
 * render a one-line template: bot command replies, alert TTS and chat
 * messages, and the living Twitch title.
 *
 * WHY THIS EXISTS. An Expression Control has no value of its own. The
 * overlay evaluates it in the browser on every tick, so `[[[c:subs_plus_1]]]`
 * on an overlay is always live. PHP has never evaluated anything: it reads
 * the scalar cached on the row, and RecomputeExpressionControls only
 * refreshes that scalar when a control the expression DEPENDS ON changes.
 * Two consequences, both reported as bugs:
 *
 *  - `t.subscribers_total + 1` depends on no control at all
 *    (extractExpressionDependencies matches `c.` references only), so
 *    nothing ever triggers a recompute and the row keeps the NULL it was
 *    created with. Every PHP surface rendered it as empty, forever.
 *  - Even a `c.`-dependent expression is NULL until its first dependency
 *    update, and stale between them.
 *
 * So the title advertised parity with an overlay and did not have it.
 *
 * WHY AT READ TIME rather than more triggers. Adding a "Twitch data changed"
 * trigger would still leave a new control empty until the first event fires,
 * and still hand out a snapshot from whenever that was. Evaluating on read
 * is parity by construction, needs no migration, and leaves the save path,
 * the cycle detector and the dependency validator alone.
 *
 * WHAT IT COSTS. The sidecar takes one expression per HTTP call, so this
 * only ever runs for expression controls the template actually NAMES -
 * `needed()` is a string scan and returns nothing for the overwhelming
 * majority of bot commands, which reference no expression at all. A
 * template naming one expression costs one call, plus one per expression it
 * transitively depends on.
 *
 * FAILURE IS NEVER A REGRESSION. If the sidecar is down, an expression keeps
 * whatever scalar the row already had - exactly what these surfaces printed
 * before this class existed.
 */
class ExpressionControlHydrator
{
    /**
     * Depth cap mirroring RecomputeExpressionControls::MAX_DEPTH. Expression
     * dependency graphs are cycle-checked at save time, so this is a net for
     * a row that bypassed the validator (a direct DB write, an import).
     */
    private const int MAX_DEPTH = 10;

    public function __construct(
        private readonly ExpressionEngineClient $engine,
        private readonly ExpressionDataContext $context,
    ) {}

    /**
     * Return $controls with every expression control named by $source
     * replaced by its freshly evaluated value.
     *
     * @param  array<string,string>  $controls  A ControlSnapshot's `values`:
     *                                          identifier => display value.
     * @return array<string,string>
     */
    public function hydrate(User $user, array $controls, ?string $source): array
    {
        // needed() only ever matches on `c:<identifier>`, so a source without
        // `c:` in it cannot name an expression control and does not even earn
        // the query. Most bot commands never mention a control at all.
        if ($source === null || ! str_contains($source, 'c:')) {
            return $controls;
        }

        $expressions = $this->expressionControls($user);

        if ($expressions->isEmpty()) {
            return $controls;
        }

        $needed = $this->needed($expressions, $source);

        // The hot path: a template naming no expression control pays one
        // indexed query and nothing else. No sidecar call, no Helix read.
        if ($needed === []) {
            return $controls;
        }

        $data = $this->context->for($user);
        $evaluated = [];

        foreach ($needed as $identifier) {
            $this->evaluate($identifier, $expressions, $data, $evaluated, 0);
        }

        foreach ($evaluated as $identifier => $value) {
            $controls[$identifier] = $value;
        }

        return $controls;
    }

    /**
     * Every expression control the user owns, keyed the way a `c:` tag names
     * it - broadcastKey for a service-managed control, key otherwise. Same
     * rule ControlSnapshot uses, so the keys line up with `$controls`.
     *
     * @return Collection<string,OverlayControl>
     */
    private function expressionControls(User $user): Collection
    {
        return OverlayControl::where('user_id', $user->id)
            ->where('type', 'expression')
            ->get()
            ->keyBy(fn (OverlayControl $c): string => $c->tagIdentifier());
    }

    /**
     * Which expression controls the source names. A plain substring test on
     * `c:<identifier>`: it deliberately over-matches rather than parsing, so
     * an expression named inside a conditional, a pipe or a `??` default is
     * still found. Over-matching costs one wasted evaluation; missing one
     * brings the bug back.
     *
     * @param  Collection<string,OverlayControl>  $expressions
     * @return list<string>
     */
    private function needed(Collection $expressions, string $source): array
    {
        $needed = [];

        foreach ($expressions->keys() as $identifier) {
            if (str_contains($source, 'c:'.$identifier)) {
                $needed[] = $identifier;
            }
        }

        return $needed;
    }

    /**
     * Depth-first, so an expression built on another expression sees the
     * fresh value rather than the stale scalar sitting on the row.
     *
     * @param  Collection<string,OverlayControl>  $expressions
     * @param  array<string,string>  $data  Sidecar context, mutated as values resolve.
     * @param  array<string,string>  $evaluated  identifier => value, memoized.
     */
    private function evaluate(string $identifier, Collection $expressions, array &$data, array &$evaluated, int $depth): void
    {
        if (isset($evaluated[$identifier]) || $depth >= self::MAX_DEPTH) {
            return;
        }

        $control = $expressions->get($identifier);

        if (! $control instanceof OverlayControl) {
            return;
        }

        $expression = (string) ($control->config['expression'] ?? '');

        if ($expression === '') {
            return;
        }

        // Claim the slot before recursing: a cycle that slipped past the
        // save-time check would otherwise re-enter this identifier forever.
        $evaluated[$identifier] = (string) ($control->value ?? '');

        foreach ((array) ($control->config['dependencies'] ?? []) as $dependency) {
            if (is_string($dependency) && $expressions->has($dependency)) {
                $this->evaluate($dependency, $expressions, $data, $evaluated, $depth + 1);
            }
        }

        $value = $this->engine->evaluate($expression, $data);

        // Sidecar down or expression invalid: keep the stored scalar, which
        // is what this surface printed before the hydrator existed.
        if ($value === null) {
            return;
        }

        $evaluated[$identifier] = $value;
        $data['c:'.$control->broadcastKey()] = $value;
    }
}
