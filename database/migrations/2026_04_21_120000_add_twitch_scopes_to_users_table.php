<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'twitch_scopes')) {
                // Null = unknown (legacy user), array = authoritative granted scopes.
                $table->json('twitch_scopes')->nullable()->after('twitch_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'twitch_scopes')) {
                $table->dropColumn('twitch_scopes');
            }
        });
    }
};
