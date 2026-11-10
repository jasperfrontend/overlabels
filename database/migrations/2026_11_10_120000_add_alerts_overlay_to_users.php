<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Alerts overlay per account: the static overlay every untargeted alert
 * renders in, once OBS has loaded it. See App\Services\Alerts\AlertsOverlay.
 *
 * `alerts_overlay_live_at` is the moment OBS first rendered it. Until then
 * alerts keep showing in every static overlay, so creating the overlay can
 * never take alerts off someone's stream before they have added it to OBS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('alerts_overlay_id')->nullable()->constrained('overlay_templates')->nullOnDelete();
            $table->timestamp('alerts_overlay_live_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('alerts_overlay_id');
            $table->dropColumn('alerts_overlay_live_at');
        });
    }
};
