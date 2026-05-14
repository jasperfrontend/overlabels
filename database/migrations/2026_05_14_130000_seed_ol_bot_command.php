<?php

use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed !ol (moderator) for every opted-in streamer so the bot will dispatch
 * the new chat-admin meta-command. Bot-side relays to /api/internal/bot/manage.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::where('bot_enabled', true)
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    BotCommand::firstOrCreate(
                        ['user_id' => $user->id, 'command' => 'ol'],
                        ['permission_level' => 'moderator', 'enabled' => true],
                    );
                }
            });
    }

    public function down(): void
    {
        BotCommand::where('command', 'ol')->delete();
    }
};
