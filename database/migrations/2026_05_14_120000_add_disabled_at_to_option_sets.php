<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            // Null = active. Set = disabled at that timestamp. Disabled
            // lists silently refuse list_appender fires but stay
            // editable in the dashboard - the streamer can still
            // curate items manually. List itself + items stay visible
            // to overlays via [[[c:list:slug...]]] tags.
            $table->timestamp('disabled_at')->nullable()->after('user_editable');
            $table->index('disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('option_sets', function (Blueprint $table) {
            $table->dropIndex(['disabled_at']);
            $table->dropColumn('disabled_at');
        });
    }
};
