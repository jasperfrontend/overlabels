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
        Schema::create('template_tag_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('job_type'); // 'generate' or 'cleanup'
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('job_id')->nullable(); // Laravel queue job ID
            $table->json('progress')->nullable(); // Progress data (e.g., current step, total steps)
            $table->json('result')->nullable(); // Job result data
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'job_type']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_tag_jobs');
    }
};
