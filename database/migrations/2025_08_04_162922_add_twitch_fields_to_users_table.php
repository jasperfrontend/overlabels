<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only add columns that don't already exist
            if (!Schema::hasColumn('users', 'twitch_id')) {
                $table->string('twitch_id')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable();
            }

            if (!Schema::hasColumn('users', 'access_token')) {
                $table->text('access_token')->nullable();
            }

            if (!Schema::hasColumn('users', 'refresh_token')) {
                $table->text('refresh_token')->nullable();
            }

            if (!Schema::hasColumn('users', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'twitch_data')) {
                $table->json('twitch_data')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'twitch_id',
                'avatar',
                'access_token',
                'refresh_token',
                'token_expires_at',
                'twitch_data'
            ]);
        });
    }
};
