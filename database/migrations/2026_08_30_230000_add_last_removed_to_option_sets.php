<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            // The value most recently taken OUT of the list by `pop` or `draw`,
            // and when. A pop or draw broadcasts the remaining items, so
            // without this the overlay could see the list shrink but never
            // who left it - the raffle winner only ever reached chat. Rendered
            // as [[[c:list:<slug>:last_removed]]] / :last_removed_at.
            // NULL until something is popped or drawn; `clear` leaves it alone.
            $table->text('last_removed')->nullable();
            $table->timestamp('last_removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->dropColumn(['last_removed', 'last_removed_at']);
        });
    }
};
