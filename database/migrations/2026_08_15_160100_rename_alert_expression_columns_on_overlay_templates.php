<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last two columns calling themselves expressions. Neither is a jsep
 * formula and neither is a command: both hold a templated string an alert
 * renders and sends somewhere. `tts_message` is spoken, `chat_message` is
 * posted to Twitch by the bot.
 *
 * With these renamed, "expression" means a jsep formula in an Expression
 * Control and nothing else, anywhere in the codebase.
 *
 * Not a wire format: OverlayShareService builds its alert array fresh from the
 * template on every request and never reads one back, so no stored share
 * document carries the old keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->renameColumn('tts_expression', 'tts_message');
            $table->renameColumn('bot_message_expression', 'chat_message');
        });
    }

    public function down(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->renameColumn('tts_message', 'tts_expression');
            $table->renameColumn('chat_message', 'bot_message_expression');
        });
    }
};
