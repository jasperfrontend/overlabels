<?php

namespace App\Services\External;

use App\Contracts\ExternalServiceDriver;
use App\Events\ControlValuesBatchUpdated;
use App\Events\ControlValueUpdated;
use App\Events\MapPositionBroadcast;
use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\MapSlugService;
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
     * Reset every service-managed control for a source back to defaults and
     * broadcast each change. `total_received` is special-cased: if the user
     * has saved a starting amount via {@see seedTotalReceived()}, that value
     * sticks instead of dropping to 0, so a streamer's donation-goal seed
     * survives a test-mode toggle. All other counter/number controls reset
     * to '0'; text controls reset to ''.
     */
    public function resetServiceManagedControls(User $user, string $source, ?string $seedValue = null): void
    {
        $controls = OverlayControl::where('user_id', $user->id)
            ->where('source', $source)
            ->where('source_managed', true)
            ->with('template')
            ->get();

        foreach ($controls as $control) {
            $resetValue = match ($control->key) {
                'total_received' => $seedValue ?? '0',
                default => in_array($control->type, ['counter', 'number'], true) ? '0' : '',
            };

            $control->update(['value' => $resetValue]);

            $overlaySlug = $control->overlay_template_id
                ? ($control->template?->slug ?? '')
                : '';

            ControlValueUpdated::dispatch(
                $overlaySlug,
                $control->broadcastKey(),
                $control->type,
                $resetValue,
                $user->twitch_id,
            );
        }
    }

    /**
     * Seed the running total on the source's `total_received` service-managed
     * control. Used when a streamer raised some money before connecting and
     * wants their donation goal to start partway, e.g. €30 already in.
     */
    public function seedTotalReceived(User $user, string $source, int|string $value): void
    {
        OverlayControl::where('user_id', $user->id)
            ->where('source', $source)
            ->where('key', 'total_received')
            ->where('source_managed', true)
            ->update(['value' => (string) $value]);
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
     * Apply control updates from a normalized event and broadcast the changes.
     * $updates is a map of control key => new value (or ['action' => 'increment']).
     *
     * All control updates from one event (e.g. a GPS ping touching ~11 keys, each
     * duplicated across overlays) are collapsed into a SINGLE
     * {@see ControlValuesBatchUpdated} broadcast instead of one per control
     * instance - that per-instance dispatch is what fanned a single ping out to
     * ~50 broadcasts. The public `map.{slug}` feed stays per-key (it's minimal
     * and unmetered).
     */
    public function applyUpdates(User $user, string $service, array $updates): void
    {
        // Streamers who opt into public map sharing get a parallel broadcast on
        // the public `map.{slug}` channel for GPS controls only. Computed
        // once per call so the per-control loop doesn't reload it.
        $mapSharingEnabled = $service === 'gps'
            && $this->isMapSharingEnabled($user);

        $mapSlug = $mapSharingEnabled
            ? app(MapSlugService::class)->encode($user->twitch_id)
            : null;

        $batch = [];

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

                $batch[] = [
                    'overlay_slug' => $overlaySlug,
                    'key' => $control->broadcastKey(),
                    'type' => $control->type,
                    'value' => $newValue,
                ];

                if ($mapSharingEnabled && $mapSlug !== null && self::isMapSharedKey($key)) {
                    MapPositionBroadcast::dispatch(
                        $mapSlug,
                        $key,
                        $newValue,
                    );
                }
            }
        }

        if (! empty($batch)) {
            ControlValuesBatchUpdated::dispatch($user->twitch_id, $batch);
        }
    }

    /**
     * Allowlist of GPS control keys safe to broadcast on the public
     * `map.{slug}` channel. Anything not on this list (including battery,
     * accuracy, distance, donor info, etc.) stays private.
     */
    private static function isMapSharedKey(string $key): bool
    {
        return in_array($key, [
            'lat',
            'lng',
            'speed',
            'bearing',
            'tracking',
        ], true);
    }

    private function isMapSharingEnabled(User $user): bool
    {
        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', 'gps')
            ->where('enabled', true)
            ->first();

        if (! $integration) {
            return false;
        }

        return (bool) (($integration->settings ?? [])['map_sharing_enabled'] ?? false);
    }
}
