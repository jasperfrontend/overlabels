<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed !s (stay) for every opted-in streamer. It was added to handlers.js and
 * the controller earlier but never reached DEFAULTS, so the bot's
 * commandMap.lookup() returned null and new streamers' !s calls were silently
 * dropped.
 *
 * That paragraph describes the bug twice over: !s was the first time a builtin
 * shipped without a DEFAULTS entry, !followage and !accountage were the second,
 * three months later, found the same way - a streamer reporting silence. A
 * backfill migration only ever fixes the users who already exist. DEFAULTS is
 * what fixes the ones who arrive tomorrow, and both edits have to land together.
 *
 * Rewritten Aug 2026 to name its table instead of reaching through
 * App\Models\BotCommand, which the rename repointed at a different table. See
 * the 2026-04-14 seed for the full note.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('users')
            ->where('bot_enabled', true)
            ->chunkById(200, function ($users) use ($now) {
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = [
                        'user_id' => $user->id,
                        'command' => 's',
                        'permission_level' => 'everyone',
                        'enabled' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('bot_commands')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        // Deliberately empty. !s is in BotBuiltin::DEFAULTS, so UserObserver
        // recreates it the moment anyone opts in - deleting it here would
        // desync users from the seeder rather than reverse anything.
    }
};
