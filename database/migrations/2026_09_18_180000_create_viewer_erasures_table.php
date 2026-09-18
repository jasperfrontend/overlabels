<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Viewers who have asked not to be stored.
 *
 * This is the one table that exists to hold a viewer's identifier on purpose,
 * and the paradox is deliberate: to reliably forget someone you have to
 * remember that you were asked to. It holds a Twitch id and a date. Nothing
 * else - no name, no login, no channel, no reason.
 *
 * Deleting someone's rows is not enough on its own, because the next `!checkin`
 * puts them straight back. The ingest paths check this table first.
 *
 * Platform-wide by design. A viewer typing !forgetme in one channel means it
 * everywhere, because they are asking US, not the streamer whose chat they
 * happened to be in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viewer_erasures', function (Blueprint $table) {
            $table->id();
            $table->string('twitch_id')->unique();
            $table->timestamp('erased_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viewer_erasures');
    }
};
