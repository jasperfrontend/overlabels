<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_meta_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Without leading "!". Default "list" - streamer can rename to
            // anything that matches the command-slug pattern (e.g. "l",
            // "queue", "ll"). Same per-user uniqueness as bot_commands /
            // bot_expressions / recipe_chat_triggers / list_appenders -
            // resolution order enforced by BotCommandController is:
            //   builtin > expression > recipe_trigger > list_append > list_meta
            $table->string('command', 30)->default('list');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();

            // One !list per user. The action vocabulary is platform-fixed;
            // the command name is the only thing that varies per streamer.
            $table->unique('user_id');
            $table->index(['user_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_meta_commands');
    }
};
