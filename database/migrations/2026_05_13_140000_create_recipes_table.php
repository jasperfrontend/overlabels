<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50);
            $table->unsignedInteger('version');
            $table->string('name', 100);
            $table->text('description');
            $table->string('author_name', 100);
            $table->string('author_twitch_login', 25)->nullable();
            $table->string('icon_url', 1024)->nullable();
            $table->text('changelog')->nullable();
            $table->unsignedSmallInteger('min_overlabels_version')->default(1);
            $table->jsonb('requires_integrations')->default(DB::raw("'[]'::jsonb"));
            $table->unsignedSmallInteger('max_instances_per_user')->nullable();
            $table->jsonb('manifest');
            $table->boolean('is_first_party')->default(false);
            $table->timestamps();

            // Each (slug, version) is its own installable artifact. No
            // in-place upgrades, per the manifest design decision.
            $table->unique(['slug', 'version']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
