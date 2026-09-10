<?php

namespace App\Services\Controls;

use App\Models\OverlayControl;
use App\Models\User;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The flat key->value map the expression sidecar evaluates against.
 *
 * Keyed "c:<broadcastKey>" for every control the user owns, plus "t:<tag>"
 * for every Twitch template tag, so an expression can mix Helix data into
 * its formula the same way the overlay's local jsep evaluator can.
 *
 * Lifted out of RecomputeExpressionControls when ExpressionControlHydrator
 * needed the same map. Two builders would mean an expression evaluating one
 * way when a dependency changed and another way when a bot command read it,
 * which is the exact class of drift this project keeps paying for. One
 * builder, both callers.
 */
class ExpressionDataContext
{
    public function __construct(
        private readonly TwitchApiService $twitchService,
        private readonly TemplateDataMapperService $mapper,
    ) {}

    /**
     * @return array<string,string>
     */
    public function for(User $user): array
    {
        $data = [];

        $controls = OverlayControl::where('user_id', $user->id)
            ->get(['id', 'key', 'source', 'source_managed', 'value', 'type', 'config', 'recipe_instance_id', 'created_at', 'updated_at']);

        foreach ($controls as $control) {
            // tagIdentifier(), not broadcastKey(): a `c.` reference names an
            // own control by its bare key. The two coincide for every row that
            // exists today, so this changes nothing now and stops a control
            // with a source but no source_managed from silently resolving to 0.
            $key = 'c:'.$control->tagIdentifier();
            $data[$key] = (string) ($control->value ?? '');

            // The `_at` companion: when this control last changed, Unix
            // seconds, same rule and same fallback as the overlay payload
            // (OverlayTemplateController). Without it every `c.<key>_at`
            // reference evaluated server-side as undefined - and the engine
            // does not error on an unknown identifier, it coerces, so
            // `max(c.a_at, c.b_at)` quietly returned 0 and got STORED as the
            // control's value. Overlays were unaffected: they evaluate
            // locally against the payload, which has always carried these.
            $data[$key.'_at'] = (string) ($control->updated_at ?? $control->created_at)?->timestamp;
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
     * any `t.<tag>` reference evaluate to empty.
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
            Log::warning('[expression-context] Twitch data fetch failed; skipping t-tags', [
                'user_id' => $user->id,
                'err' => $e->getMessage(),
            ]);
        }
    }
}
