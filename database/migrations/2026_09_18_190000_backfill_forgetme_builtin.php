<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give !forgetme a bot_builtins row for every opted-in streamer, at everyone
 * tier. The other half of the two-edit rule: BotBuiltin::DEFAULTS covers
 * everyone who opts in from now on, this covers everyone who already has.
 * Shipping one without the other makes the command silent for the other group
 * with no error anywhere, because the bot's dispatcher drops commands missing
 * from the map by design. That has happened twice before.
 *
 * Unlike the tower backfill this does NOT skip a user who has claimed the name
 * with something of their own, and does not check those tables at all. A
 * viewer's route to ask for deletion is not a name a channel gets to win: the
 * builtin outranks every user-owned type in the command map, which is the
 * intended outcome here rather than a collision to avoid. `forgetme` is also
 * not a name anybody is plausibly already using.
 *
 * Table names are literal, never Eloquent models - a migration is dated but a
 * model reference resolves against today's codebase.
 */
return new class extends Migration
{
    private const COMMAND = 'forgetme';

    private const PERMISSION_LEVEL = 'everyone';

    public function up(): void
    {
        $now = now();
        $seeded = 0;

        DB::table('users')
            ->where('bot_enabled', true)
            ->chunkById(200, function ($users) use ($now, &$seeded) {
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = [
                        'user_id' => $user->id,
                        'command' => self::COMMAND,
                        'permission_level' => self::PERMISSION_LEVEL,
                        'enabled' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows === []) {
                    return;
                }

                // Unique on (user_id, command); insertOrIgnore keeps a re-run
                // and a partial apply harmless.
                $seeded += DB::table('bot_builtins')->insertOrIgnore($rows);
            });

        Log::info('Backfilled forgetme builtin.', ['seeded' => $seeded]);
    }

    public function down(): void
    {
        // Not reversible. forgetme is in BotBuiltin::DEFAULTS now, so every
        // opt-in seeds it again; removing rows here would only desync users
        // from the seeder that puts them straight back.
    }
};
