<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Turn the Gradient angle of already-installed Chat Emote Bubbles overlays
 * into an angle knob.
 *
 * The recipe's `gradient_angle` now declares `angle_colors`, the two color
 * controls it turns, which is what makes the designer and the Values tab draw
 * a circle to drag instead of a slider. A recipe is copied into an overlay at
 * install time with no update path, so existing installs get it here.
 *
 * Matched through the install record, like the earlier backfills, so a
 * hand-made control called `gradient_angle` is never touched. A row that
 * already declares `angle_colors` is left as it is; otherwise the key is
 * added and the rest of the config and the value are kept. Frozen here.
 */
return new class extends Migration
{
    private const ANGLE_COLORS = ['bubble_color', 'bubble_color_2'];

    public function up(): void
    {
        $recipeIds = DB::table('recipes')->where('slug', 'chat-emote-bubbles')->pluck('id');

        if ($recipeIds->isEmpty()) {
            return;
        }

        DB::table('recipe_instances')
            ->whereIn('recipe_id', $recipeIds)
            ->select('id', 'primitive_map')
            ->chunkById(100, function ($instances) {
                foreach ($instances as $instance) {
                    $map = is_string($instance->primitive_map)
                        ? json_decode($instance->primitive_map, true)
                        : $instance->primitive_map;
                    $templateId = is_array($map) ? ($map['overlays']['bubbles'] ?? null) : null;

                    if (! $templateId) {
                        continue;
                    }

                    $row = DB::table('overlay_controls')
                        ->where('overlay_template_id', $templateId)
                        ->where('type', 'number')
                        ->where('key', 'gradient_angle')
                        ->first(['id', 'config']);

                    if (! $row) {
                        continue;
                    }

                    $config = json_decode((string) $row->config, true);
                    $config = is_array($config) ? $config : [];

                    if (array_key_exists('angle_colors', $config)) {
                        continue;
                    }

                    $config['angle_colors'] = self::ANGLE_COLORS;

                    DB::table('overlay_controls')->where('id', $row->id)->update([
                        'config' => json_encode($config),
                    ]);
                }
            });
    }

    /** Taking it away only turns the circle back into a slider. */
    public function down(): void {}
};
