<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('twitch_events', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('user_id');
        });

        // Backfill: match broadcaster_user_id (or to_broadcaster_user_id for raids) to users.twitch_id
        DB::statement("
            UPDATE twitch_events
            SET user_id = users.id
            FROM users
            WHERE twitch_events.user_id IS NULL
              AND (
                  users.twitch_id = twitch_events.event_data->>'broadcaster_user_id'
                  OR users.twitch_id = twitch_events.event_data->>'to_broadcaster_user_id'
              )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('twitch_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
