<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_template_mappings', function (Blueprint $table) {
            $table->string('transition_in')->default('fade')->after('duration_ms');
            $table->string('transition_out')->default('fade')->after('transition_in');
        });

        DB::statement("UPDATE event_template_mappings SET transition_in = transition_type, transition_out = transition_type");

        Schema::table('event_template_mappings', function (Blueprint $table) {
            $table->dropColumn('transition_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_template_mappings', function (Blueprint $table) {
            $table->string('transition_type')->default('fade')->after('duration_ms');
        });

        DB::statement("UPDATE event_template_mappings SET transition_type = transition_in");

        Schema::table('event_template_mappings', function (Blueprint $table) {
            $table->dropColumn(['transition_in', 'transition_out']);
        });
    }
};
