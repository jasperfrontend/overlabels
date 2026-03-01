<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('service', 50);
            $table->string('event_type', 50); // 'donation', 'subscription', 'shop_order'
            $table->string('message_id', 255); // service's dedup key
            $table->json('raw_payload');
            $table->json('normalized_payload')->nullable();
            $table->boolean('controls_updated')->default(false);
            $table->boolean('alert_dispatched')->default(false);
            $table->timestamp('created_at')->useCurrent(); // append-only — no updated_at

            // Global dedup: service-level IDs are globally unique
            $table->unique(['service', 'message_id']);
            $table->index(['user_id', 'service']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_events');
    }
};
