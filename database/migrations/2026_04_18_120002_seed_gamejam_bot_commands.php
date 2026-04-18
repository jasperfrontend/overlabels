<?php

use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed join/p/h/a into every opted-in streamer's command table so the bot
 * will dispatch them. firstOrCreate inside seedDefaults keeps it idempotent.
 */
return new class extends Migration
{
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
        BotCommand::whereIn('command', ['join', 'p', 'h', 'a'])->delete();
    }
};
