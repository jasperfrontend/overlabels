<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Originally seeded the Chat Castle verbs join/p/h/a into every opted-in
 * streamer's command table. Chat Castle was removed on 2026-09-08 and those
 * four keys were struck from the frozen list below; the teardown migration of
 * that date deletes the rows this once wrote. What remains re-states the
 * April 14th eight, which is harmless and keeps this slot in the sequence.
 *
 * Rewritten Aug 2026 for the same reason as the 2026-04-14 seed above it: this
 * called BotCommand::seedDefaults(), and that method moved to BotBuiltin while
 * the table underneath was renamed. See that file for the full note.
 *
 * The earlier eight are already in place by now; insertOrIgnore makes
 * re-stating them free.
 */
return new class extends Migration
{
    /** command => permission_level, as of 2026-04-18. */
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
