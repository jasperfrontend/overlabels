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
        Schema::table('foxes', function (Blueprint $table) {
            $table->string('cloudinary_url')->nullable()->after('local_file');
            $table->string('cloudinary_public_id')->nullable()->after('cloudinary_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('foxes', function (Blueprint $table) {
            $table->dropColumn(['cloudinary_url', 'cloudinary_public_id']);
        });
    }
};
