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
        // Deliberately empty, and it has to stay that way.
        //
        // This used to be BotCommand::whereIn('command', [...])->delete(). When it
        // ran, App\Models\BotCommand pointed at the per-user builtin registry, so
        // that deleted exactly the rows up() created. The Aug 2026 rename moved
        // that table to bot_builtins and handed the bot_commands name to the
        // user-authored custom commands, which means the same line now reaches a
        // completely different table and would delete real user content.
        //
        // Retargeting it at BotBuiltin would be safe but still wrong: followage
        // and accountage are in BotBuiltin::DEFAULTS now, so every opted-in user
        // is entitled to those rows regardless of this migration. Deleting them
        // on rollback would just desync them from the seeder that puts them back.
        // Nothing to undo.
    }
};
