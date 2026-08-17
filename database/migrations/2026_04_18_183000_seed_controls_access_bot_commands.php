<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed !enablecontrols and !disablecontrols (broadcaster-only) for every
 * opted-in streamer so they can toggle chat control access without a web
 * visit. Default for the flag itself is "disabled" - see the User model
 * accessors; this just makes the commands exist.
 *
 * Rewritten Aug 2026 to name its table instead of reaching through
 * App\Models\BotCommand, which the rename repointed at a different table. See
 * the 2026-04-14 seed for the full note.
 */
return new class extends Migration
{
    private const COMMANDS = ['enablecontrols', 'disablecontrols'];

    public function up(): void
    {
        $now = now();

        DB::table('users')
            ->where('bot_enabled', true)
            ->chunkById(200, function ($users) use ($now) {
                $rows = [];

                foreach ($users as $user) {
                    foreach (self::COMMANDS as $command) {
                        $rows[] = [
                            'user_id' => $user->id,
                            'command' => $command,
                            'permission_level' => 'broadcaster',
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
        // Deliberately empty. Both commands are in BotBuiltin::DEFAULTS, so
        // UserObserver recreates them the moment anyone opts in - deleting them
        // here would desync users from the seeder rather than reverse anything.
    }
};
