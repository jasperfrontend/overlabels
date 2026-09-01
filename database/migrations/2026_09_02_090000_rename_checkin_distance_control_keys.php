<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Strip the baked-in unit from the checkin distance control keys.
 *
 * `farthest_checkin_km_this_stream` and `latest_checkin_distance_km` shipped
 * with "km" in the NAME, which fights the house formatter system: values are
 * stored in km (the `|distance:` pipe's documented input unit, same as GPS)
 * and presentation belongs to the pipe - `|distance:km` or `|distance:mi` -
 * not to the key. The unit-free names let both render from one control.
 *
 * Renames the provisioned rows in place so values, sort order and `_at`
 * timestamps survive; the driver provisions the new names from here on and
 * firstOrCreate would otherwise have left the old rows orphaned next to
 * fresh zeroed ones.
 *
 * Templates written against the old tags (one day old at this point) render
 * the old name as nothing after this - the shipped-yesterday window is why
 * this is a rename, not a compatibility alias.
 *
 * Table names are literal, never Eloquent models - a migration is dated but
 * a model reference resolves against today's codebase.
 */
return new class extends Migration
{
    private const RENAMES = [
        'farthest_checkin_km_this_stream' => ['key' => 'farthest_checkin_this_stream', 'label' => 'Farthest Checkin This Stream'],
        'latest_checkin_distance_km' => ['key' => 'latest_checkin_distance', 'label' => 'Latest Checkin Distance'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('overlay_controls')
                ->where('source', 'checkin')
                ->where('source_managed', true)
                ->where('key', $from)
                ->update(['key' => $to['key'], 'label' => $to['label']]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('overlay_controls')
                ->where('source', 'checkin')
                ->where('source_managed', true)
                ->where('key', $to['key'])
                ->update(['key' => $from]);
        }
    }
};
