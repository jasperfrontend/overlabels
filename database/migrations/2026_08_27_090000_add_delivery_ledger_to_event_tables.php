<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The delivery ledger: what became of each inbound event's alert.
 *
 * alert_id is the UUID renderEventAlert() mints for the AlertTriggered
 * broadcast; it rides the payload the broadcaster receives in the queue
 * worker, which closes the row by it. outcome is App\Enums\DeliveryOutcome.
 * All nullable and additive: existing rows keep null and simply predate the
 * ledger. See docs/design/event-delivery-ledger-2026-08.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['twitch_events', 'external_events'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->uuid('alert_id')->nullable()->index();
                $t->string('outcome', 32)->nullable();
                $t->timestamp('delivered_at')->nullable();
                $t->smallInteger('connections')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['twitch_events', 'external_events'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex([$t->getTable().'_alert_id_index']);
                $t->dropColumn(['alert_id', 'outcome', 'delivered_at', 'connections']);
            });
        }
    }
};
