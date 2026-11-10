<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Featured kits are the official ready-made sets: the ones the kit picker a
 * new account lands on offers, and the ones leading the Alerts shelf on
 * /products. Ordered by the moment they were featured. A copy never inherits
 * it (Kit::fork resets it). See App\Services\Kits\FeaturedKits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kits', function (Blueprint $table) {
            $table->timestamp('featured_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kits', function (Blueprint $table) {
            $table->dropColumn('featured_at');
        });
    }
};
