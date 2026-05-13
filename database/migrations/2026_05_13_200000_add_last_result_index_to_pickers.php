<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickers', function (Blueprint $table) {
            // Numeric index of the last picked item in the linked OptionSet's
            // items array. Set alongside last_result so consumers (expression
            // engine, animations) can map a string result to a position
            // without doing the string->index lookup themselves.
            $table->integer('last_result_index')->nullable()->after('last_result');
        });
    }

    public function down(): void
    {
        Schema::table('pickers', function (Blueprint $table) {
            $table->dropColumn('last_result_index');
        });
    }
};
