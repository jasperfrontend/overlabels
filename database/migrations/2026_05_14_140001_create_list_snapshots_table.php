<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_snapshots', function (Blueprint $table) {
            $table->id();
            // The list this snapshot was taken from. Cascade so deleting
            // a list wipes its snapshot trail too. Note: this means
            // restoring snapshots after the parent list is deleted isn't
            // possible. If we ever want orphan-survivable snapshots,
            // soft-delete the list instead of hard-delete.
            $table->foreignId('list_id')
                ->constrained('option_sets')
                ->cascadeOnDelete();
            // Full items array at the moment of the snapshot. Stored
            // verbatim - lists are lists. JSON-encoded.
            $table->jsonb('items');
            // Why this snapshot exists. Useful for the dashboard UI to
            // explain "this snapshot was taken right before clear" and
            // for filtering ("show me only manual saves").
            $table->string('reason', 30);
            // Who triggered this snapshot - the streamer themselves
            // (manual / via UI action button), or a chat-driven action
            // (then this is the streamer's own user_id, because actions
            // run on behalf of the streamer regardless of who in chat
            // typed the command). Null only for system-driven retention
            // jobs that won't exist in v1.
            $table->foreignId('triggered_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // Pinned snapshots survive the 30-day retention sweep. UI
            // lets the streamer pin "the raffle pool from the charity
            // stream" forever.
            $table->boolean('pinned')->default(false);
            $table->timestamp('created_at');

            $table->index(['list_id', 'created_at']);
            $table->index('pinned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_snapshots');
    }
};
