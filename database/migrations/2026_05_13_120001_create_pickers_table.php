<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_set_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 50);
            $table->string('label', 255)->nullable();

            // When true, picked indices are appended to consumed_indices and
            // excluded from future picks until reset. Raffle-style behaviour.
            $table->boolean('consume_on_pick')->default(false);
            $table->jsonb('consumed_indices')->default(DB::raw("'[]'::jsonb"));

            // reject_if_running | cancel_running | allow
            $table->string('concurrency', 20)->default('reject_if_running');

            $table->boolean('user_editable')->default(false);

            // Last-fire state. Result is the picked string verbatim.
            $table->string('last_result', 255)->nullable();
            $table->timestamp('last_result_at')->nullable();
            $table->boolean('is_running')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index('option_set_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickers');
    }
};
