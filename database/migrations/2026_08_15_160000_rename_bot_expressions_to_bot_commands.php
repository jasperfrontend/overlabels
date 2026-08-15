<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Expression" meant five different things in this codebase. This migration
 * settles the two that were commands all along.
 *
 * The user-authored chat commands (bot_expressions) take the plain name
 * bot_commands. The table that already held it is the per-user registry of
 * which BUILT-IN verbs are on and at what tier, so it becomes bot_builtins -
 * which is what the wire has called those rows since day one
 * (BotCommandMapController emits type=builtin).
 *
 * Both renames run in one migration because they are a swap: bot_commands has
 * to vacate the name before bot_expressions can take it. Postgres does DDL
 * transactionally and Laravel wraps migrations, so this is atomic.
 *
 * Index and constraint names do NOT follow a table rename in Postgres, and
 * they share one schema-wide namespace. Leaving them would mean a future
 * $table->unique(['user_id', 'command']) on bot_commands colliding with the
 * identically named constraint still sitting on bot_builtins. So they move too.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Vacate the bot_commands name for the table that earns it.
        Schema::rename('bot_commands', 'bot_builtins');
        $this->renameConstraints('bot_builtins', [
            'bot_commands_pkey' => 'bot_builtins_pkey',
            'bot_commands_user_id_command_unique' => 'bot_builtins_user_id_command_unique',
            'bot_commands_user_id_foreign' => 'bot_builtins_user_id_foreign',
        ]);

        // 2. The custom commands take the plain name.
        Schema::rename('bot_expressions', 'bot_commands');
        $this->renameConstraints('bot_commands', [
            'bot_expressions_pkey' => 'bot_commands_pkey',
            'bot_expressions_user_id_command_unique' => 'bot_commands_user_id_command_unique',
            'bot_expressions_user_id_foreign' => 'bot_commands_user_id_foreign',
        ]);
        $this->renameIndexes([
            'bot_expressions_user_id_enabled_index' => 'bot_commands_user_id_enabled_index',
            'bot_expressions_destroy_at_index' => 'bot_commands_destroy_at_index',
        ]);

        // 3. `expression` held the templated text the bot speaks. Call it that.
        //    `hidden_from_commands` was named for the !commands listing it hides
        //    a row from; on a table now called bot_commands that reads circular.
        Schema::table('bot_commands', function (Blueprint $table) {
            $table->renameColumn('expression', 'reply');
            $table->renameColumn('hidden_from_commands', 'hidden');
        });

        // Aliases carry the same toggle and the docs promise the vocabularies
        // match one-for-one, so it moves with them.
        Schema::table('bot_aliases', function (Blueprint $table) {
            $table->renameColumn('hidden_from_commands', 'hidden');
        });
    }

    public function down(): void
    {
        Schema::table('bot_aliases', function (Blueprint $table) {
            $table->renameColumn('hidden', 'hidden_from_commands');
        });

        Schema::table('bot_commands', function (Blueprint $table) {
            $table->renameColumn('reply', 'expression');
            $table->renameColumn('hidden', 'hidden_from_commands');
        });

        $this->renameIndexes([
            'bot_commands_user_id_enabled_index' => 'bot_expressions_user_id_enabled_index',
            'bot_commands_destroy_at_index' => 'bot_expressions_destroy_at_index',
        ]);
        $this->renameConstraints('bot_commands', [
            'bot_commands_pkey' => 'bot_expressions_pkey',
            'bot_commands_user_id_command_unique' => 'bot_expressions_user_id_command_unique',
            'bot_commands_user_id_foreign' => 'bot_expressions_user_id_foreign',
        ]);
        Schema::rename('bot_commands', 'bot_expressions');

        $this->renameConstraints('bot_builtins', [
            'bot_builtins_pkey' => 'bot_commands_pkey',
            'bot_builtins_user_id_command_unique' => 'bot_commands_user_id_command_unique',
            'bot_builtins_user_id_foreign' => 'bot_commands_user_id_foreign',
        ]);
        Schema::rename('bot_builtins', 'bot_commands');
    }

    /**
     * Rename constraints only when present. A database restored from an older
     * dump, or one where a constraint was recreated by hand, can carry a
     * different name - that is not a reason to abort the whole rename.
     *
     * @param  array<string,string>  $names  old name => new name
     */
    private function renameConstraints(string $table, array $names): void
    {
        foreach ($names as $from => $to) {
            $exists = DB::selectOne(
                'select 1 from pg_constraint where conname = ? and conrelid = ?::regclass',
                [$from, $table]
            );

            if ($exists) {
                DB::statement("alter table \"{$table}\" rename constraint \"{$from}\" to \"{$to}\"");
            }
        }
    }

    /**
     * @param  array<string,string>  $names  old name => new name
     */
    private function renameIndexes(array $names): void
    {
        foreach ($names as $from => $to) {
            $exists = DB::selectOne('select 1 from pg_class where relname = ? and relkind = \'i\'', [$from]);

            if ($exists) {
                DB::statement("alter index \"{$from}\" rename to \"{$to}\"");
            }
        }
    }
};
