<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per viewer per channel: the viewer's current !checkin pin.
 *
 * Latest wins - a second !checkin moves the pin (upsert on the unique pair),
 * so this table holds current state, not history. History lives in
 * external_events like every other integration. Whether an overlay shows all
 * pins or only this stream's is a read filter on checked_in_at (the
 * pin_lifetime integration setting), never a second table.
 *
 * distance_km is stamped at checkin time against the streamer's home point
 * (checkin integration settings); it deliberately does not chase a later
 * home change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chatter_twitch_id', 32);
            $table->string('chatter_login', 64);
            $table->string('chatter_display_name', 64);
            $table->string('place_label');
            $table->string('country_code', 2);
            $table->double('lat');
            $table->double('lng');
            $table->double('distance_km')->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamps();

            $table->unique(['user_id', 'chatter_twitch_id']);
            $table->index(['user_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
