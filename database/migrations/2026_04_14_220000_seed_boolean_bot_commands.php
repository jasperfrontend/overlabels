<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill enable/disable/toggle defaults for users who opted into the bot
 * before those commands existed.
 *
 * Rewritten Aug 2026; unchanged for anyone who has already run it. It used to
 * call BotCommand::seedDefaults(), and both halves of that name moved out from
 * under it: the method lives on BotBuiltin now, and the bot_commands table it
 * wrote to was renamed bot_builtins while a different table took the vacated
 * name. A migration that reaches through a model gets whatever that class means
 * today, not what it meant when the migration was written, so this one names
 * its table and its command set literally and stays put.
 *
 * The frozen list is what DEFAULTS held on 2026-04-14. Calling the live seeder
 * from a slot dated in April would seed every builtin added since, none of
 * which existed yet at this point in the chain.
 */
return new class extends Migration
{
    /** command => permission_level, as of 2026-04-14. */
    private const COMMANDS = [
        'control' => 'everyone',
        'set' => 'moderator',
        'increment' => 'moderator',
        'decrement' => 'moderator',
        'reset' => 'broadcaster',
        'enable' => 'moderator',
        'disable' => 'moderator',
        'toggle' => 'moderator',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('users')
            ->where('bot_enabled', true)
            ->chunkById(200, function ($users) use ($now) {
                $rows = [];

                foreach ($users as $user) {
                    foreach (self::COMMANDS as $command => $permission) {
                        $rows[] = [
                            'user_id' => $user->id,
                            'command' => $command,
                            'permission_level' => $permission,
                            'enabled' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                // Unique on (user_id, command), so this is the same idempotency
                // firstOrCreate gave us, in one statement per chunk.
                DB::table('bot_commands')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        // Deliberately empty. These commands are in BotBuiltin::DEFAULTS, so
        // UserObserver recreates them the moment anyone opts in - deleting them
        // here would desync users from the seeder rather than reverse anything.
    }
};
