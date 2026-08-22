<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give !ping a bot_builtins row for every opted-in streamer, at moderator tier.
 *
 * !ping never had one. It was the sole member of a hardcoded BUILTINS map in the
 * bot's registry.js, and the dispatcher checked that map before the command map,
 * so it ran for anyone who typed it without ever consulting a permission tier.
 * The `permission: 'moderator'` field on the bot's ping.js was read by nothing.
 *
 * The bot change that goes with this moves ping onto the same path as every
 * other builtin: command map lookup -> canRun(permission_level) -> handler. That
 * path needs a row here, or !ping goes silent instead of becoming mod-only.
 *
 * Ship order matters and it is app first. With this migration deployed and the
 * old bot still running, !ping keeps working for everyone exactly as it does
 * today - the row is simply unread. The reverse order gives every channel a dead
 * !ping for the length of the gap.
 *
 * Conflict skip: ping was not in DEFAULTS, so it was never in the
 * `reservedCommands` list BotCommandsController hands the UI, and a streamer was
 * free to claim the name with a custom command, alias, recipe trigger, list
 * appender or list meta-command. The hardcoded builtin shadowed those bot-side,
 * so they have been dead all along; seeding a builtin row here would keep them
 * that way. Skipping instead means the bot change hands those users the command
 * they actually wrote. They are logged below.
 *
 * Table names are literal here, not reached through Eloquent models, for the
 * reason the 2026-04-14 seed spells out: a model reference in a migration means
 * whatever that class points at today, and this file is expected to still run
 * correctly after the next rename.
 */
return new class extends Migration
{
    private const COMMAND = 'ping';

    private const PERMISSION_LEVEL = 'moderator';

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

                // Unique on (user_id, command). Nobody should have this row yet,
                // but insertOrIgnore keeps a re-run and a partial apply harmless.
                $seeded += DB::table('bot_builtins')->insertOrIgnore($rows);
            });

        Log::info('Backfilled ping builtin.', [
            'seeded' => $seeded,
            'skipped_user_owned' => $skipped,
        ]);
    }

    public function down(): void
    {
        // Not reversible. ping is in BotBuiltin::DEFAULTS now, so UserObserver
        // seeds it on every opt-in and on every save that flips bot_enabled.
        // Removing the rows here would only desync users from the seeder that
        // puts them straight back.
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
