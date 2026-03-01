<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_controls', function (Blueprint $table) {
            // Drop the old NOT NULL foreign key + unique constraint
            $table->dropUnique(['overlay_template_id', 'key']);
            $table->dropForeign(['overlay_template_id']);
            $table->dropIndex(['overlay_template_id', 'user_id']);

            // Make overlay_template_id nullable (NULL = user-scoped control)
            $table->foreignId('overlay_template_id')
                ->nullable()
                ->change();

            // Re-add foreign key as nullable (cascadeOnDelete only when non-null)
            $table->foreign('overlay_template_id')
                ->references('id')
                ->on('overlay_templates')
                ->nullOnDelete();

            // Service source fields
            $table->string('source', 50)->nullable()->after('sort_order');
            $table->boolean('source_managed')->default(false)->after('source');

            // New unique constraints:
            // Template-scoped controls: unique per template + key (partial, only when template_id is set)
            // Service-managed controls: unique per user + source + key
            $table->unique(['user_id', 'source', 'key'], 'overlay_controls_user_source_key_unique');
            $table->index(['overlay_template_id', 'user_id'], 'overlay_controls_template_user_index');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_controls', function (Blueprint $table) {
            $table->dropUnique('overlay_controls_user_source_key_unique');
            $table->dropIndex('overlay_controls_template_user_index');
            $table->dropForeign(['overlay_template_id']);
            $table->dropColumn(['source', 'source_managed']);

            $table->foreignId('overlay_template_id')
                ->nullable(false)
                ->change();

            $table->foreign('overlay_template_id')
                ->references('id')
                ->on('overlay_templates')
                ->cascadeOnDelete();

            $table->unique(['overlay_template_id', 'key']);
            $table->index(['overlay_template_id', 'user_id']);
        });
    }
};
