<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The local GeoNames gazetteer behind !checkin place resolution.
 *
 * `geo_places` is one row per city from the GeoNames cities500 dump (every
 * city on earth with population >= 500), imported by `php artisan geo:import`.
 * Global shared data - no user FKs, no timestamps, refreshed by re-running
 * the import (upsert on geonames_id).
 *
 * `geo_place_names` is the search surface: one row per searchable name per
 * place (primary name, ASCII name, and ASCII-safe alternate names such as
 * "den haag" for The Hague), normalized to lowercase ASCII. Exact lookups hit
 * the btree index; typo tolerance ("amsterdamm") comes from the pg_trgm GIN
 * index. pg_trgm is a trusted extension since PG 13, so the app role may
 * create it without superuser. The extension statements are pgsql-only so a
 * stray sqlite environment can still migrate (it just gets no fuzzy match).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_places', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('geonames_id')->unique();
            $table->string('name');
            $table->string('ascii_name');
            $table->double('lat');
            $table->double('lng');
            $table->string('country_code', 2);
            $table->unsignedBigInteger('population')->default(0);

            $table->index('country_code');
        });

        Schema::create('geo_place_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geo_place_id')->constrained('geo_places')->cascadeOnDelete();
            $table->string('name_normalized');

            $table->unique(['geo_place_id', 'name_normalized']);
            $table->index('name_normalized');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX geo_place_names_name_normalized_trgm ON geo_place_names USING gin (name_normalized gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_place_names');
        Schema::dropIfExists('geo_places');
    }
};
