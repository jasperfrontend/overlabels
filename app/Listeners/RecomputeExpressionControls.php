<?php

namespace App\Listeners;

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Controls\ExpressionEngineClient;
use Illuminate\Support\Facades\Log;

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
 * Scope limit (v1): builds the data context from the user's
 * `overlay_controls` table only. Expressions that reference `t.<tag>`
 * (Twitch template-tag values) will see those as undefined and evaluate
 * accordingly. Real Twitch tag data lookup is a follow-up.
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

        $this->walk($event, $user, 0, []);
    }

    /**
     * @param  array<int,bool>  $visited  Expression Control IDs already
     *                                    recomputed in this cascade (DAG
     *                                    safety net).
     */
    private function walk(ControlValueUpdated $event, User $user, int $depth, array $visited): void
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

        $data = $this->buildDataContext($user);

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
            $this->walk($cascadeEvent, $user, $depth + 1, $visited);
        }
    }

    /**
     * Build the flat key->value map the sidecar expects. Keyed by
     * "c:<broadcastKey>" for every control the user owns. Service-managed
     * controls give "c:kofi:donations_received" etc.; user-authored
     * controls give "c:wins" etc.
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

        return $data;
    }
}
