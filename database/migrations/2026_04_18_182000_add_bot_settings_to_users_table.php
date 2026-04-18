<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user JSONB bag for bot toggles. First key is `controls_enabled` -
 * when false (or absent) the bot rejects every controls command, so
 * chatters can't guess-and-check key names to manipulate overlay state.
 * JSONB over a dedicated column so future toggles piggyback without
 * another migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('bot_settings')->nullable()->after('bot_enabled');
        });

        DB::statement("UPDATE users SET bot_settings = '{}'::jsonb WHERE bot_settings IS NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bot_settings');
        });
    }
};
