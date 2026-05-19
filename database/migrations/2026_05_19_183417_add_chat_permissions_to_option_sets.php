<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            // Map of action_name => permission_level. NULL = use the
            // service-side defaults (moderator+ for everything, matching
            // pre-migration behaviour). Stored as partial overrides: only
            // actions whose permission differs from the default need to
            // appear here. The service merges this over its defaults at
            // resolve time.
            $table->jsonb('chat_permissions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->dropColumn('chat_permissions');
        });
    }
};
