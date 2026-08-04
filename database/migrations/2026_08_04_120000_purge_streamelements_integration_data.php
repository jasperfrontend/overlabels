<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes every trace of the retired StreamElements integration from the data.
 *
 * The driver, controller, routes and listener are gone in the same commit, which
 * leaves the rows behind in a state the app can no longer reach or repair:
 *
 * - `overlay_controls` with source `streamelements` are `source_managed`, so the
 *   dashboard renders them read-only and `setValue()`/`update()` answer 403.
 *   Without the driver nothing will ever write them again either, so they would
 *   sit in the user's control list forever, permanently stale and undeletable.
 * - `external_integrations` rows still hold an encrypted StreamElements JWT.
 *   Dropping the integration and keeping the credential is the wrong half of the
 *   decision - the point was to stop holding it.
 * - `external_events` rows are append-only history whose `service` value no
 *   longer resolves to a registered driver, so the events feed and the stream
 *   session income view would have to special-case a service that is not there.
 *
 * Irreversible on purpose: `down()` cannot invent an encrypted JWT it never had,
 * and the integration it would restore no longer exists in the codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('overlay_controls')->where('source', 'streamelements')->delete();
        DB::table('external_events')->where('service', 'streamelements')->delete();
        DB::table('external_integrations')->where('service', 'streamelements')->delete();
    }

    public function down(): void
    {
        // Not reversible. See the class docblock.
    }
};
