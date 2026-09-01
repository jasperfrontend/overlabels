<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill farthest_checkin_name_this_stream for every existing checkin
 * integration. The distance record existed without the person who set it -
 * `farthest_checkin_this_stream` had no complementary name control, so a
 * "who came furthest" callout could not name the viewer.
 *
 * New connections pick it up via getAutoProvisionedControls(); provision()
 * only runs at connect, so existing users need the row explicitly (the
 * gps_accuracy backfill precedent).
 *
 * The explicit reset_value keeps the go-live reset from writing the literal
 * string "0" into a text control (the latest_cheerer_name lesson).
 *
 * Table names are literal, never Eloquent models - a migration is dated but
 * a model reference resolves against today's codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        $userIds = DB::table('external_integrations')
            ->where('service', 'checkin')
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $exists = DB::table('overlay_controls')
                ->where('user_id', $userId)
                ->where('source', 'checkin')
                ->where('key', 'farthest_checkin_name_this_stream')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('overlay_controls')->insert([
                'user_id' => $userId,
                'overlay_template_id' => null,
                'key' => 'farthest_checkin_name_this_stream',
                'label' => 'Farthest Checkin Name This Stream',
                'type' => 'text',
                'value' => '',
                'config' => json_encode(['reset_value' => '']),
                'sort_order' => 0,
                'source' => 'checkin',
                'source_managed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('overlay_controls')
            ->where('source', 'checkin')
            ->where('source_managed', true)
            ->where('key', 'farthest_checkin_name_this_stream')
            ->delete();
    }
};
