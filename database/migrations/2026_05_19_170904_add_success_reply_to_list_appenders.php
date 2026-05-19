<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('list_appenders', function (Blueprint $table) {
            // Mirror of args_empty_reply. Sent to bot_chat_outbox on a
            // successful append. Resolved via BotExpressionResolver so
            // it can reference [[[bot:from_user]]], [[[bot:args]]], etc.
            // NULL = silent (existing default behaviour).
            $table->string('success_reply', 500)->nullable()->after('args_empty_reply');
        });
    }

    public function down(): void
    {
        Schema::table('list_appenders', function (Blueprint $table) {
            $table->dropColumn('success_reply');
        });
    }
};
