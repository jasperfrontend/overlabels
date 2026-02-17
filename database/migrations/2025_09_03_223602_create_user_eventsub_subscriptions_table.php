<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_eventsub_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('twitch_subscription_id')->unique(); // Twitch's subscription ID
            $table->string('event_type'); // e.g., 'channel.follow'
            $table->string('version'); // API version
            $table->string('status'); // enabled, webhook_callback_verification_failed, etc.
            $table->json('condition'); // Store the condition for reference
            $table->string('callback_url'); // The webhook URL used
            $table->timestamp('twitch_created_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_eventsub_subscriptions');
    }
};
