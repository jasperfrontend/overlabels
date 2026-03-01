<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_event_template_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('service', 50);
            $table->string('event_type', 50);
            $table->foreignId('overlay_template_id')->nullable()->constrained('overlay_templates')->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'service', 'event_type']);
            $table->index(['user_id', 'service']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_event_template_mappings');
    }
};
