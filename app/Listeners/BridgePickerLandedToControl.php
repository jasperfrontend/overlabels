<?php

namespace App\Listeners;

use App\Events\ControlValueUpdated;
use App\Events\PickerLanded;
use App\Models\OverlayControl;
use App\Models\Picker;

/**
 * Mirrors a PickerLanded event into the overlay_controls layer for the
 * Recipe instance that owns the picker. Walks the manifest's
 * control_exports to find which export names map to this picker, updates
 * the corresponding overlay_control rows, and dispatches
 * ControlValueUpdated so the existing overlay Echo subscription picks
 * them up just like a service-managed control.
 *
 * No-op for primitive-only pickers (no recipe_instance_id) - those exist
 * for power users and don't need the control bridge.
 */
class BridgePickerLandedToControl
{
    public function handle(PickerLanded $event): void
    {
        /** @var Picker|null $picker */
        $picker = Picker::with('recipeInstance.recipe', 'user:id,twitch_id')
            ->find($event->pickerId);

        if (! $picker || ! $picker->recipe_instance_id) {
            return;
        }

        $instance = $picker->recipeInstance;
        $recipe = $instance?->recipe;
        if (! $instance || ! $recipe) {
            return;
        }

        $manifest = $recipe->manifest;
        $exports = $manifest['control_exports'] ?? [];

        $pickerRef = $this->refForPickerId($instance->primitive_map, $picker->id);
        if ($pickerRef === null) {
            return;
        }

        foreach ($exports as $export) {
            $expected = "pickers.{$pickerRef}.";
            if (! is_string($export['from'] ?? null) || ! str_starts_with($export['from'], $expected)) {
                continue;
            }

            $field = substr($export['from'], strlen($expected));
            $value = match ($field) {
                'result' => $event->result,
                'result_index' => (string) $event->resultIndex,
                'result_at' => (string) $event->resultAt,
                'running' => $picker->is_running ? '1' : '0',
                default => null,
            };
            if ($value === null) {
                continue;
            }

            /** @var OverlayControl|null $control */
            $control = OverlayControl::where('recipe_instance_id', $instance->id)
                ->where('key', $export['name'])
                ->first();

            if (! $control) {
                continue;
            }

            $control->value = $value;
            $control->save();

            ControlValueUpdated::dispatch(
                '',
                $control->broadcastKey(),
                $control->type,
                $value,
                (string) $event->broadcasterId,
            );
        }
    }

    /**
     * @param  array{option_sets?: array<string, int>, pickers?: array<string, int>}  $primitiveMap
     */
    private function refForPickerId(array $primitiveMap, int $pickerId): ?string
    {
        foreach ($primitiveMap['pickers'] ?? [] as $ref => $id) {
            if ((int) $id === $pickerId) {
                return $ref;
            }
        }

        return null;
    }
}
