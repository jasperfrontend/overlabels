<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overlay_reports', function (Blueprint $table) {
            $table->id();

            // Nullable + nullOnDelete on purpose: a report has to outlive the
            // overlay it is about. Deleting the overlay (which is often the
            // outcome of acting on the report) must not erase the record of
            // why it was deleted. The slug/name snapshot keeps the row
            // readable once the FK is gone.
            $table->foreignId('overlay_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_slug');
            $table->string('template_name');

            // Exactly one of these is set: a logged-in reporter is identified
            // by their Twitch-backed account, an anonymous one by the email
            // they typed. Anonymous reports keep their email after the
            // reporter's (non-existent) account goes away, hence no FK.
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_email')->nullable();

            $table->text('reason');

            // open | read. Deliberately a string rather than a DB enum so
            // adding a state later is a code change, not a migration.
            $table->string('status')->default('open');

            // Retained for abuse handling only (spotting one person filing
            // dozens of reports). Disclosed in the privacy policy and dropped
            // with the report by the 180-day prune.
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_reports');
    }
};
