<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give !unforgetme a bot_builtins row for every opted-in streamer, at everyone
 * tier. The other half of the two-edit rule: BotBuiltin::DEFAULTS covers
 * everyone who opts in from now on, this covers everyone who already has.
 * Without it the command is silent for every existing channel, because the
 * bot's dispatcher drops commands missing from the map by design.
 *
 * Like the forgetme backfill it does not skip a channel that claimed the name
 * for something of its own: a viewer's way back from an opt-out is not a name
 * a channel gets to win.
 *
 * Table names are literal, never Eloquent models - a migration is dated but a
 * model reference resolves against today's codebase.
 */
return new class extends Migration
{
    private const COMMAND = 'unforgetme';

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

        Log::info('Backfilled unforgetme builtin.', ['seeded' => $seeded]);
    }

    public function down(): void
    {
        // Not reversible. unforgetme is in BotBuiltin::DEFAULTS, so every
        // opt-in seeds it again; removing rows here would only desync users
        // from the seeder that puts them straight back.
    }
};
