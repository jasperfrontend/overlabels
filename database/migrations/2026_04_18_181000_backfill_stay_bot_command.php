<?php

use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed !s (stay) for every opted-in streamer. It was added to handlers.js
 * and the controller earlier but never reached BotCommand::DEFAULTS, so the
 * bot's commandMap.lookup() returned null and new streamers' !s calls were
 * silently dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::where('bot_enabled', true)
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    BotCommand::firstOrCreate(
                        ['user_id' => $user->id, 'command' => 's'],
                        ['permission_level' => 'everyone', 'enabled' => true],
                    );
                }
            });
    }

    public function down(): void
    {
        BotCommand::where('command', 's')->delete();
    }
};
