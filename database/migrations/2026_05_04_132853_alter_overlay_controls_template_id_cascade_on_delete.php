<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Switching from nullOnDelete to cascadeOnDelete to fix template deletion
// hitting the partial unique index `overlay_controls_user_source_key_unique`
// (user_id, source, key) WHERE overlay_template_id IS NULL. Per-template
// service-managed rows (e.g. a streamelements donations_received preset
// added to a template) were being demoted to template_id=NULL on delete,
// colliding with the user-scoped row auto-provisioned at integration connect.
// Orphaned (template_id=NULL, source=NULL) rows are unreachable from every
// UI - cascading is the intended semantic anyway.
return new class extends Migration
{
    public function up(): void
    {
        // Clean up any pre-existing orphan rows from past nullOnDelete runs.
        // These have no template AND no source, so they're invisible to every
        // UI and safe to delete.
        DB::table('overlay_controls')
            ->whereNull('overlay_template_id')
            ->whereNull('source')
            ->delete();

        Schema::table('overlay_controls', function (Blueprint $table) {
            $table->dropForeign(['overlay_template_id']);
            $table->foreign('overlay_template_id')
                ->references('id')
                ->on('overlay_templates')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('overlay_controls', function (Blueprint $table) {
            $table->dropForeign(['overlay_template_id']);
            $table->foreign('overlay_template_id')
                ->references('id')
                ->on('overlay_templates')
                ->nullOnDelete();
        });
    }
};
