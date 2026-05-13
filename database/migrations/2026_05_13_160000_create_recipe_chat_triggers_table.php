<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_chat_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_instance_id')->constrained()->cascadeOnDelete();
            // Denormalised user_id so the bot commandMap query stays a single
            // scan over recipe_chat_triggers without joining recipe_instances.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('picker_id')->constrained()->cascadeOnDelete();
            // Command WITHOUT leading "!". Same shape as bot_commands.command
            // and bot_expressions.command (their seeders strip the !).
            $table->string('command', 30);
            $table->string('permission_level', 20)->default('everyone');
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->timestamp('last_fired_at')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'command']);
            $table->index(['user_id', 'enabled']);
            $table->index('picker_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_chat_triggers');
    }
};
