<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->longText('head')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('overlay_templates', function (Blueprint $table) {
            $table->string('head')->nullable()->change();
        });
    }
};
