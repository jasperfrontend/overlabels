<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * gps_pings was created in March 2026 and never written to or read: GPS flows
 * through external_events via GpsServiceDriver, and a repo-wide search finds
 * no reference outside its own migration. Pile C of the delivery heal list.
 * down() restores the original shape so a rollback is exact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gps_pings');
    }

    public function down(): void
    {
        Schema::create('gps_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('speed')->nullable();
            $table->float('altitude')->nullable();
            $table->float('accuracy')->nullable();
            $table->float('direction')->nullable();
            $table->uuid('session_id')->nullable()->index();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }
};
