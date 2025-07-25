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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable();
            $table->string('access_token')->nullable();
            $table->string('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('twitch_data')->nullable(); // whole-ass object
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar',
                'access_token',
                'refresh_token',
                'token_expires_at',
                'twitch_data',
            ]);
        });
    }
};
