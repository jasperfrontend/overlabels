<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            // The instance_slug RecipeInstaller should use when this kit is
            // forked. If the slug is already taken on the forker's account
            // (e.g. they forked the kit before) Kit::fork() suffixes it
            // (`<slug>_2`, `_3`, ...) to avoid collisions.
            $table->string('default_instance_slug', 50);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['kit_id', 'recipe_id']);
            $table->index('kit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_recipes');
    }
};
