<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cheer/raid/gift "variants": a single event type can now resolve to
     * different alert templates depending on a numeric payload field
     * (bits / viewers / gift count). The condition lives on the mapping row;
     * the variant ladder emerges from multiple templates each owning one row.
     *
     * The old unique(user_id, event_type) enforced "one template per event
     * type" - exactly the assumption variants break. We re-key it to
     * (user_id, template_id, event_type): a single template still renders one
     * way (so one row per event type), but many templates can map the same
     * event type with different conditions.
     */
    public function up(): void
    {
        Schema::table('event_template_mappings', function (Blueprint $table) {
            // null = base/catch-all (today's behavior); 'at_least' / 'exactly'
            $table->string('condition_type')->nullable()->after('event_type');
            // null for base rows; the bits/viewers/gift threshold otherwise
            $table->unsignedInteger('condition_value')->nullable()->after('condition_type');

            $table->dropUnique(['user_id', 'event_type']);
            $table->unique(['user_id', 'template_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::table('event_template_mappings', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'template_id', 'event_type']);
            $table->unique(['user_id', 'event_type']);

            $table->dropColumn(['condition_type', 'condition_value']);
        });
    }
};
