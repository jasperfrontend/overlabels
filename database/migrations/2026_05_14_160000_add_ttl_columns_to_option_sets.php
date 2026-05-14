<?php

use App\Models\OptionSet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            // Entry-level expiry: each item gets swept when its
            // item_added_at exceeds this many seconds ago. Null = no
            // per-item expiry. Lets streamers run "raise your hand for
            // 5 minutes" mechanics where chatter entries decay
            // individually.
            $table->unsignedInteger('entry_ttl_seconds')->nullable()->after('disabled_at');

            // List-level expiry: when reached, the sweeper snapshots,
            // clears items, and sets disabled_at. The whole list
            // "vanishes" at the deadline - dramatic for raffle rushes.
            // Surfaces in overlays as [[[c:list:slug:expires_at]]] and
            // a live-ticking [[[c:list:slug:countdown]]] timer.
            $table->timestamp('expires_at')->nullable()->after('entry_ttl_seconds');

            // Parallel array to items, one timestamp per item (Unix
            // seconds). Same length as items, maintained in sync by
            // every mutator (chat appender, dashboard save, destructive
            // actions, clone). Existing rows get a backfill below.
            $table->jsonb('item_added_at')->default(DB::raw("'[]'::jsonb"))->after('items');

            $table->index('expires_at');
        });

        // Backfill: for every existing row, set item_added_at to an
        // array of the list's created_at, one per item. Not precise
        // (the items might have been added at different times) but
        // reasonable - they're all "as old as the list" from the
        // sweeper's perspective.
        OptionSet::query()->orderBy('id')->chunkById(200, function ($lists) {
            foreach ($lists as $list) {
                $count = count($list->items ?? []);
                if ($count === 0) {
                    continue;
                }
                $ts = $list->created_at?->timestamp ?? now()->timestamp;
                $list->update(['item_added_at' => array_fill(0, $count, $ts)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['entry_ttl_seconds', 'expires_at', 'item_added_at']);
        });
    }
};
