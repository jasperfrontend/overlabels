<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            // Opt-in config that turns a List into a live "recent events"
            // feed (StreamElements-style widget). NULL = not a feed; the
            // list behaves exactly as before. Shape:
            //   { "enabled": bool, "types": string[] }
            // `types` is a whitelist of event_type strings (e.g.
            // "channel.follow", "donation"); an empty array means "every
            // event type". When enabled, EventFeedAppender appends a
            // formatted line for each matching Twitch / external event and
            // FIFO-drops past the list's max_items so the broadcast stays
            // under the Reverb payload cap.
            $table->jsonb('event_feed')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->dropColumn('event_feed');
        });
    }
};
