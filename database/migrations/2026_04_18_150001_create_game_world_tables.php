<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_hidden_tiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('room');
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->string('content');
            $table->json('payload')->nullable();
            $table->unsignedInteger('revealed_at_round')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'room']);
            $table->unique(['game_id', 'room', 'x', 'y']);
        });

        Schema::create('game_doors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('room');
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->string('state')->default('closed');
            $table->unsignedTinyInteger('turns_remaining')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'room']);
            $table->unique(['game_id', 'room', 'x', 'y']);
        });

        Schema::create('game_hiding_spots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('room');
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->json('open_sides')->default(new Illuminate\Database\Query\Expression("'[]'"));
            $table->timestamps();

            $table->index(['game_id', 'room']);
            $table->unique(['game_id', 'room', 'x', 'y']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_hiding_spots');
        Schema::dropIfExists('game_doors');
        Schema::dropIfExists('game_hidden_tiles');
    }
};
