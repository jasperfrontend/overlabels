<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'block' to the overlay_templates.type allowlist.
     *
     * The column was created with $table->enum(), which on Postgres is a
     * varchar + CHECK constraint (NOT a native PG enum), so this is a
     * transactional constraint swap. The constraint name is discovered at
     * runtime instead of hardcoded in case prod's name ever drifted.
     */
    public function up(): void
    {
        $this->swapTypeConstraint(['static', 'alert', 'block']);
    }

    public function down(): void
    {
        // Only safe when no block rows exist; down migrations are dev-only here.
        DB::table('overlay_templates')->where('type', 'block')->delete();
        $this->swapTypeConstraint(['static', 'alert']);
    }

    private function swapTypeConstraint(array $allowed): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $constraint = collect(DB::select(
                "select conname, pg_get_constraintdef(oid) as def
                 from pg_constraint
                 where conrelid = 'overlay_templates'::regclass and contype = 'c'"
            ))->first(fn ($row) => str_contains($row->def, "'static'") && str_contains($row->def, "'alert'"));

            if ($constraint) {
                DB::statement("alter table overlay_templates drop constraint \"{$constraint->conname}\"");
            }

            $list = implode(', ', array_map(fn ($v) => "'{$v}'::character varying", $allowed));
            DB::statement(
                "alter table overlay_templates add constraint overlay_templates_type_check
                 check (((type)::text = any ((array[{$list}])::text[])))"
            );

            return;
        }

        // sqlite (and anything else): rebuild the column, regenerating the inline CHECK.
        Schema::table('overlay_templates', function (Blueprint $table) use ($allowed) {
            $table->enum('type', $allowed)->default('static')->change();
        });
    }
};
