<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Twitch retries a notification on any non-2xx or timeout, and every retry
 * carries the same Twitch-Eventsub-Message-Id. The row never stored it, so a
 * redelivery was a second row and a second alert. Nullable and unique: Postgres
 * allows any number of NULLs, so existing rows and synthetic test events are
 * untouched, and only a real redelivery collides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('twitch_events', function (Blueprint $table) {
            $table->string('message_id')->nullable()->after('event_type');
            $table->unique('message_id');
        });
    }

    public function down(): void
    {
        Schema::table('twitch_events', function (Blueprint $table) {
            $table->dropUnique(['message_id']);
            $table->dropColumn('message_id');
        });
    }
};
