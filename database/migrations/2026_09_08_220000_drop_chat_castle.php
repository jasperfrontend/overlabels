<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chat Castle is removed. This drops its seven tables, deletes the six bot
 * verbs it registered for every opted-in streamer, and clears the per-user
 * debug flag it kept in the cache.
 *
 * Children before parent: every game_* table has a game_id FK to games with
 * cascade, and nothing outside the game points at any of them. games.user_id
 * pointed at users; dropping games removes that constraint with the table.
 *
 * The nine migrations that built this schema, and the two that seeded
 * castlehelp and s, are deleted from the tree in the same commit. A fresh
 * database therefore never creates these tables, and dropIfExists makes this
 * migration a no-op there. On a database that has them, the rows go with the
 * tables and cannot be brought back; that is the point.
 *
 * bot_builtins is the builtin registry today. The April seeders that wrote
 * these verbs say bot_commands, which was the same table before the
 * 2026-08-15 swap; a migration names the table as of its own date.
 */
return new class extends Migration
{
    private const TABLES = [
        'game_zombies',
        'game_blockers',
        'game_hiding_spots',
        'game_doors',
        'game_hidden_tiles',
        'game_joiners',
        'games',
    ];

    private const VERBS = ['join', 'p', 'h', 'a', 's', 'castlehelp'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('bot_builtins')) {
            DB::table('bot_builtins')->whereIn('command', self::VERBS)->delete();
        }

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->whereNotNull('twitch_id')
                ->select('twitch_id')
                ->orderBy('id')
                ->chunk(500, function ($users) {
                    foreach ($users as $user) {
                        Cache::forget('gamejam.debug.'.$user->twitch_id);
                    }
                });
        }
    }

    public function down(): void
    {
        // Deliberately empty. The schema this dropped no longer exists in the
        // tree and the rows are gone; there is nothing to reverse into.
    }
};
