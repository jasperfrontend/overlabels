<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_doors', function (Blueprint $table) {
            $table->boolean('is_exit')->default(false)->after('turns_remaining');
        });

        Schema::table('game_hiding_spots', function (Blueprint $table) {
            $table->dropColumn('open_sides');
        });

        Schema::create('game_blockers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('room');
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->timestamps();

            $table->index(['game_id', 'room']);
            $table->unique(['game_id', 'room', 'x', 'y']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_blockers');

        Schema::table('game_hiding_spots', function (Blueprint $table) {
            $table->json('open_sides')->default(new Illuminate\Database\Query\Expression("'[]'"));
        });

        Schema::table('game_doors', function (Blueprint $table) {
            $table->dropColumn('is_exit');
        });
    }
};
