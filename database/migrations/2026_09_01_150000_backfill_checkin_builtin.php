<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give !checkin a bot_builtins row for every opted-in streamer, at everyone
 * tier. The other half of the two-edit rule: BotBuiltin::DEFAULTS covers
 * everyone who opts in from now on, this covers everyone who already has.
 * Shipping one without the other makes the command silent for the other group
 * with no error anywhere (the bot's dispatcher drops commands missing from
 * the map by design - !s in April and !followage/!accountage in May are the
 * worked examples).
 *
 * The command only does anything for channels that also connect the Chat
 * Checkin integration: without the integration row the endpoint replies null
 * and the bot stays silent, so seeding it everywhere is safe.
 *
 * Conflict skip: a streamer may already have claimed `checkin` as a custom
 * command, alias, recipe trigger, list appender or list meta-command, and
 * builtins outrank all of those in the command map. Skipping keeps the
 * command they wrote alive; they can delete it to get the builtin.
 *
 * Table names are literal, never Eloquent models - a migration is dated but
 * a model reference resolves against today's codebase.
 */
return new class extends Migration
{
    private const COMMAND = 'checkin';

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
                    if ($this->userHasOwnCommand($user->id, self::COMMAND)) {
                        $skipped[] = $user->id;

                        continue;
                    }

                    $rows[] = [
                        'user_id' => $user->id,
                        'command' => self::COMMAND,
                        'permission_level' => self::PERMISSION_LEVEL,
                        'enabled' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Unique on (user_id, command); insertOrIgnore keeps a re-run
                // and a partial apply harmless.
                $seeded += DB::table('bot_builtins')->insertOrIgnore($rows);
            });

        Log::info('Backfilled checkin builtin.', [
            'seeded' => $seeded,
            'skipped_user_owned' => $skipped,
        ]);
    }

    public function down(): void
    {
        // Not reversible. checkin is in BotBuiltin::DEFAULTS now, so
        // UserObserver seeds it on every opt-in; removing rows here would only
        // desync users from the seeder that puts them straight back.
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
