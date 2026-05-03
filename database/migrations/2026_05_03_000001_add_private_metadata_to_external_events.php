<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_events', function (Blueprint $table) {
            $table->char('supporter_email_hash', 64)->nullable()->after('normalized_payload');
            $table->text('private_metadata')->nullable()->after('supporter_email_hash');
            $table->index('supporter_email_hash');
        });
    }

    public function down(): void
    {
        Schema::table('external_events', function (Blueprint $table) {
            $table->dropIndex(['supporter_email_hash']);
            $table->dropColumn(['supporter_email_hash', 'private_metadata']);
        });
    }
};
