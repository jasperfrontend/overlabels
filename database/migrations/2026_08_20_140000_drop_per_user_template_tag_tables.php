<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template tags are a code constant now, so the tables that stored a private
 * copy per user are dropped.
 *
 * They held nothing an account owned. Production carried 1155 tag rows
 * expressing 82 distinct names across 19 users, and 122 category rows for 7
 * distinct categories - every one written by the generator, none ever edited
 * (zero rows with tag_type 'custom', zero with is_editable true). At 1000 users
 * the same list would have cost 64,000 identical rows.
 *
 * `user_templates` goes with them: it referenced template_tags by id, held zero
 * rows in production, and nothing in the codebase referenced the model.
 *
 * down() recreates the schema but not the data. Restoring the rows would mean
 * reinstating the generator that produced them, and the catalogue in
 * TemplateDataMapperService is now the only description of what a tag is.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Child first: template_tags.category_id references the categories table.
        Schema::dropIfExists('user_templates');
        Schema::dropIfExists('template_tags');
        Schema::dropIfExists('template_tag_categories');
        Schema::dropIfExists('template_tag_jobs');
    }

    public function down(): void
    {
        Schema::create('template_tag_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->boolean('is_group')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('template_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('template_tag_categories')->cascadeOnDelete();
            $table->string('tag_name');
            $table->string('display_tag');
            $table->string('json_path');
            $table->string('data_type');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->json('sample_data')->nullable();
            $table->json('formatting_options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('tag_type')->default('standard');
            $table->string('version', 10)->default('1.0');
            $table->boolean('is_editable')->default(false);
            $table->string('original_tag_name')->nullable();
            $table->timestamps();
        });

        Schema::create('template_tag_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('job_type');
            $table->string('status')->default('pending');
            $table->string('job_id')->nullable();
            $table->json('progress')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('html_content')->nullable();
            $table->longText('css_content')->nullable();
            $table->json('used_tags')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }
};
