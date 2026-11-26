<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overlabels Overload Report (#345): one row per busy stretch on stream.
 * Aggregates only - counts, amounts and timestamps, never a viewer's name,
 * id or message - built once when the stretch closes, so a report does not
 * depend on the event rows afterwards. Pruned after 90 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overload_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 8);
            $table->timestamp('started_at');
            $table->timestamp('ended_at');
            $table->unsignedInteger('event_count');
            $table->json('totals');
            $table->json('timeline');
            $table->timestamps();

            $table->unique(['user_id', 'code']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overload_reports');
    }
};
