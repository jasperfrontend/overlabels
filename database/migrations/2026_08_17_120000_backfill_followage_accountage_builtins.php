<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give !followage and !accountage to the streamers who opted in after May 2026.
 *
 * The May migration backfilled both commands for everyone who had bot_enabled at
 * the time, but nobody added them to BotBuiltin::DEFAULTS, which is what
 * UserObserver seeds on opt-in. So every streamer who connected the bot after
 * 2026-05-20 got the other seventeen builtins and silently missed these two:
 * with no bot_builtins row the command never reaches the command map, and the
 * bot's dispatcher drops it before any handler runs. No error, no reply, nothing
 * in the logs - which is exactly how it was reported.
 *
 * DEFAULTS carries both now, so new opt-ins are covered. This closes the gap for
 * the users already in that window.
 *
 * Conflict skip: these two names were NOT in DEFAULTS, and DEFAULTS is what
 * BotCommandsController offers as `reservedCommands`, so for three months a
 * streamer was free to create their own !followage as a custom command, alias,
 * recipe trigger, list appender or list meta-command. Builtins outrank all five
 * in BotCommandMapController's resolution order, so seeding blindly would
 * silently shadow whatever they built. Those users keep their own version and
 * are logged below.
 *
 * Table names are literal here, not reached through Eloquent models, for the
 * reason the 2026-04-14 seed spells out: a model reference in a migration means
 * whatever that class points at today, and this file is expected to still run
 * correctly after the next rename.
 */
return new class extends Migration
{
    private const COMMANDS = ['followage', 'accountage'];

    /** Every table a user can claim a command name in, all of which builtins outrank. */
    private const USER_OWNED_TABLES = [
        'bot_commands',
        'bot_aliases',
        'recipe_chat_triggers',
        'list_appenders',
        'list_meta_commands',
    ];

    public function up(): void
    {
        $now = now();
        $seeded = 0;
        $skipped = [];

        DB::table('users')
            ->where('bot_enabled', true)
            ->chunkById(200, function ($users) use ($now, &$seeded, &$skipped) {
                $rows = [];

                foreach ($users as $user) {
                    foreach (self::COMMANDS as $command) {
                        if ($this->userHasOwnCommand($user->id, $command)) {
                            $skipped[] = "{$user->id}:{$command}";

                            continue;
                        }

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

                // Unique on (user_id, command), so users who already have the
                // row - everyone from before the May cutoff - are left alone.
                $seeded += DB::table('bot_builtins')->insertOrIgnore($rows);
            });

        Log::info('Backfilled followage/accountage builtins.', [
            'seeded' => $seeded,
            'skipped_user_owned' => $skipped,
        ]);
    }

    public function down(): void
    {
        // Not reversible. The rows are indistinguishable from the ones the May
        // migration created and from the ones UserObserver seeds on opt-in, and
        // both commands are in BotBuiltin::DEFAULTS now, so removing them would
        // only desync users from the seeder that puts them straight back.
    }

    /**
     * Has this user already claimed the name with something of their own?
     */
    private function userHasOwnCommand(int $userId, string $command): bool
    {
        foreach (self::USER_OWNED_TABLES as $table) {
            if (DB::table($table)->where('user_id', $userId)->where('command', $command)->exists()) {
                return true;
            }
        }

        return false;
    }
};
