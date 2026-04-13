<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('account')->unique();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->bigInteger('expires_at');
            $table->bigInteger('obtained_at');
            $table->json('scopes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_tokens');
    }
};
