<?php

use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed !castlehelp (everyone) for every opted-in streamer so the bot will
 * dispatch it. Bot-side returns a pointer to /help/gamejam.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::where('bot_enabled', true)
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    BotCommand::firstOrCreate(
                        ['user_id' => $user->id, 'command' => 'castlehelp'],
                        ['permission_level' => 'everyone', 'enabled' => true],
                    );
                }
            });
    }

    public function down(): void
    {
        BotCommand::where('command', 'castlehelp')->delete();
    }
};
