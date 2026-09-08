<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The What's New card no longer tracks "visited" separately from "seen".
 *
 * `visited_at` was set by a middleware when the reader landed on the page a
 * post's call to action pointed at; the row went grey but stayed on the card
 * until it was dismissed by hand. Opening the post itself now marks it seen
 * (`dismissed_at`), the middleware is gone, and nothing reads or writes this
 * column. It goes with them.
 *
 * Rows are kept. A row whose only value was `visited_at` becomes a row with
 * nothing set, which the card treats exactly like no row at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('update_interactions', function (Blueprint $table) {
            $table->dropColumn('visited_at');
        });
    }

    public function down(): void
    {
        Schema::table('update_interactions', function (Blueprint $table) {
            $table->timestamp('visited_at')->nullable()->after('update_id');
        });
    }
};
