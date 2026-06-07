<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->text('bot_message_expression')->nullable()->after('tts_expression');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->dropColumn('bot_message_expression');
        });
    }
};
