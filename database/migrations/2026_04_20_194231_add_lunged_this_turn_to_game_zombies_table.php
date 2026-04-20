<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_zombies', function (Blueprint $table) {
            $table->boolean('lunged_this_turn')->default(false)->after('brain_state');
        });
    }

    public function down(): void
    {
        Schema::table('game_zombies', function (Blueprint $table) {
            $table->dropColumn('lunged_this_turn');
        });
    }
};
