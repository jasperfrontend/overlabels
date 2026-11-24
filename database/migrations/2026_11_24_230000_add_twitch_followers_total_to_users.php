<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each account's Twitch follower total, refreshed daily by
 * `twitch:refresh-follower-counts`, so /admin/users can show which channels
 * could drop a big raid's follow flood on the platform (#343). Null until the
 * first refresh, or when Twitch would not tell.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('twitch_followers_total')->nullable();
            $table->timestamp('twitch_followers_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['twitch_followers_total', 'twitch_followers_checked_at']);
        });
    }
};
