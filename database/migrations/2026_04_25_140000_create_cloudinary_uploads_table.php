<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloudinary_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('public_id', 500)->unique();
            $table->string('secure_url', 1024);
            $table->string('kind', 64);
            $table->unsignedBigInteger('bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('format', 16)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['claimed_at', 'created_at']);
            $table->index('secure_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudinary_uploads');
    }
};
