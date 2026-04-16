<?php

use App\Models\ExternalIntegration;
use App\Models\OverlayControl;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill gps_accuracy on every existing overlabels-mobile integration.
 * The payload always carried an accuracy float; we just never exposed it
 * as a control. New connections pick it up via getAutoProvisionedControls();
 * existing users need the row explicitly.
 *
 * Idempotent via firstOrCreate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $preset = [
            'key' => 'gps_accuracy',
            'type' => 'number',
            'label' => 'GPS Accuracy (meters)',
            'value' => '0',
        ];

        ExternalIntegration::where('service', 'overlabels-mobile')
            ->get()
            ->each(function (ExternalIntegration $integration) use ($preset) {
                $user = User::find($integration->user_id);
                if (! $user) {
                    return;
                }
                OverlayControl::provisionServiceControl($user, 'overlabels-mobile', $preset);
            });
    }

    public function down(): void
    {
        OverlayControl::where('source', 'overlabels-mobile')
            ->where('key', 'gps_accuracy')
            ->delete();
    }
};
