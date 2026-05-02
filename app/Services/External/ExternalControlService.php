<?php

namespace App\Services\External;

use App\Contracts\ExternalServiceDriver;
use App\Events\ControlValueUpdated;
use App\Events\MapPositionBroadcast;
use App\Models\ExternalIntegration;
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
        // Streamers who opt into public map sharing get a parallel broadcast on
        // the public `map.{twitchId}` channel for GPS controls only. Computed
        // once per call so the per-control loop doesn't reload it.
        $mapSharingEnabled = $service === 'overlabels-mobile'
            && $this->isMapSharingEnabled($user);

        foreach ($updates as $key => $update) {
            $controls = OverlayControl::where('user_id', $user->id)
                ->where('source', $service)
                ->where('key', $key)
                ->where('source_managed', true)
                ->with('template')
                ->get();

            if ($controls->isEmpty()) {
                continue;
            }

            foreach ($controls as $control) {
                $action = is_array($update) ? ($update['action'] ?? null) : null;

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

                // For template-scoped controls, broadcast to the specific overlay slug.
                // For user-scoped (overlay_template_id=null), use empty slug so all overlays receive it.
                $overlaySlug = $control->overlay_template_id
                    ? ($control->template?->slug ?? '')
                    : '';

                ControlValueUpdated::dispatch(
                    $overlaySlug,
                    $control->broadcastKey(),
                    $control->type,
                    $newValue,
                    $user->twitch_id,
                );

                if ($mapSharingEnabled && self::isMapSharedKey($key)) {
                    MapPositionBroadcast::dispatch(
                        (string) $user->twitch_id,
                        $key,
                        $newValue,
                    );
                }

            }
        }
    }

    /**
     * Allowlist of overlabels-mobile control keys safe to broadcast on the
     * public `map.{twitchId}` channel. Anything not on this list (including
     * battery, accuracy, distance, donor info, etc.) stays private.
     */
    private static function isMapSharedKey(string $key): bool
    {
        return in_array($key, [
            'gps_lat',
            'gps_lng',
            'gps_speed',
            'gps_bearing',
            'gps_tracking',
        ], true);
    }

    private function isMapSharingEnabled(User $user): bool
    {
        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'overlabels-mobile')
            ->where('enabled', true)
            ->first();

        if (! $integration) {
            return false;
        }

        return (bool) (($integration->settings ?? [])['map_sharing_enabled'] ?? false);
    }
}
