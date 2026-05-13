<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 50);
            $table->string('label', 255)->nullable();
            $table->jsonb('items')->default(DB::raw("'[]'::jsonb"));
            $table->unsignedSmallInteger('min_items')->default(1);
            $table->unsignedSmallInteger('max_items')->nullable();
            $table->boolean('user_editable')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_sets');
    }
};
