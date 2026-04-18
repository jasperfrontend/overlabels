<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chat Castle game session table.
 * Minimal shape for the bot ingress endpoint. Room state and player position
 * columns are added in a later migration once the resolver lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('waiting');
            $table->unsignedInteger('current_round')->default(0);
            $table->unsignedInteger('player_hp')->default(0);
            $table->timestamp('round_started_at')->nullable();
            $table->timestamps();
        });

        // Only one in-flight game per streamer at a time.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX games_user_id_active_unique
            ON games (user_id)
            WHERE status IN ('waiting', 'running')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
