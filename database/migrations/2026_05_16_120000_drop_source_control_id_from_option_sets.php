<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The list->control binding lived briefly on option_sets.source_control_id
 * (2026-05-15) before being replaced the next day by the list_writer
 * control type, which stores the binding on the control row's config
 * instead (config.source_control_id + config.target_list_id).
 *
 * Why the unwind: Controls are the user-facing primitive that lives on
 * an overlay template. Treating the binding as a Control makes it
 * editable from the same dashboard surface that creates every other
 * control, and lets the binding survive across templates / be cloned /
 * be exported in recipe manifests the same way other controls are. The
 * column on option_sets was redundant once that became clear.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->dropUnique(['source_control_id']);
            $table->dropConstrainedForeignId('source_control_id');
        });
    }

    public function down(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->foreignId('source_control_id')
                ->nullable()
                ->after('expires_at')
                ->constrained('overlay_controls')
                ->nullOnDelete();
            $table->unique('source_control_id');
        });
    }
};
