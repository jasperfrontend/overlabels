<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->string('alert_sound_url', 2048)->nullable()->after('tts_delay_ms');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->dropColumn('alert_sound_url');
        });
    }
};
