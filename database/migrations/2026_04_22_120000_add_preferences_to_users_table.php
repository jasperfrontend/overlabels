<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('preferences')->default('{}')->after('role');
        });

        // Backfill: move every non-null locale into preferences->>locale.
        // Using a raw statement so we touch the jsonb column with the right operator.
        DB::statement(<<<'SQL'
            UPDATE users
            SET preferences = jsonb_set(
                COALESCE(preferences, '{}'::jsonb),
                '{locale}',
                to_jsonb(locale)
            )
            WHERE locale IS NOT NULL
        SQL);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->default('en-US')->after('role');
        });

        DB::statement(<<<'SQL'
            UPDATE users
            SET locale = COALESCE(preferences->>'locale', 'en-US')
        SQL);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};
