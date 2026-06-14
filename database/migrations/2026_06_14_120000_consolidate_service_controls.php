<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collapse duplicate per-overlay (template-scoped) service-managed controls
 * into the single user-scoped row for each (user, source, key).
 *
 * Service controls (GPS, donations) are now a user-scoped class: one row that
 * every overlay renders. Historically a user could ALSO add a per-overlay copy
 * of a service preset, so the same value lived on N rows and broadcast N times
 * - the fan-out this whole effort removes. This one-off cleanup removes the
 * duplicates so existing data matches the new model.
 *
 * Value precedence: the existing user-scoped row wins (it's the live one the
 * services update). When no user-scoped row exists, the freshest template-scoped
 * row is promoted to user-scoped (overlay_template_id = null) to preserve its
 * type/config, and the rest are deleted.
 *
 * Scoped to source_managed = true, a non-null source, and recipe_instance_id =
 * null (recipe controls compose their broadcastKey from the instance and are
 * not service controls). Idempotent: re-running finds no template-scoped
 * duplicates and does nothing.
 *
 * down() is intentionally a no-op: deleted per-overlay rows cannot be restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('overlay_controls')
            ->where('source_managed', true)
            ->whereNotNull('source')
            ->whereNotNull('overlay_template_id')
            ->whereNull('recipe_instance_id')
            ->get(['id', 'user_id', 'source', 'key', 'updated_at']);

        $groups = $duplicates->groupBy(fn ($row) => $row->user_id.'|'.$row->source.'|'.$row->key);

        foreach ($groups as $rows) {
            $first = $rows->first();

            $userScoped = DB::table('overlay_controls')
                ->where('user_id', $first->user_id)
                ->where('source', $first->source)
                ->where('key', $first->key)
                ->where('source_managed', true)
                ->whereNull('overlay_template_id')
                ->first();

            if ($userScoped) {
                // User-scoped row wins; drop every template-scoped duplicate.
                DB::table('overlay_controls')->whereIn('id', $rows->pluck('id'))->delete();

                continue;
            }

            // No user-scoped row: promote the freshest template-scoped row to
            // user-scoped and delete the remaining duplicates.
            $survivor = $rows->sortByDesc('updated_at')->first();

            DB::table('overlay_controls')
                ->where('id', $survivor->id)
                ->update(['overlay_template_id' => null, 'updated_at' => now()]);

            $drop = $rows->where('id', '!=', $survivor->id)->pluck('id');
            if ($drop->isNotEmpty()) {
                DB::table('overlay_controls')->whereIn('id', $drop)->delete();
            }
        }
    }

    public function down(): void
    {
        // Irreversible: the deleted per-overlay duplicates cannot be recreated.
    }
};
