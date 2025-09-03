<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('eventsub_connected_at')->nullable()->after('access_token_expires_at');
            $table->boolean('eventsub_auto_connect')->default(true)->after('eventsub_connected_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['eventsub_connected_at', 'eventsub_auto_connect']);
        });
    }
};