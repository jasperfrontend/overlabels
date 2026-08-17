<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed !followage + !accountage for every opted-in streamer. These are
 * builtin commands; the bot-side handlers POST to /api/internal/bot/
 * followage and /accountage respectively and speak the returned reply
 * inline. permission_level 'everyone' because viewers are the primary
 * audience - if a streamer wants to lock them down they can edit the
 * row from the bot settings UI.
 *
 * This migration is only half the job, and the other half was missing for three
 * months: neither command was added to DEFAULTS, so every streamer who opted in
 * after this ran had no row and got silence in chat. Fixed on 2026-08-17 by the
 * backfill migration of that date, which also carries the writeup.
 *
 * Rewritten Aug 2026 to name its table instead of reaching through
 * App\Models\BotCommand, which the rename repointed at a different table. See
 * the 2026-04-14 seed for the full note.
 */
return new class extends Migration
{
    private const COMMANDS = ['followage', 'accountage'];

    public function up(): void
    {
        $now = now();

        DB::table('users')
            ->where('bot_enabled', true)
            ->chunkById(200, function ($users) use ($now) {
                $rows = [];

                foreach ($users as $user) {
                    foreach (self::COMMANDS as $command) {
                        $rows[] = [
                            'user_id' => $user->id,
                            'command' => $command,
                            'permission_level' => 'everyone',
                            'enabled' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                DB::table('bot_commands')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        // Deliberately empty, and it has to stay that way.
        //
        // This used to be BotCommand::whereIn('command', [...])->delete(). When
        // it ran, App\Models\BotCommand pointed at the per-user builtin
        // registry, so that deleted exactly the rows up() created. The Aug 2026
        // rename moved that table to bot_builtins and handed the bot_commands
        // name to the user-authored custom commands, which means the same line
        // now reaches a completely different table and would delete real user
        // content.
        //
        // Retargeting it at BotBuiltin would be safe but still wrong: followage
        // and accountage are in BotBuiltin::DEFAULTS now, so every opted-in user
        // is entitled to those rows regardless of this migration. Deleting them
        // on rollback would just desync them from the seeder that puts them
        // straight back. Nothing to undo.
    }
};
