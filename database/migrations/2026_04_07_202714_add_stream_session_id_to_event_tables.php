<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('twitch_events', function (Blueprint $table) {
            $table->foreignId('stream_session_id')->nullable()
                ->constrained('stream_sessions')->nullOnDelete();
            $table->index('stream_session_id');
        });

        Schema::table('external_events', function (Blueprint $table) {
            $table->foreignId('stream_session_id')->nullable()
                ->constrained('stream_sessions')->nullOnDelete();
            $table->index('stream_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('twitch_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stream_session_id');
        });

        Schema::table('external_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stream_session_id');
        });
    }
};
