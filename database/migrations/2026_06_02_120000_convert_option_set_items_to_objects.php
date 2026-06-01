<?php

use App\Support\ListItems;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Items-as-objects, slice 2. Converts option_sets.items (and the parallel
 * list_snapshots.items) from flat string arrays into the rich object shape
 * built by App\Support\ListItems:
 *
 *   ["A","B"] + item_added_at:[t1,t2]
 *     -> [{id:1,value:"A",added_at:t1,label:null,weight:1,color:null},
 *         {id:2,value:"B",added_at:t2,...}]
 *
 * Adds option_sets.next_item_id (the per-list id counter). It deliberately
 * does NOT drop the now-redundant item_added_at column: the mutators, tests,
 * and readers that still reference it are rewired in slices 3-4, and a
 * RefreshDatabase test run executes this migration, so dropping the column
 * here would break the still-string-era suite. A small follow-up migration
 * drops item_added_at once nothing references it (end of slice 3). Until then
 * the column lingers, stale and ignored - the objects' added_at is the truth.
 *
 * Writes go through DB::table (json-encoded by hand) rather than the Eloquent
 * model on purpose: a later slice removes item_added_at from the model's
 * casts/fillable, and a model-based write would then mishandle the very
 * columns this migration converts. Reads are raw for the same reason. The
 * conversion is idempotent in both directions (guards on the current shape),
 * so a re-run or a half-applied state is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            // Per-list monotonic id counter: the id to assign to the NEXT
            // appended item. Starts at 1; never decremented, ids never reused.
            $table->unsignedInteger('next_item_id')->default(1)->after('items');
        });

        // option_sets: string items + parallel stamps -> object items.
        DB::table('option_sets')->orderBy('id')
            ->select('id', 'items', 'item_added_at', 'created_at')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $values = $this->decodeJson($row->items);
                    if ($this->isObjectShape($values)) {
                        continue; // already converted
                    }
                    $stamps = $this->decodeJson($row->item_added_at);
                    $built = ListItems::fromLegacy($values, $stamps, $this->fallbackTs($row->created_at));

                    DB::table('option_sets')->where('id', $row->id)->update([
                        'items' => $this->encodeJson($built['items']),
                        'next_item_id' => $built['next_id'],
                    ]);
                }
            });

        // list_snapshots: string items -> object items. Snapshots never tracked
        // per-item stamps, so every restored item ages from the snapshot's own
        // created_at (the fallback). Ids restart at 1 within each snapshot.
        DB::table('list_snapshots')->orderBy('id')
            ->select('id', 'items', 'created_at')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $values = $this->decodeJson($row->items);
                    if ($this->isObjectShape($values)) {
                        continue;
                    }
                    $built = ListItems::fromLegacy($values, [], $this->fallbackTs($row->created_at));

                    DB::table('list_snapshots')->where('id', $row->id)->update([
                        'items' => $this->encodeJson($built['items']),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // item_added_at was never dropped in up(), so it is still here to
        // repopulate; we just rebuild it from each object's added_at.
        // object items -> string items + rebuilt parallel stamps.
        DB::table('option_sets')->orderBy('id')
            ->select('id', 'items')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $items = $this->decodeJson($row->items);
                    if (! $this->isObjectShape($items)) {
                        continue; // already strings
                    }
                    DB::table('option_sets')->where('id', $row->id)->update([
                        'items' => $this->encodeJson(ListItems::values($items)),
                        'item_added_at' => $this->encodeJson($this->extractStamps($items)),
                    ]);
                }
            });

        DB::table('list_snapshots')->orderBy('id')
            ->select('id', 'items')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $items = $this->decodeJson($row->items);
                    if (! $this->isObjectShape($items)) {
                        continue;
                    }
                    DB::table('list_snapshots')->where('id', $row->id)->update([
                        'items' => $this->encodeJson(ListItems::values($items)),
                    ]);
                }
            });

        Schema::table('option_sets', function (Blueprint $table) {
            $table->dropColumn('next_item_id');
        });
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJson(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, mixed>  $value
     */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * True when items already look like the object shape (first entry is an
     * array carrying an `id`). Lets the migration no-op on already-converted
     * rows in either direction.
     *
     * @param  array<int, mixed>  $items
     */
    private function isObjectShape(array $items): bool
    {
        $first = $items[0] ?? null;

        return is_array($first) && array_key_exists('id', $first);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, int>
     */
    private function extractStamps(array $items): array
    {
        $fallback = now()->getTimestamp();

        return array_map(
            static fn ($item): int => is_array($item) && is_numeric($item['added_at'] ?? null)
                ? (int) $item['added_at']
                : $fallback,
            array_values($items),
        );
    }

    private function fallbackTs(mixed $createdAt): int
    {
        return $createdAt ? Carbon::parse($createdAt)->getTimestamp() : now()->getTimestamp();
    }
};
