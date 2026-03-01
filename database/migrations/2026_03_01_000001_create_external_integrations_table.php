<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('service', 50); // 'kofi', 'throne', etc.
            $table->uuid('webhook_token')->unique(); // URL routing key
            $table->text('credentials')->nullable(); // encrypted JSON
            $table->json('settings')->nullable(); // {enabled_events: [...], ...}
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'service']);
            $table->index('webhook_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_integrations');
    }
};
