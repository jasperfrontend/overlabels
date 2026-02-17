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
        Schema::table('overlay_hashes', function (Blueprint $table) {
            // Add slug field for shareable URLs
            $table->string('slug')->nullable()->after('overlay_name');

            // Add index for performance
            $table->index('slug');
        });

        // Update existing records to have fun slugs
        $slugService = app(\App\Services\FunSlugGenerationService::class);

        DB::table('overlay_hashes')->get()->each(function ($hash) use ($slugService) {
            $slug = $slugService->generateUniqueSlug();
            DB::table('overlay_hashes')->where('id', $hash->id)->update(['slug' => $slug]);
        });

        // Now make slug required (not nullable)
        Schema::table('overlay_hashes', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overlay_hashes', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');
        });
    }
};
