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
        Schema::table('bot_expressions', function (Blueprint $table) {
            // When set, a scheduled sweep (bot:sweep-destroyed) deletes the
            // expression once this timestamp passes. Nullable = no timer.
            $table->timestamp('destroy_at')->nullable()->after('last_fired_at');
            $table->index('destroy_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_expressions', function (Blueprint $table) {
            $table->dropIndex(['destroy_at']);
            $table->dropColumn('destroy_at');
        });
    }
};
