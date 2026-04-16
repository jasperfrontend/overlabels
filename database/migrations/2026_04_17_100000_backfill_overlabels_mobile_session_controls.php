<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill the new per-session GPS controls (gps_session_distance,
 * gps_session_max_speed, gps_session_avg_speed, gps_session_duration) for
 * every existing overlabels-mobile integration. New connections pick these
 * up automatically via getAutoProvisionedControls(), but existing users
 * need the rows created explicitly.
 *
 * Idempotent via firstOrCreate — safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $newControls = [
            ['key' => 'gps_session_distance', 'type' => 'number', 'label' => 'GPS Session Distance (km)', 'value' => '0'],
            ['key' => 'gps_session_max_speed', 'type' => 'number', 'label' => 'GPS Session Max Speed (m/s)', 'value' => '0'],
            ['key' => 'gps_session_avg_speed', 'type' => 'number', 'label' => 'GPS Session Avg Speed (m/s)', 'value' => '0'],
            ['key' => 'gps_session_duration', 'type' => 'number', 'label' => 'GPS Session Duration (seconds)', 'value' => '0'],
        ];

        ExternalIntegration::where('service', 'overlabels-mobile')
            ->get()
            ->each(function (ExternalIntegration $integration) use ($newControls) {
                $user = User::find($integration->user_id);
                if (! $user) {
                    return;
                }

                foreach ($newControls as $control) {
                    OverlayControl::provisionServiceControl($user, 'overlabels-mobile', $control);
                }
            });
    }

    public function down(): void
    {
        OverlayControl::where('source', 'overlabels-mobile')
            ->whereIn('key', [
                'gps_session_distance',
                'gps_session_max_speed',
                'gps_session_avg_speed',
                'gps_session_duration',
            ])
            ->delete();
    }
};
