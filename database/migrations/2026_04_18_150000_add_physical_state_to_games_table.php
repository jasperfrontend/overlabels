<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_room')->default(1)->after('current_round');
            $table->unsignedTinyInteger('player_x')->nullable()->after('current_room');
            $table->unsignedTinyInteger('player_y')->nullable()->after('player_x');
            $table->boolean('player_hiding_this_round')->default(false)->after('player_y');
            $table->string('weapon_slot_1')->default('fists')->after('player_hiding_this_round');
            $table->string('weapon_slot_2')->nullable()->after('weapon_slot_1');
            $table->unsignedInteger('weapon_slot_1_uses')->nullable()->after('weapon_slot_2');
            $table->boolean('wears_iron_fists')->default(false)->after('weapon_slot_1_uses');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'current_room',
                'player_x',
                'player_y',
                'player_hiding_this_round',
                'weapon_slot_1',
                'weapon_slot_2',
                'weapon_slot_1_uses',
                'wears_iron_fists',
            ]);
        });
    }
};
