<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The blocks of a channel's CURRENT Chat Tower, one row per block, bottom to
 * top by `position`. This is standing state, not history: a topple deletes
 * every row for the channel and the next `!stack` starts at position 1.
 * History lives in external_events like every other integration (one `stack`
 * or `topple` event per command).
 *
 * `offset` is where this block landed relative to the block below it, in
 * block-width units (a block is 4 wide); `x` is the running sum, the block's
 * centre relative to the base. The top block's `x` is the tower's lean, the
 * number the fall rule is applied to (TowerPhysics). Both are stamped at
 * placement and never recomputed.
 *
 * Whether a tower survives a stream ending is the `tower_lifetime`
 * integration setting (per_stream clears it at go-live), never a second
 * table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tower_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('chatter_twitch_id', 32);
            $table->string('chatter_login', 64);
            $table->string('chatter_display_name', 64);
            $table->string('color', 7)->nullable();
            $table->double('offset');
            $table->double('x');
            // True when placing this block took the tower past the all-time
            // record. The block below it not being one is what makes a
            // record announcement fire once per tower, and the top block
            // being one is what tells a topple it took the record tower down.
            $table->boolean('record')->default(false);
            $table->timestamp('placed_at');
            $table->timestamps();

            $table->unique(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tower_blocks');
    }
};
