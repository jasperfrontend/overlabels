<?php

namespace App\Services\External;

use App\Contracts\ExternalServiceDriver;
use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ExternalControlService
{
    /**
     * Auto-provision service-managed controls for a user when they connect an integration.
     * Idempotent: existing controls are not overwritten.
     */
    public function provision(User $user, ExternalServiceDriver $driver): array
    {
        $provisioned = [];

        foreach ($driver->getAutoProvisionedControls() as $def) {
            $control = OverlayControl::provisionServiceControl($user, $driver->getServiceKey(), $def);
            $provisioned[] = $control;
        }

        Log::info("Provisioned service controls for user {$user->id}", [
            'service' => $driver->getServiceKey(),
            'count' => count($provisioned),
        ]);

        return $provisioned;
    }

    /**
     * Remove all service-managed controls for a user (called on disconnect).
     */
    public function deprovision(User $user, string $service): int
    {
        $count = OverlayControl::where('user_id', $user->id)
            ->where('source', $service)
            ->where('source_managed', true)
            ->delete();

        Log::info("Deprovisioned service controls for user {$user->id}", [
            'service' => $service,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Apply control updates from a normalized event and broadcast each change.
     * $updates is a map of control key => new value (or ['action' => 'increment']).
     */
    public function applyUpdates(User $user, string $service, array $updates): void
    {
        foreach ($updates as $key => $update) {
            $control = OverlayControl::where('user_id', $user->id)
                ->where('source', $service)
                ->where('key', $key)
                ->where('source_managed', true)
                ->first();

            if (! $control) {
                continue;
            }

            $action = is_array($update) ? ($update['action'] ?? null) : null;
            $newValue = $update;

            if ($action === 'increment') {
                $step = (float) ($control->config['step'] ?? 1);
                $current = (float) ($control->value ?? 0);
                $newValue = (string) ($current + $step);
            } elseif ($action === 'add') {
                $current = (float) ($control->value ?? 0);
                $amount = (float) ($update['amount'] ?? 0);
                $newValue = (string) ($current + $amount);
            } else {
                $newValue = OverlayControl::sanitizeValue($control->type, $update);
            }

            $control->update(['value' => $newValue]);

            // Broadcast using the namespaced key (e.g. "kofi:kofis_received")
            // Empty overlay_slug = user-scoped; the renderer accepts it for all overlays.
            ControlValueUpdated::dispatch(
                '',
                $control->broadcastKey(),
                $control->type,
                $newValue,
                $user->twitch_id,
            );
        }
    }
}
