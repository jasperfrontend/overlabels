<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finish what 2026_11_16_200000 started: drop the `!fbfirst` / `!fbdraw`
 * aliases from EVERY Follower Bowling install that still lists them.
 *
 * The redesign move returns early for an install with no lane overlay in its
 * primitive_map, or whose lane overlay was deleted, so those installs kept
 * their two moderator aliases and the `bot_aliases` section that names them
 * (found by the OL-2611-019 audit, finding F2). The product no longer has
 * either command, so the aliases go here whatever state the overlay is in.
 *
 * Same scoping as the move: by the ids the install recorded, and only rows
 * owned by the install's user. An alias the streamer made themselves is never
 * in the map and is never touched. Idempotent: an install without
 * `bot_aliases` is skipped.
 */
return new class extends Migration
{
    private const SLUGS = ['follower-bowling', 'follower_bowling'];

    public function up(): void
    {
        $recipeIds = DB::table('recipes')->whereIn('slug', self::SLUGS)->pluck('id');

        if ($recipeIds->isEmpty()) {
            return;
        }

        $cleaned = 0;

        DB::table('recipe_instances')
            ->whereIn('recipe_id', $recipeIds)
            ->select('id', 'user_id', 'primitive_map')
            ->chunkById(100, function ($instances) use (&$cleaned) {
                foreach ($instances as $instance) {
                    $map = is_string($instance->primitive_map)
                        ? json_decode($instance->primitive_map, true)
                        : $instance->primitive_map;
                    $map = is_array($map) ? $map : [];

                    if (! array_key_exists('bot_aliases', $map)) {
                        continue;
                    }

                    $aliasIds = array_values(array_map('intval', is_array($map['bot_aliases']) ? $map['bot_aliases'] : []));
                    unset($map['bot_aliases']);

                    DB::transaction(function () use ($instance, $aliasIds, $map) {
                        if ($aliasIds !== []) {
                            DB::table('bot_aliases')
                                ->where('user_id', $instance->user_id)
                                ->whereIn('id', $aliasIds)
                                ->delete();
                        }

                        DB::table('recipe_instances')->where('id', $instance->id)->update([
                            'primitive_map' => json_encode($map),
                            'updated_at' => now(),
                        ]);
                    });

                    $cleaned++;
                }
            });

        Log::info('Follower Bowling leftover alias cleanup', ['cleaned' => $cleaned]);
    }

    /**
     * The aliases belong to a product that no longer has those commands.
     * Nothing to put back.
     */
    public function down(): void {}
};
