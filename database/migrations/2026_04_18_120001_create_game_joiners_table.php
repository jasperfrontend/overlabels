<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat Castle game roster.
 * One row per chat member who !joined. status enforces the pending -> active
 * -> inactive lifecycle described in docs/chat-castle-schema.md. current_vote
 * is a single string (e.g. "p:up", "h", "a:2") because votes are state, not
 * events - the resolver reads standing votes at round close.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_joiners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('twitch_user_id');
            $table->string('username');
            $table->string('status')->default('pending');
            $table->unsignedInteger('joined_round');
            $table->string('current_vote')->nullable();
            $table->unsignedInteger('last_vote_round')->nullable();
            $table->unsignedTinyInteger('blocks_remaining')->default(3);
            $table->boolean('hp_contributed')->default(true);
            $table->timestamps();

            $table->unique(['game_id', 'twitch_user_id']);
            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_joiners');
    }
};
