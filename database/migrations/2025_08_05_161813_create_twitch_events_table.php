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
        Schema::create('twitch_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->json('event_data');
            $table->timestamp('twitch_timestamp');
            $table->boolean('processed')->default(false);
            $table->timestamps();

            // Add index for faster queries
            $table->index('event_type');
            $table->index('processed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twitch_events');
    }
};
