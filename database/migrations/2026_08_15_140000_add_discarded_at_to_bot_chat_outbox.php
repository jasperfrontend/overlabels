<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat replies are perishable. A message queued while the bot was down is
     * worthless by the time it comes back, so the claim path now drops stale
     * rows instead of posting them into a conversation that has moved on.
     *
     * Dropped rows are marked rather than deleted so the behaviour is visible:
     * "did the bot eat my message" is answerable for as long as the prune keeps
     * them, and a sudden run of discards is the clearest signal that the bot is
     * flapping. Nothing else would show that.
     */
    public function up(): void
    {
        Schema::table('bot_chat_outbox', function (Blueprint $table) {
            $table->timestamp('discarded_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('bot_chat_outbox', function (Blueprint $table) {
            $table->dropColumn('discarded_at');
        });
    }
};
