<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Twitch Chat Plinko became Chat Plinko on 2026-11-28, and its slug moved from
 * twitch-chat-plinko to chat-plinko (the manifest keeps the old one in
 * `url_aliases`, so the old URL 301s to the new one).
 *
 * `recipes.slug` has to move with it. ProductController reads the catalogue off
 * disk but finds an account's install by joining `recipes` and matching that
 * column, so without this every existing install would read as not installed:
 * the page would offer Get again, and the Installed shelf would lose it.
 *
 * `recipes.name` is moved too, where it still says the old name: only the
 * catalogue sync on an install would otherwise correct it.
 *
 * `recipe_instances` point at `recipes.id`, so they follow on their own.
 * `instance_slug` (twitch_chat_plinko) is per-account naming that nothing
 * matches against a product slug, and the overlays an account already
 * installed are the streamer's own copies, so their names are left as they
 * are, the same as the 2026-09-19 slug rename did.
 *
 * Literals on purpose: a migration is dated, a constant is not.
 */
return new class extends Migration
{
    private const OLD_SLUG = 'twitch-chat-plinko';

    private const NEW_SLUG = 'chat-plinko';

    private const OLD_NAME = 'Twitch Chat Plinko';

    private const NEW_NAME = 'Chat Plinko';

    public function up(): void
    {
        DB::table('recipes')->where('slug', self::OLD_SLUG)->update(['slug' => self::NEW_SLUG]);
        DB::table('recipes')->where('slug', self::NEW_SLUG)->where('name', self::OLD_NAME)->update(['name' => self::NEW_NAME]);
    }

    public function down(): void
    {
        DB::table('recipes')->where('slug', self::NEW_SLUG)->update(['slug' => self::OLD_SLUG]);
        DB::table('recipes')->where('slug', self::OLD_SLUG)->where('name', self::NEW_NAME)->update(['name' => self::OLD_NAME]);
    }
};
