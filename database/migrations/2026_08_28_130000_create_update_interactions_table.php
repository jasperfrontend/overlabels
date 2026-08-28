<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per user per update they have interacted with on the What's New card.
 *
 * Two independent facts live here, which is why this is not an
 * `update_dismissals` table with a boolean. `visited_at` means the user landed
 * on the page the update points at, however they got there - the row goes grey
 * but stays on the card. `dismissed_at` means they cleared it and it is gone.
 * A row can carry either, both, or be created by whichever happens first.
 *
 * Neither column is a count. Both are timestamps because `dismissed_at` groups
 * a "mark all as seen" press into a batch, which is what Undo reverses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('update_id')->constrained()->cascadeOnDelete();
            $table->timestamp('visited_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'update_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_interactions');
    }
};
