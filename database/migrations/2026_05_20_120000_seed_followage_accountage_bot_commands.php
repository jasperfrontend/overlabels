<?php

use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed !followage + !accountage for every opted-in streamer. These are
 * builtin commands; the bot-side handlers POST to /api/internal/bot/
 * followage and /accountage respectively and speak the returned reply
 * inline. permission_level 'everyone' because viewers are the primary
 * audience - if a streamer wants to lock them down they can edit the
 * row from the bot settings UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::where('bot_enabled', true)
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    foreach (['followage', 'accountage'] as $command) {
                        BotCommand::firstOrCreate(
                            ['user_id' => $user->id, 'command' => $command],
                            ['permission_level' => 'everyone', 'enabled' => true],
                        );
                    }
                }
            });
    }

    public function down(): void
    {
        BotCommand::whereIn('command', ['followage', 'accountage'])->delete();
    }
};
