<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The answers an install gave to its recipe's ingredients, keyed by
 * ingredient key. The shipped manifest plus these answers reproduce what
 * was installed. Existing instances predate ingredients and answered
 * nothing, which the empty object says exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_instances', function (Blueprint $table) {
            $table->jsonb('ingredients')->default(DB::raw("'{}'::jsonb"));
        });
    }

    public function down(): void
    {
        Schema::table('recipe_instances', function (Blueprint $table) {
            $table->dropColumn('ingredients');
        });
    }
};
