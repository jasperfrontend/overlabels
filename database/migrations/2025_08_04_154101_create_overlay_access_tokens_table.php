<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overlay_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('token_hash', 64)->unique()->index();
            $table->string('token_prefix', 8)->index(); // For easy identification
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->integer('access_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->json('metadata')->nullable();
            $table->string('abilities', 500)->nullable(); // Comma-separated list of permissions
            $table->timestamps();

            // Composite indexes for performance
            $table->index(['user_id', 'is_active']);
            $table->index(['token_hash', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_access_tokens');
    }
};
