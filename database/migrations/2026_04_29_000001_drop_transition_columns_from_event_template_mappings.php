<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_template_mappings', function (Blueprint $table) {
            $table->dropColumn(['transition_in', 'transition_out']);
        });

        Schema::table('external_event_template_mappings', function (Blueprint $table) {
            $table->dropColumn(['transition_in', 'transition_out']);
        });
    }

    public function down(): void
    {
        Schema::table('event_template_mappings', function (Blueprint $table) {
            $table->string('transition_in')->default('fade')->after('duration_ms');
            $table->string('transition_out')->default('fade')->after('transition_in');
        });

        Schema::table('external_event_template_mappings', function (Blueprint $table) {
            $table->string('transition_in', 30)->default('fade')->after('duration_ms');
            $table->string('transition_out', 30)->default('fade')->after('transition_in');
        });
    }
};
