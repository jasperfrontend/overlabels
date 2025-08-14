<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_template_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type')->index();
            $table->foreignId('template_id')->nullable()->constrained('overlay_templates')->nullOnDelete();
            $table->integer('duration_ms')->default(5000);
            $table->string('transition_type')->default('fade');
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'event_type']);
            $table->index(['user_id', 'enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_template_mappings');
    }
};
