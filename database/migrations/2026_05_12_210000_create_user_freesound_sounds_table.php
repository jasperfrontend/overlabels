<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_freesound_sounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('freesound_id');
            $table->string('name', 255);
            $table->string('author', 255);
            // Freesound's `license` field is a name string (e.g. "Creative
            // Commons 0", "Attribution"). We only accept commercial-safe
            // licenses server-side, so this column holds the human label.
            $table->string('license', 100);
            // Preview MP3 URL - the actual playback source. We never store
            // audio bytes; this URL points at Freesound's CDN.
            $table->string('preview_url', 1024);
            $table->decimal('duration', 8, 3)->nullable();
            // Canonical Freesound page URL for attribution links.
            $table->string('freesound_url', 1024)->nullable();
            $table->timestamps();

            // A user can save the same Freesound sound only once. Pivots their
            // library entries so the "library size" cap is unambiguous.
            $table->unique(['user_id', 'freesound_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_freesound_sounds');
    }
};
