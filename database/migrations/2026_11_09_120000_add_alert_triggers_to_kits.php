<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which event makes each alert in a kit fire, declared by the kit's author:
 * [{template_id, service, event_type, duration_ms}]. It is what the kit page
 * previews with, and what copying the kit wires up on the copier's Triggers.
 * Null for every kit made before this, which then previews from its author's
 * own Triggers instead. See Kit::alertTriggers().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kits', function (Blueprint $table) {
            $table->json('alert_triggers')->nullable()->after('is_starter_kit');
        });
    }

    public function down(): void
    {
        Schema::table('kits', function (Blueprint $table) {
            $table->dropColumn('alert_triggers');
        });
    }
};
