<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A saved look belongs to one product's designer.
 *
 * Until now only the Twitch Chat product had a designer, so every row in
 * `user_chat_presets` was a chat look and the table needed no column to say
 * so. The designer is declared per product now (the manifest's `designer`
 * block) and Chat Emote Bubbles has one too, so a look has to know which
 * designer lists it: a chat look's thirteen keys mean nothing to the bubbles
 * overlay and would apply as nothing. Every existing row is a chat look, and
 * is stamped as one. Names stay unique per person per product, so the same
 * name can exist once for each.
 *
 * The product slug is a literal here, never a constant: a migration is dated
 * and the codebase is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_chat_presets', function (Blueprint $table) {
            $table->string('product', 50)->nullable()->after('user_id');
        });

        DB::table('user_chat_presets')->whereNull('product')->update(['product' => 'twitch-chat-overlay']);

        Schema::table('user_chat_presets', function (Blueprint $table) {
            $table->string('product', 50)->nullable(false)->change();
            $table->dropUnique(['user_id', 'name']);
            $table->unique(['user_id', 'product', 'name']);
        });
    }

    public function down(): void
    {
        // Two products' looks may share a name once the column is gone. The
        // chat product's rows keep theirs; the rest go, since nothing before
        // this migration can list them.
        DB::table('user_chat_presets')->where('product', '!=', 'twitch-chat-overlay')->delete();

        Schema::table('user_chat_presets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'product', 'name']);
            $table->unique(['user_id', 'name']);
            $table->dropColumn('product');
        });
    }
};
