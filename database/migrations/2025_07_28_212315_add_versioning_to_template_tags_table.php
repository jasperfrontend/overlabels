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
        // Add new columns to the template_tags table
        Schema::table('template_tags', function (Blueprint $table) {
            // Add these new columns after existing ones
            $table->enum('tag_type', ['standard', 'custom'])->default('standard')->after('data_type');
            $table->string('version', 10)->default('1.0')->after('tag_type');
            $table->boolean('is_editable')->default(false)->after('version');
            $table->string('original_tag_name')->nullable()->after('is_editable');
        });

        // Update existing tags to be 'standard' type (since they're already created)
        DB::table('template_tags')->update([
            'tag_type' => 'standard',
            'version' => '1.0',
            'is_editable' => false,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_tags', function (Blueprint $table) {
            $table->dropColumn([
                'tag_type',
                'version',
                'is_editable',
                'original_tag_name',
            ]);
        });
    }
};
