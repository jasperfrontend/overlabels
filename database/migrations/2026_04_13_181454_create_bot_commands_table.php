<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('command');
            $table->string('permission_level');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'command']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_commands');
    }
};
