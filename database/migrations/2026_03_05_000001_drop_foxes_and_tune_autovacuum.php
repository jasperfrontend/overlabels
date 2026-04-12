<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('foxes');

        // Sessions gets frequent upserts and deletes (expired sessions), which
        // creates dead rows faster than PostgreSQL's default autovacuum threshold
        // (20% of table size). At low row counts the table never reaches the
        // threshold, so dead rows pile up. Setting a 1% scale factor means
        // autovacuum kicks in as soon as ~1 dead row exists.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                ALTER TABLE sessions
                SET (
                    autovacuum_vacuum_scale_factor = 0.01,
                    autovacuum_analyze_scale_factor = 0.01,
                    autovacuum_vacuum_cost_delay = 2
                )
            ');
        }
    }

    public function down(): void
    {
        Schema::create('foxes', function ($table) {
            $table->id();
            $table->string('api_url')->unique();
            $table->string('local_file');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sessions RESET (autovacuum_vacuum_scale_factor, autovacuum_analyze_scale_factor, autovacuum_vacuum_cost_delay)');
        }
    }
};
