<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_zombies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('room');
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->unsignedTinyInteger('prev_x');
            $table->unsignedTinyInteger('prev_y');
            $table->string('facing')->default('right');
            $table->unsignedInteger('hp');
            $table->unsignedInteger('max_hp');
            $table->unsignedInteger('damage');
            $table->string('kind')->default('regular');
            $table->string('brain_state')->default('drifting');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['game_id', 'room']);
            $table->index(['game_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_zombies');
    }
};
