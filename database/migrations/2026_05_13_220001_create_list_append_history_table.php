<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_append_history', function (Blueprint $table) {
            $table->id();
            // The appender that fired. Cascade so deleting an appender
            // wipes its dedup ledger too.
            $table->foreignId('list_appender_id')
                ->constrained('list_appenders')
                ->cascadeOnDelete();
            // Denormalised list id so dedup-across-appenders ("did this
            // chatter contribute to THIS list via any appender?") can be
            // a one-table query if we ever want it.
            $table->foreignId('target_list_id')
                ->constrained('option_sets')
                ->cascadeOnDelete();
            // Twitch numeric user id. Stored as string for parity with
            // the rest of the platform (twitch ids are 64-bit and PHP
            // stores them as strings).
            $table->string('chatter_id', 50);
            // Login at fire time. Useful for audit even if the chatter
            // later renames - audit reads what they were called when
            // they fired, not their current login.
            $table->string('chatter_login', 50);
            // The resolved string that landed in the list. Stored so we
            // can answer "what did alice actually submit?" without
            // replaying the resolver.
            $table->text('value');
            // Scopes per_chatter_per_stream dedup. NULL means stream was
            // offline at fire time, in which case dedup falls back to
            // per_chatter (lifetime).
            $table->foreignId('stream_session_id')
                ->nullable()
                ->constrained('stream_sessions')
                ->nullOnDelete();
            $table->timestamp('fired_at');

            // Append-only - no updated_at.
            $table->index(['list_appender_id', 'chatter_id']);
            $table->index(['target_list_id', 'chatter_id']);
            $table->index('fired_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_append_history');
    }
};
