<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasColumn('users', 'twitch_data')) {
            return;
        }

        DB::statement('ALTER TABLE users ALTER COLUMN twitch_data TYPE jsonb USING twitch_data::jsonb');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasColumn('users', 'twitch_data')) {
            return;
        }

        DB::statement('ALTER TABLE users ALTER COLUMN twitch_data TYPE json USING twitch_data::json');
    }
};
