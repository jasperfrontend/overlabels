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
        // Drop the existing table completely
        Schema::dropIfExists('overlay_hashes');
        
        // Recreate the table with correct column types
        Schema::create('overlay_hashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('hash_key', 64)->unique(); // The secure hash for URL access
            $table->string('overlay_name'); // User-friendly name for the overlay
            $table->text('description')->nullable(); // Optional description
            $table->boolean('is_active')->default(true); // Can be revoked by setting to false
            $table->timestamp('last_accessed_at')->nullable(); // Track usage
            $table->integer('access_count')->default(0); // FIXED: Now properly an integer!
            $table->timestamp('expires_at')->nullable(); // Optional expiration
            $table->json('allowed_ips')->nullable(); // Optional IP whitelist for extra security
            $table->json('metadata')->nullable(); // Store any additional overlay-specific data
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['hash_key', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overlay_hashes');
    }
};