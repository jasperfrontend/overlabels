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
        Schema::create('storage_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('provider', ['google_drive', 'onedrive', 'dropbox']);
            $table->string('provider_user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['user_id', 'provider', 'provider_user_id']);
            $table->index(['user_id', 'provider', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_accounts');
    }
};
