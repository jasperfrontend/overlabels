<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->string('screenshot_url', 2048)->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->dropColumn('screenshot_url');
        });
    }
};
