<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->text('tts_expression')->nullable()->after('js');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->dropColumn('tts_expression');
        });
    }
};
