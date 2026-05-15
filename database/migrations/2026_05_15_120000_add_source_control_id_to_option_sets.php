<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bind a List to a Control: every time the bound control fires a
 * ControlValueUpdated event, its new value gets appended to this list.
 * Unique constraint enforces 1-control-to-1-list - a control can feed
 * at most one list.
 *
 * Type=expression controls are not accepted as sources in v1 because
 * the expression engine (jsep) runs in the overlay only; the server
 * never sees the computed value to append. The model-level validator
 * rejects those bindings at write time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->foreignId('source_control_id')
                ->nullable()
                ->after('expires_at')
                ->constrained('overlay_controls')
                ->nullOnDelete();

            // 1-control-to-1-list. If a user wants multiple lists from one
            // source, that's a future feature (broadcast fan-out).
            $table->unique('source_control_id');
        });
    }

    public function down(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->dropUnique(['source_control_id']);
            $table->dropConstrainedForeignId('source_control_id');
        });
    }
};
