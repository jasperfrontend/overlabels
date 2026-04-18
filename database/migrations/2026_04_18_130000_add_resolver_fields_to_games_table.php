<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('round_duration_seconds')->default(30)->after('player_hp');
            $table->string('last_resolved_action')->nullable()->after('round_started_at');
            $table->json('last_resolved_tally')->nullable()->after('last_resolved_action');
            $table->timestamp('last_resolved_at')->nullable()->after('last_resolved_tally');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'round_duration_seconds',
                'last_resolved_action',
                'last_resolved_tally',
                'last_resolved_at',
            ]);
        });
    }
};
