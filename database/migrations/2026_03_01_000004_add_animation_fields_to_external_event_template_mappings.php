<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_event_template_mappings', function (Blueprint $table) {
            $table->integer('duration_ms')->default(5000)->after('enabled');
            $table->string('transition_in', 30)->default('fade')->after('duration_ms');
            $table->string('transition_out', 30)->default('fade')->after('transition_in');
            $table->json('settings')->nullable()->after('transition_out');
        });
    }

    public function down(): void
    {
        Schema::table('external_event_template_mappings', function (Blueprint $table) {
            $table->dropColumn(['duration_ms', 'transition_in', 'transition_out', 'settings']);
        });
    }
};
