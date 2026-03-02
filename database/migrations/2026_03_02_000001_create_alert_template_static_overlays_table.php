<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_template_static_overlays', function (Blueprint $table) {
            $table->id();
            // The alert template (type=alert)
            $table->foreignId('alert_template_id')
                  ->constrained('overlay_templates')->cascadeOnDelete();
            // The static overlay it should fire on (type=static)
            $table->foreignId('static_overlay_id')
                  ->constrained('overlay_templates')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['alert_template_id', 'static_overlay_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_template_static_overlays');
    }
};
