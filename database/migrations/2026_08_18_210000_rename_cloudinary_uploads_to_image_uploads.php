<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cloudinary is gone; images live on Cloudflare R2 behind
 * images.overlabels.com. Rename the table and the two vendor-shaped columns
 * to match.
 *
 * `public_id` was a Cloudinary asset identifier and is now an R2 object key,
 * so it becomes `path`. `secure_url` was Cloudinary's name for "the https
 * one" and is now just the delivery URL, so it becomes `url`.
 *
 * This only renames. The values in those columns still point at Cloudinary
 * until `php artisan images:migrate-from-cloudinary` has run - that command
 * is what moves the bytes and rewrites the values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('cloudinary_uploads', 'image_uploads');

        Schema::table('image_uploads', function (Blueprint $table) {
            $table->renameColumn('public_id', 'path');
            $table->renameColumn('secure_url', 'url');
        });
    }

    public function down(): void
    {
        Schema::table('image_uploads', function (Blueprint $table) {
            $table->renameColumn('path', 'public_id');
            $table->renameColumn('url', 'secure_url');
        });

        Schema::rename('image_uploads', 'cloudinary_uploads');
    }
};
