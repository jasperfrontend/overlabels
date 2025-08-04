<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overlay_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique()->index();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->longText('html');
            $table->longText('css')->nullable();
            $table->longText('js')->nullable();
            $table->boolean('is_public')->default(true)->index();
            $table->integer('version')->default(1);
            $table->foreignId('fork_of_id')->nullable()->constrained('overlay_templates')->nullOnDelete();
            $table->json('template_tags')->nullable(); // Store which tags are used
            $table->json('metadata')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('fork_count')->default(0);
            $table->timestamps();

            // Composite indexes
            $table->index(['owner_id', 'is_public']);
            $table->index(['slug', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_templates');
    }
};
