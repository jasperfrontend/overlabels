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
        // Table to store different categories of template tags (like 'channel', 'subscribers', etc.)
        Schema::create('template_tag_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'channel', 'subscribers', 'followers'
            $table->string('display_name'); // e.g., 'Channel Info', 'Subscribers', 'Followers'
            $table->text('description')->nullable();
            $table->boolean('is_group')->default(false); // true for groups like [[[subscribers]]], false for singles
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->unique('name');
        });

        // Table to store individual template tags
        Schema::create('template_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('template_tag_categories')->onDelete('cascade');
            $table->string('tag_name'); // e.g., 'channel.name', 'subscribers.total', 'followers.latest'
            $table->string('display_tag'); // e.g., '[[[channel.name]]]', '[[[subscribers.total]]]'
            $table->string('json_path'); // e.g., 'channel.broadcaster_name', 'subscribers.total'
            $table->string('data_type')->default('string'); // string, number, array, object, boolean
            $table->string('display_name'); // Human-readable name like 'Channel Name', 'Total Subscribers'
            $table->text('description')->nullable();
            $table->json('sample_data')->nullable(); // Store sample data for preview
            $table->json('formatting_options')->nullable(); // Store formatting rules (dates, numbers, etc.)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['category_id', 'tag_name']);
            $table->index('json_path');
        });

        // Table to store user-generated templates that use these tags
        Schema::create('user_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('html_content'); // The HTML template with [[[tags]]]
            $table->longText('css_content')->nullable(); // Optional CSS
            $table->json('used_tags')->nullable(); // Array of tag IDs used in this template
            $table->string('status')->default('draft'); // draft, published, archived
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_templates');
        Schema::dropIfExists('template_tags');
        Schema::dropIfExists('template_tag_categories');
    }
};