<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed !castlehelp (everyone) for every opted-in streamer so the bot will
 * dispatch it. Bot-side returns a pointer to /help/gamejam.
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
                        'command' => 'castlehelp',
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
        // Deliberately empty. !castlehelp is in BotBuiltin::DEFAULTS, so
        // UserObserver recreates it the moment anyone opts in - deleting it
        // here would desync users from the seeder rather than reverse anything.
    }
};
