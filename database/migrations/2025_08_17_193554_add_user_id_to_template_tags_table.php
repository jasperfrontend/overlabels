<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('template_tags', function (Blueprint $table) {
            // Add user_id column after category_id
            $table->foreignId('user_id')->nullable()->after('category_id')->constrained()->onDelete('cascade');
            
            // Remove the unique constraint on category_id and tag_name
            $table->dropUnique(['category_id', 'tag_name']);
            
            // Add new unique constraint that includes user_id
            $table->unique(['user_id', 'category_id', 'tag_name']);
        });
        
        // Add user_id to template_tag_categories as well
        Schema::table('template_tag_categories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            
            // Drop the unique constraint on name
            $table->dropUnique(['name']);
            
            // Add new unique constraint that includes user_id
            $table->unique(['user_id', 'name']);
        });
        
        // Optionally, assign existing tags to the first user or delete them
        // Since these are generated tags, we'll delete them to start fresh
        DB::table('template_tags')->truncate();
        DB::table('template_tag_categories')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_tags', function (Blueprint $table) {
            // Drop the unique constraint with user_id
            $table->dropUnique(['user_id', 'category_id', 'tag_name']);
            
            // Restore the old unique constraint
            $table->unique(['category_id', 'tag_name']);
            
            // Drop the user_id column
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
        
        Schema::table('template_tag_categories', function (Blueprint $table) {
            // Drop the unique constraint with user_id
            $table->dropUnique(['user_id', 'name']);
            
            // Restore the old unique constraint
            $table->unique(['name']);
            
            // Drop the user_id column
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
