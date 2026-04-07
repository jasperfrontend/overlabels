<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stream_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('state', 20)->default('offline');
            $table->float('confidence')->default(0.0);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('helix_stream_id')->nullable();
            $table->foreignId('current_session_id')->nullable()
                ->constrained('stream_sessions')->nullOnDelete();
            $table->timestamp('grace_period_until')->nullable();
            $table->timestamps();

            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_states');
    }
};
