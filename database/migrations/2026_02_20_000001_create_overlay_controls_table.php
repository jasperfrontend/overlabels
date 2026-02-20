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
        Schema::create('overlay_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overlay_template_id')->constrained('overlay_templates')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('label', 100)->nullable();
            $table->string('type', 20); // text|number|counter|timer|datetime
            $table->text('value')->nullable();
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['overlay_template_id', 'key']);
            $table->index(['overlay_template_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overlay_controls');
    }
};
