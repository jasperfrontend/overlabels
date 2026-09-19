<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The nine listed products moved from snake_case slugs to the hyphenated form
 * their public URLs now use, so /products/bmac_alert became
 * /products/buy-me-a-coffee-alerts.
 *
 * `recipes.slug` has to move with them. ProductController reads the catalogue
 * off disk but finds an account's install by joining `recipes` and matching
 * that column, so without this every installed product would read as not
 * installed: the page would offer Install again, and the Installed shelf would
 * empty out.
 *
 * `recipe_instances` points at `recipes.id`, so instances follow the rename on
 * their own and are not touched here. `instance_slug` is per-account naming and
 * keeps whatever it was given at install time; nothing matches it against a
 * product slug.
 *
 * The map is frozen as a literal. A migration is dated but a constant is not,
 * so reading today's manifests here would seed whatever the catalogue grows
 * into later. `coin_flip` and `dice` are deliberately absent: they are unlisted
 * picker recipes with no public URL, and their slug is the first segment of
 * [[[c:<slug>:<instance>:<key>]]] tags sitting in user-owned overlays.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const RENAMES = [
        'bmac_alert' => 'buy-me-a-coffee-alerts',
        'chat_checkin' => 'chat-checkin',
        'chat_tower' => 'chat-tower',
        'follower_bowling' => 'follower-bowling',
        'fourthwall_alert' => 'fourthwall-alerts',
        'kofi_alert' => 'ko-fi-alerts',
        'streamlabs_alert' => 'streamlabs-alerts',
        'throne_alert' => 'throne-alerts',
        'twitch_chat' => 'twitch-chat-overlay',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('recipes')->where('slug', $old)->update(['slug' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('recipes')->where('slug', $new)->update(['slug' => $old]);
        }
    }
};
