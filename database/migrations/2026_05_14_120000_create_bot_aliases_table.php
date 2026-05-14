<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('command', 30);
            // Rewritten command incl. positional placeholders, e.g. "!increment wins {1}".
            $table->string('target_template', 200);
            $table->string('permission_level', 20)->default('moderator');
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->boolean('enabled')->default(true);
            $table->boolean('hidden_from_commands')->default(false);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'command']);
            $table->index(['user_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_aliases');
    }
};
