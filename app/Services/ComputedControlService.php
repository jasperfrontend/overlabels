<?php

namespace App\Services;

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComputedControlService
{
    private const MAX_DEPTH = 5;

    /**
     * Evaluate a computed control's formula against a watched value.
     */
    public function evaluate(OverlayControl $computed, ?string $watchedValue): string
    {
        $formula = $computed->config['formula'] ?? [];
        $operator = $formula['operator'] ?? '==';
        $compareValue = $formula['compare_value'] ?? '';
        $thenValue = $formula['then_value'] ?? '';
        $elseValue = $formula['else_value'] ?? '';

        if ($watchedValue === null) {
            return $elseValue;
        }

        $result = $this->compare($watchedValue, $operator, $compareValue);

        return $result ? $thenValue : $elseValue;
    }

    /**
     * Compare two values using the given operator.
     * Uses numeric comparison when both sides are numeric, otherwise string comparison.
     */
    private function compare(string $left, string $operator, string $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            $l = (float) $left;
            $r = (float) $right;
        } else {
            $l = $left;
            $r = $right;
        }

        return match ($operator) {
            '==' => $l == $r,
            '!=' => $l != $r,
            '>' => $l > $r,
            '<' => $l < $r,
            '>=' => $l >= $r,
            '<=' => $l <= $r,
            default => false,
        };
    }

    /**
     * Cascade a control value change through dependent computed controls.
     * Called after any control value changes.
     */
    public function cascade(User $user, OverlayControl $changedControl, string $overlaySlug): void
    {
        $this->doCascade($user, $changedControl, $overlaySlug, [], 0);
    }

    private function doCascade(User $user, OverlayControl $changedControl, string $overlaySlug, array $visited, int $depth): void
    {
        if ($depth >= self::MAX_DEPTH) {
            Log::warning('Computed control cascade max depth reached', [
                'user_id' => $user->id,
                'control_id' => $changedControl->id,
                'depth' => $depth,
            ]);

            return;
        }

        $dependents = $this->findDependents($user, $changedControl);

        foreach ($dependents as $computed) {
            if (in_array($computed->id, $visited)) {
                continue;
            }

            $visited[] = $computed->id;

            $newValue = $this->evaluate($computed, $changedControl->value);

            if ($newValue === ($computed->value ?? '')) {
                continue;
            }

            DB::transaction(function () use ($computed, $newValue) {
                OverlayControl::where('id', $computed->id)->lockForUpdate()->first();
                $computed->update(['value' => $newValue]);
            });

            $computedSlug = $computed->overlay_template_id
                ? ($computed->template?->slug ?? '')
                : '';

            ControlValueUpdated::dispatch(
                $computedSlug,
                $computed->broadcastKey(),
                $computed->type,
                $newValue,
                $user->twitch_id,
            );

            $this->doCascade($user, $computed->fresh(), $computedSlug, $visited, $depth + 1);
        }
    }

    /**
     * Find all computed controls that depend on the given changed control.
     */
    private function findDependents(User $user, OverlayControl $changedControl): Collection
    {
        $watchKey = $changedControl->key;
        $watchSource = $changedControl->source;

        $query = OverlayControl::where('user_id', $user->id)
            ->where('type', 'computed')
            ->whereNotNull('config');

        // Scope: if changed control is template-scoped, check same template + user-scoped
        // If changed control is user-scoped, check all templates + user-scoped
        if ($changedControl->overlay_template_id) {
            $query->where(function ($q) use ($changedControl) {
                $q->where('overlay_template_id', $changedControl->overlay_template_id)
                    ->orWhereNull('overlay_template_id');
            });
        }

        return $query->with('template')->get()->filter(function (OverlayControl $ctrl) use ($watchKey, $watchSource) {
            $formula = $ctrl->config['formula'] ?? null;
            if (! $formula) {
                return false;
            }

            $fWatchKey = $formula['watch_key'] ?? null;
            $fWatchSource = $formula['watch_source'] ?? null;

            return $fWatchKey === $watchKey && ($fWatchSource ?: null) === ($watchSource ?: null);
        });
    }

    /**
     * Detect if saving this formula would create a circular dependency.
     * Returns true if a cycle is detected.
     */
    public function detectCycle(OverlayControl $control, array $formula, ?int $templateId): bool
    {
        $watchKey = $formula['watch_key'] ?? null;
        $watchSource = $formula['watch_source'] ?? null;

        if (! $watchKey) {
            return false;
        }

        return $this->dfsDetectCycle(
            $control->id,
            $control->user_id,
            $watchKey,
            $watchSource ?: null,
            $templateId,
            [],
            0
        );
    }

    private function dfsDetectCycle(int $originId, int $userId, string $watchKey, ?string $watchSource, ?int $templateId, array $visited, int $depth): bool
    {
        if ($depth >= self::MAX_DEPTH) {
            return false;
        }

        // Find the control being watched
        $query = OverlayControl::where('user_id', $userId)
            ->where('key', $watchKey);

        if ($watchSource) {
            $query->where('source', $watchSource);
        } else {
            $query->whereNull('source');
        }

        // Scope: look on same template + user-scoped
        if ($templateId) {
            $query->where(function ($q) use ($templateId) {
                $q->where('overlay_template_id', $templateId)
                    ->orWhereNull('overlay_template_id');
            });
        } else {
            $query->whereNull('overlay_template_id');
        }

        $watchedControls = $query->get();

        foreach ($watchedControls as $watched) {
            if ($watched->id === $originId) {
                return true;
            }

            if (in_array($watched->id, $visited)) {
                continue;
            }

            $visited[] = $watched->id;

            // If the watched control is itself computed, follow its dependency
            if ($watched->isComputed()) {
                $watchedFormula = $watched->config['formula'] ?? null;
                if ($watchedFormula) {
                    $nextKey = $watchedFormula['watch_key'] ?? null;
                    $nextSource = $watchedFormula['watch_source'] ?? null;

                    if ($nextKey && $this->dfsDetectCycle($originId, $userId, $nextKey, $nextSource ?: null, $watched->overlay_template_id ?? $templateId, $visited, $depth + 1)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get controls available as dependencies for a computed control.
     * Excludes timers, datetimes, and optionally self.
     */
    public function getAvailableControls(User $user, ?int $templateId, ?int $excludeId = null): Collection
    {
        $query = OverlayControl::where('user_id', $user->id)
            ->whereNotIn('type', ['timer', 'datetime']);

        if ($templateId) {
            $query->where(function ($q) use ($templateId) {
                $q->where('overlay_template_id', $templateId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('overlay_template_id')
                            ->where('source_managed', true);
                    });
            });
        } else {
            $query->whereNull('overlay_template_id');
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->orderBy('sort_order')->get();
    }
}
