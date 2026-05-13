<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_appenders', function (Blueprint $table) {
            $table->id();
            // Owner. Cascade so deleting a user wipes their appenders.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The list this command appends to. Cascade so deleting a list
            // takes its append commands with it (their target is gone).
            $table->foreignId('target_list_id')
                ->constrained('option_sets')
                ->cascadeOnDelete();
            // Chat command without leading "!". Same shape + namespace as
            // bot_commands.command, bot_expressions.command, and
            // recipe_chat_triggers.command - resolution order on
            // collision is enforced by BotCommandController, not here.
            $table->string('command', 30);
            $table->string('permission_level', 20)->default('everyone');
            $table->unsignedInteger('cooldown_seconds')->default(0);
            // Bot Expression template syntax. Resolves at fire time via
            // BotExpressionResolver. Empty string is intentionally
            // allowed - lists are lists, the streamer might want empty
            // entries for some reason we don't get to police.
            $table->string('value_template', 500)->default('[[[bot:from_user]]]');
            // Sent to bot_chat_outbox when {args} resolves empty AND the
            // template references it. NULL = silent refusal. Also
            // resolved via BotExpressionResolver so it can include
            // chatter context like "@[[[bot:from_user]]] add something!".
            $table->string('args_empty_reply', 500)->nullable();
            // 'none' | 'per_chatter' | 'per_chatter_per_stream'
            $table->string('dedup_policy', 30)->default('per_chatter');
            // Null = unlimited. When hit, fire silently refuses.
            $table->unsignedInteger('max_size')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'command']);
            $table->index(['user_id', 'enabled']);
            $table->index('target_list_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_appenders');
    }
};
