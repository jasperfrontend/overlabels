<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give !stack and !tower a bot_builtins row for every opted-in streamer, at
 * everyone tier. The other half of the two-edit rule: BotBuiltin::DEFAULTS
 * covers everyone who opts in from now on, this covers everyone who already
 * has. Shipping one without the other makes the command silent for the other
 * group with no error anywhere (the bot's dispatcher drops commands missing
 * from the map by design).
 *
 * The commands only do anything for channels that also connect the Chat
 * Tower integration: without the integration row the endpoint replies null
 * and the bot stays silent, so seeding them everywhere is safe.
 *
 * Conflict skip: a streamer may already have claimed `stack` or `tower` as a
 * custom command, alias, recipe trigger, list appender or list meta-command,
 * and builtins outrank all of those in the command map. Skipping keeps the
 * command they wrote alive; they can delete it to get the builtin.
 *
 * Table names are literal, never Eloquent models - a migration is dated but
 * a model reference resolves against today's codebase.
 */
return new class extends Migration
{
    private const COMMANDS = ['stack', 'tower'];

    private const PERMISSION_LEVEL = 'everyone';

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
                            'permission_level' => self::PERMISSION_LEVEL,
                            'enabled' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                // Unique on (user_id, command); insertOrIgnore keeps a re-run
                // and a partial apply harmless.
                $seeded += DB::table('bot_builtins')->insertOrIgnore($rows);
            });

        Log::info('Backfilled tower builtins.', [
            'seeded' => $seeded,
            'skipped_user_owned' => $skipped,
        ]);
    }

    public function down(): void
    {
        // Not reversible. stack and tower are in BotBuiltin::DEFAULTS now, so
        // UserObserver seeds them on every opt-in; removing rows here would
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
