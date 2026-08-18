<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the overlay_hashes table.
     *
     * It was the original hash-based public-link scheme for overlays, superseded
     * by OverlayAccessToken (64-char hex token in the URL fragment, sha256 stored
     * server-side). The model, controller and factory were deleted on 2026-04-13
     * and the migrations were kept then because the table still held historical
     * state. Nothing has been able to write to it since.
     *
     * The last reader was FunSlugGenerationService, which checked slug uniqueness
     * against this table long after slugs had moved to overlay_templates.slug. It
     * was repointed on 2026-08-18, which left this table referenced by nothing at
     * all outside its own three migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('overlay_hashes');
    }

    /**
     * Recreate the table as it stood, so a rollback restores the schema.
     *
     * The rows are not recoverable. That is the honest limit of a table drop and
     * there is nothing to restore them from, but the data described a link scheme
     * no code has read since April.
     */
    public function down(): void
    {
        Schema::create('overlay_hashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('hash_key', 64)->unique();
            $table->string('overlay_name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_accessed_at')->nullable();
            $table->integer('access_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['hash_key', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index('expires_at');
            $table->index('slug');
        });
    }
};
