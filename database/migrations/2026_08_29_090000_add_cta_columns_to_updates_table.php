<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The What's New card's call to action, denormalised out of the body frontmatter.
 *
 * Frontmatter stays the authoring surface - these columns are a projection of
 * it, rewritten on every save, never edited by hand. They exist because a
 * update has to go grey the moment the reader lands on the page it points at,
 * and answering "does any pending update point at the route being requested"
 * once per page load cannot mean parsing the markdown body of every post.
 *
 * cta_route is indexed for exactly that lookup. cta_url is indexed for the
 * same lookup against an internal path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('updates', function (Blueprint $table) {
            $table->string('cta_route')->nullable()->index();
            $table->string('cta_params')->nullable();
            $table->string('cta_url')->nullable()->index();
            $table->string('cta_label')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('updates', function (Blueprint $table) {
            $table->dropIndex(['cta_route']);
            $table->dropIndex(['cta_url']);
            $table->dropColumn(['cta_route', 'cta_params', 'cta_url', 'cta_label']);
        });
    }
};
