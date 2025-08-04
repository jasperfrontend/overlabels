<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overlay_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_id')->constrained('overlay_access_tokens')->cascadeOnDelete();
            $table->string('template_slug', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('accessed_at');

            $table->index(['token_id', 'accessed_at']);
            $table->index('template_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_access_logs');
    }
};
