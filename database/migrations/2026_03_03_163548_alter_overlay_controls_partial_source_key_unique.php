<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The previous (user_id, source, key) constraint was intended only for user-scoped
     * controls (overlay_template_id IS NULL), but was a full-table constraint. This
     * prevented adding the same source+key preset to more than one template.
     * Replace it with a partial unique index scoped to user-scoped rows only.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE overlay_controls DROP CONSTRAINT overlay_controls_user_source_key_unique');

        DB::statement('
            CREATE UNIQUE INDEX overlay_controls_user_source_key_unique
            ON overlay_controls (user_id, source, key)
            WHERE overlay_template_id IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX overlay_controls_user_source_key_unique');

        DB::statement('
            ALTER TABLE overlay_controls
            ADD CONSTRAINT overlay_controls_user_source_key_unique
            UNIQUE (user_id, source, key)
        ');
    }
};
