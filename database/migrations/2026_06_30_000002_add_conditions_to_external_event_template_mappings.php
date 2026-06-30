<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2 of alert variants: external donation amounts. Mirrors the Twitch
     * side (event_template_mappings) - a single donation event type can now
     * resolve to different alert templates depending on the donated amount.
     *
     * The old unique(user_id, service, event_type) enforced one template per
     * external event type. We re-key it to include overlay_template_id so many
     * templates can map the same (service, donation) with different conditions,
     * which is what makes the variant ladder.
     */
    public function up(): void
    {
        Schema::table('external_event_template_mappings', function (Blueprint $table) {
            // null = base/catch-all (today's behavior); 'at_least' / 'exactly'
            $table->string('condition_type')->nullable()->after('event_type');
            // null for base rows; the amount threshold (whole currency units)
            $table->unsignedInteger('condition_value')->nullable()->after('condition_type');

            $table->dropUnique(['user_id', 'service', 'event_type']);
            $table->unique(['user_id', 'overlay_template_id', 'service', 'event_type'], 'ext_mapping_user_tpl_service_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('external_event_template_mappings', function (Blueprint $table) {
            $table->dropUnique('ext_mapping_user_tpl_service_event_unique');
            $table->unique(['user_id', 'service', 'event_type']);

            $table->dropColumn(['condition_type', 'condition_value']);
        });
    }
};
