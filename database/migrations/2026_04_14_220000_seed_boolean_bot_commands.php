<?php

use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill enable/disable/toggle defaults for users who opted into the bot
     * before those commands existed. BotCommand::seedDefaults is idempotent via
     * firstOrCreate, so re-running it on every opted-in user is safe and only
     * inserts the rows that weren't there before.
     */
    public function up(): void
    {
        User::where('bot_enabled', true)
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    BotCommand::seedDefaults($user);
                }
            });
    }

    public function down(): void
    {
        BotCommand::whereIn('command', ['enable', 'disable', 'toggle'])->delete();
    }
};
