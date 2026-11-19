<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Put `enabled_if` on the controls of already-installed product overlays.
 *
 * Chat Emote Bubbles and Twitch Chat now declare which knobs only matter
 * while another knob holds a value: the bubble colors and gradient angle are
 * unused on the Plain look, Spawn X and Y only steer the cannon, and the chat
 * background color is unused with no background. The designer and the Values
 * tab grey those knobs out. A recipe is copied into an overlay at install
 * time with no update path, so existing installs get the declarations here.
 *
 * Matched through the install record, like the 2026-09-27 vocabulary
 * backfill, so a hand-made control with the same key is never touched. A row
 * that already declares `enabled_if` is left as it is; otherwise the key is
 * added and the rest of the config and the value are kept. Frozen here.
 */
return new class extends Migration
{
    private const CONDITIONS = [
        'chat-emote-bubbles' => [
            'overlay' => 'bubbles',
            'controls' => [
                'bubble_color' => ['c:look != plain'],
                'bubble_color_2' => ['c:look != plain'],
                'gradient_angle' => ['c:look != plain'],
                'spawn_x' => ['c:spawn = cannon'],
                'spawn_y' => ['c:spawn = cannon'],
            ],
        ],
        'twitch-chat-overlay' => [
            'overlay' => 'chat',
            'controls' => [
                'background_color' => ['c:background != none'],
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::CONDITIONS as $slug => $product) {
            $recipeIds = DB::table('recipes')->where('slug', $slug)->pluck('id');

            if ($recipeIds->isEmpty()) {
                continue;
            }

            DB::table('recipe_instances')
                ->whereIn('recipe_id', $recipeIds)
                ->select('id', 'primitive_map')
                ->chunkById(100, function ($instances) use ($product) {
                    foreach ($instances as $instance) {
                        $map = is_string($instance->primitive_map)
                            ? json_decode($instance->primitive_map, true)
                            : $instance->primitive_map;
                        $templateId = is_array($map) ? ($map['overlays'][$product['overlay']] ?? null) : null;

                        if (! $templateId) {
                            continue;
                        }

                        $rows = DB::table('overlay_controls')
                            ->where('overlay_template_id', $templateId)
                            ->whereIn('key', array_keys($product['controls']))
                            ->get(['id', 'key', 'config']);

                        foreach ($rows as $row) {
                            $config = json_decode((string) $row->config, true);
                            $config = is_array($config) ? $config : [];

                            if (array_key_exists('enabled_if', $config)) {
                                continue;
                            }

                            $config['enabled_if'] = $product['controls'][$row->key];

                            DB::table('overlay_controls')->where('id', $row->id)->update([
                                'config' => json_encode($config),
                            ]);
                        }
                    }
                });
        }
    }

    /** A declaration only greys a knob out; taking it away changes nothing on stream. */
    public function down(): void {}
};
