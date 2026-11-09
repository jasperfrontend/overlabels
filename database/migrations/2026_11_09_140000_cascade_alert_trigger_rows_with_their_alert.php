<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A trigger row now goes with its alert.
 *
 * Both mapping tables pointed at their alert with nullOnDelete, so deleting an
 * alert left its triggers behind with no alert: rows the Triggers page hides
 * (`whereNotNull`), alert resolution skips and nothing can ever use again.
 * Prod had 27 of them across 4 accounts on 2026-11-09. Nothing creates a
 * trigger without an alert on purpose, so the empty ones are cleared and the
 * foreign keys cascade from now on.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_template_mappings')->whereNull('template_id')->delete();
        DB::table('external_event_template_mappings')->whereNull('overlay_template_id')->delete();

        Schema::table('event_template_mappings', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->foreign('template_id')->references('id')->on('overlay_templates')->cascadeOnDelete();
        });

        Schema::table('external_event_template_mappings', function (Blueprint $table) {
            $table->dropForeign(['overlay_template_id']);
            $table->foreign('overlay_template_id')->references('id')->on('overlay_templates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // The deleted rows pointed at nothing and are not brought back.
        Schema::table('event_template_mappings', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->foreign('template_id')->references('id')->on('overlay_templates')->nullOnDelete();
        });

        Schema::table('external_event_template_mappings', function (Blueprint $table) {
            $table->dropForeign(['overlay_template_id']);
            $table->foreign('overlay_template_id')->references('id')->on('overlay_templates')->nullOnDelete();
        });
    }
};
