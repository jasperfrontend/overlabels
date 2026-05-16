<?php

namespace App\Listeners;

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Controls\ExpressionEngineClient;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side parity for Expression Controls.
 *
 * When any control fires `ControlValueUpdated`, find every Expression
 * Control owned by the same user whose `dependencies` list includes the
 * updated broadcast key, ship each to the expression-engine sidecar for
 * evaluation, store the new value on the row, and dispatch a cascade
 * `ControlValueUpdated` (with `alreadyRecomputed: true`) so other
 * listeners (list_writer, alerts, overlay broadcast) see the new value.
 *
 * The cascade is walked entirely inside one handler invocation. Cascade
 * events are flagged so this listener bails early when it receives one -
 * we've already walked the descendants in-process. Other listeners
 * (list_writer, alerts, the broadcast layer) ignore the flag.
 *
 * Termination is guaranteed by the cycle-detection check that runs at
 * Expression Control save time: the dependency graph is a DAG, so the
 * cascade is finite. MAX_DEPTH is defensive in case a row leaks through.
 *
 * Quiet on failure: if the sidecar is down or returns an error, the
 * recompute is skipped for that one expression. The overlay still
 * computes the value locally; persistence just doesn't refresh until
 * the next successful dep update.
 *
 * Data context: both `c:<broadcastKey>` (controls) and `t:<tagName>`
 * (Twitch template tags) get shipped to the sidecar so expressions like
 * `t.followers_total + c.bonus` evaluate the same way they would in the
 * overlay. Twitch data comes from the Helix cache via TwitchApiService -
 * if a user has no access_token or the fetch fails, t-tags evaluate to
 * empty (same fallback as the overlay sees while data loads).
 */
class RecomputeExpressionControls
{
    /**
     * Cascade depth cap. The save-time cycle check prevents real cycles,
     * but this caps the damage at 10 cascade steps in case the cycle
     * check ever leaks (e.g. via direct DB writes that bypass the
     * controller validators).
     */
    private const int MAX_DEPTH = 10;

    public function __construct(
        private readonly ExpressionEngineClient $engine,
        private readonly TwitchApiService $twitchService,
        private readonly TemplateDataMapperService $mapper,
    ) {}

    public function handle(ControlValueUpdated $event): void
    {
        // Cascade events flagged by this listener get short-circuited: the
        // descendants are already being walked in-process by the original
        // invocation. Other listeners (list_writer, overlay broadcast)
        // still see and process these events.
        if ($event->alreadyRecomputed) {
            return;
        }

        $user = User::where('twitch_id', $event->broadcasterId)->first();
        if (! $user) {
            return;
        }

        // Build the data context once at the top - both controls and Twitch
        // tags. Cascade steps mutate the local copy with newly-computed
        // expression values; nothing else changes within a single cascade.
        $data = $this->buildDataContext($user);

        $this->walk($event, $user, $data, 0, []);
    }

    /**
     * @param  array<string,string>  $data  Flat key->value context. Mutated
     *                                      in-place as we recompute and
     *                                      passed by reference into the
     *                                      recursive cascade so deeper
     *                                      steps see the just-computed
     *                                      values without an extra DB read.
     * @param  array<int,bool>  $visited  Expression Control IDs already
     *                                    recomputed in this cascade (DAG
     *                                    safety net).
     */
    private function walk(ControlValueUpdated $event, User $user, array &$data, int $depth, array $visited): void
    {
        if ($depth >= self::MAX_DEPTH) {
            Log::warning('[recompute-expression] max cascade depth reached', [
                'depth' => $depth,
                'key' => $event->key,
            ]);

            return;
        }

        $dependents = OverlayControl::where('user_id', $user->id)
            ->where('type', 'expression')
            ->whereJsonContains('config->dependencies', $event->key)
            ->get();

        if ($dependents->isEmpty()) {
            return;
        }

        foreach ($dependents as $expr) {
            if (isset($visited[$expr->id])) {
                continue;
            }
            $visited[$expr->id] = true;

            $expression = $expr->config['expression'] ?? '';
            if ($expression === '') {
                continue;
            }

            $newValue = $this->engine->evaluate($expression, $data);
            if ($newValue === null) {
                continue;
            }

            $previousValue = (string) ($expr->value ?? '');
            if ($newValue === $previousValue) {
                continue;
            }

            $expr->forceFill(['value' => $newValue])->save();
            $data['c:'.$expr->broadcastKey()] = $newValue;

            // Dispatch the cascade event with the recomputed flag so this
            // listener doesn't re-walk, but other listeners (list_writer,
            // overlay broadcast) still see the new value.
            ControlValueUpdated::dispatch(
                $event->overlaySlug,
                $expr->broadcastKey(),
                'expression',
                $newValue,
                $event->broadcasterId,
                null,
                $expression,
                null,
                true,
            );

            // Walk the next layer in-process. The dispatched event won't
            // re-trigger us; we own the cascade walk explicitly.
            $cascadeEvent = new ControlValueUpdated(
                $event->overlaySlug,
                $expr->broadcastKey(),
                'expression',
                $newValue,
                $event->broadcasterId,
                null,
                $expression,
                null,
                true,
            );
            $this->walk($cascadeEvent, $user, $data, $depth + 1, $visited);
        }
    }

    /**
     * Build the flat key->value map the sidecar expects. Keyed by
     * "c:<broadcastKey>" for every control the user owns, plus "t:<tag>"
     * for every Twitch template tag. The latter lets expressions mix
     * Helix data into their formulas server-side, the same way the
     * overlay's local jsep evaluator can.
     *
     * @return array<string,string>
     */
    private function buildDataContext(User $user): array
    {
        $data = [];

        $controls = OverlayControl::where('user_id', $user->id)
            ->get(['id', 'key', 'source', 'value', 'type', 'config', 'recipe_instance_id']);

        foreach ($controls as $control) {
            $broadcastKey = $control->broadcastKey();
            $data['c:'.$broadcastKey] = (string) ($control->value ?? '');
        }

        $this->addTwitchTagData($user, $data);

        return $data;
    }

    /**
     * Pull the user's cached Helix data through the standard mapper so the
     * tag names match what the overlay's `[[[tag]]]` rendering and the
     * Expression Control's `t.<tag>` reference would resolve to. Failure
     * here is non-fatal: missing access_token, expired token, or a Helix
     * outage all fall through to "no t-tags in context", which makes
     * any `t.<tag>` reference evaluate to empty for this cascade. Next
     * recompute will retry the fetch.
     *
     * @param  array<string,string>  $data  Mutated in-place.
     */
    private function addTwitchTagData(User $user, array &$data): void
    {
        if (! $user->access_token || ! $user->twitch_id) {
            return;
        }

        try {
            $twitchData = $this->twitchService->getExtendedUserData(
                $user->access_token,
                (string) $user->twitch_id,
            );
            // overlayName is only used by the mapper to scope `for_overlay`
            // tags; passing a stable placeholder is fine because we're not
            // rendering a specific overlay here. caps default per-user.
            $mapped = $this->mapper->mapForTemplate(
                $twitchData,
                'recompute',
                null,
                null,
                $user->foreachCaps(),
            );

            foreach ($mapped as $tag => $value) {
                if (! is_string($tag)) {
                    continue;
                }
                // Scalars only - arrays / objects in the mapped output
                // belong to foreach iteration paths the math engine
                // doesn't address anyway.
                if (is_array($value) || is_object($value)) {
                    continue;
                }
                $data['t:'.$tag] = (string) ($value ?? '');
            }
        } catch (Throwable $e) {
            Log::warning('[recompute-expression] Twitch data fetch failed; skipping t-tags', [
                'user_id' => $user->id,
                'err' => $e->getMessage(),
            ]);
        }
    }
}
