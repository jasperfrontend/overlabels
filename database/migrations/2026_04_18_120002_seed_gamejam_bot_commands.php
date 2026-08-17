<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed join/p/h/a into every opted-in streamer's command table so the bot
 * will dispatch them.
 *
 * Rewritten Aug 2026 for the same reason as the 2026-04-14 seed above it: this
 * called BotCommand::seedDefaults(), and that method moved to BotBuiltin while
 * the table underneath was renamed. See that file for the full note.
 *
 * The frozen list is what DEFAULTS held on 2026-04-18 - the April 14th set plus
 * the four Chat Castle verbs this migration was written for. The earlier eight
 * are already in place by now; insertOrIgnore makes re-stating them free, and
 * stating them is what keeps this slot honest about what seedDefaults did here.
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
        'join' => 'everyone',
        'p' => 'everyone',
        'h' => 'everyone',
        'a' => 'everyone',
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
