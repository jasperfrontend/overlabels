<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Items-as-objects, slice 3 tail. Drops the now-unreferenced
 * option_sets.item_added_at column. The prior migration
 * (convert_option_set_items_to_objects) deliberately kept it so the
 * still-string-era test suite stayed green while the mutators were rewired;
 * with every mutator, reader, and test now reading added_at from inside the
 * item object, the parallel array has no remaining reader and can go.
 *
 * down() re-adds the column and rebuilds it from each item's added_at, so a
 * rollback lands on a consistent {items, item_added_at} pair (the convert
 * migration's own down() then turns the objects back into strings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->dropColumn('item_added_at');
        });
    }

    public function down(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->jsonb('item_added_at')->default(DB::raw("'[]'::jsonb"))->after('items');
        });

        DB::table('option_sets')->orderBy('id')->select('id', 'items')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                $items = json_decode((string) ($row->items ?? '[]'), true);
                if (! is_array($items) || $items === []) {
                    continue;
                }
                $stamps = array_map(
                    static fn ($item): int => is_array($item) && is_numeric($item['added_at'] ?? null)
                        ? (int) $item['added_at']
                        : now()->getTimestamp(),
                    array_values($items),
                );
                DB::table('option_sets')->where('id', $row->id)->update([
                    'item_added_at' => json_encode($stamps),
                ]);
            }
        });
    }
};
