<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backend-to-bot chat queue. The bot polls this table every few seconds
 * and posts each row into the target channel's chat. Existed because the
 * bot has no Reverb/Pusher client and we didn't want to add one just for
 * the gamejam inactive-mention message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_chat_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sent_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_chat_outbox');
    }
};
