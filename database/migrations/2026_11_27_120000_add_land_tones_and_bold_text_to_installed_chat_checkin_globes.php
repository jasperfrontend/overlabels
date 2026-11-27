<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give every installed Chat Checkin globe the "Mixed land tones" and "Bold
 * text" switches.
 *
 * On 2026-11-27 the globe's continents became flat hexagon tiles, and the
 * recipe gained a `land_tones` boolean that paints each tile the land color,
 * a little lighter or a little darker, and a `font_bold` boolean for the
 * names, the HUD and the pin list. A recipe is copied into an overlay at
 * install time with no update path, so an install from before that has
 * neither the controls nor the CSS lines that read them. The same CSS gives
 * the names next to the pins a hard 1px outline in place of the soft 6px
 * shadow, which blurred into the tiles behind them.
 *
 * Each install gets, all or nothing:
 *  - its CSS replaced, but only when it is still exactly the CSS the
 *    2026-11-16 designer backfill (and every install since) shipped. The
 *    overlay is the streamer's own editable copy, so an edited one is skipped
 *    and logged, the same rule that backfill followed.
 *  - the `land_tones` and `font_bold` controls, off, after the controls it
 *    already has, so nothing on stream changes until the streamer switches
 *    one on.
 *  - `c:land_tones` and `c:font_bold` added to `template_tags`, the render allowlist, which is
 *    otherwise only rebuilt when the overlay is saved.
 *
 * Separately, on every install whose CSS was skipped or not: the land dot
 * size's description, when it is still the shipped one. The size now means a
 * share of the gap between tiles rather than pixels, for every globe, because
 * that is the renderer and not the overlay.
 *
 * Matched through the install record. Every snippet and value is frozen here:
 * reading the recipe on disk would patch in whatever the document says on the
 * day this runs.
 */
return new class extends Migration
{
    private const SLUG = 'chat-checkin';

    private const OVERLAY_REF = 'globe';

    /** The CSS every install between 2026-11-16 and 2026-11-27 carries. */
    private const OLD_CSS = ":root {\n"
        ."  --land: [[[c:land_color]]];\n"
        ."  --land-size: [[[c:land_dot_size]]];\n"
        ."  --globe: [[[c:globe_color]]];\n"
        ."  --globe-alpha: [[[c:globe_opacity]]];\n"
        ."  --spin: [[[c:spin_seconds]]];\n"
        ."  --tilt: [[[c:tilt]]];\n"
        ."  --pin: [[[c:pin_color]]];\n"
        ."  --label: [[[c:label_color]]];\n"
        ."  --label-size: [[[c:label_size]]]px;\n"
        ."  --font: [[[c:font]]];\n"
        ."  --text: [[[c:text_color]]];\n"
        ."  --accent: [[[c:accent_color]]];\n"
        ."}\n"
        ."body { background: transparent; margin: 0; }\n"
        .".globe-stage { position: relative; width: 100vw; height: 100vh; font-family: var(--font), ui-monospace, monospace; }\n"
        ."/* the globe reads these off its own element twice a second, so a designer\n"
        ."   change lands without reloading the browser source */\n"
        .".ol-checkin-globe {\n"
        ."  position: absolute; inset: 0;\n"
        ."  --globe-shell-color: var(--globe);\n"
        ."  --globe-shell-opacity: var(--globe-alpha);\n"
        ."  --globe-dot-color: var(--land);\n"
        ."  --globe-dot-size: var(--land-size);\n"
        ."  --globe-pin-color: var(--pin);\n"
        ."  --globe-rotation-seconds: var(--spin);\n"
        ."  --globe-tilt-degrees: var(--tilt);\n"
        ."}\n"
        .".ol-globe-label { color: var(--label); font-size: var(--label-size); letter-spacing: 0.08em; text-shadow: 0 0 6px #000; transition: opacity 0.3s; }\n"
        .".globe-hud { position: absolute; top: 24px; left: 24px; color: var(--accent); }\n"
        .".hud-latest { color: var(--text); margin-top: 4px; }\n"
        .".pin-list { position: absolute; right: 24px; top: 24px; color: var(--text); font-size: 12px; }\n"
        .'.pin-row span { color: var(--accent); }';

    private const NEW_CSS = ":root {\n"
        ."  --land: [[[c:land_color]]];\n"
        ."  --land-size: [[[c:land_dot_size]]];\n"
        ."  --land-tones: [[[c:land_tones]]];\n"
        ."  --globe: [[[c:globe_color]]];\n"
        ."  --globe-alpha: [[[c:globe_opacity]]];\n"
        ."  --spin: [[[c:spin_seconds]]];\n"
        ."  --tilt: [[[c:tilt]]];\n"
        ."  --pin: [[[c:pin_color]]];\n"
        ."  --label: [[[c:label_color]]];\n"
        ."  --label-size: [[[c:label_size]]]px;\n"
        ."  --font: [[[c:font]]];\n"
        ."  --bold: [[[c:font_bold]]];\n"
        ."  --text: [[[c:text_color]]];\n"
        ."  --accent: [[[c:accent_color]]];\n"
        ."}\n"
        ."body { background: transparent; margin: 0; }\n"
        .".globe-stage { position: relative; width: 100vw; height: 100vh; font-family: var(--font), ui-monospace, monospace; font-weight: calc(400 + 300 * var(--bold)); }\n"
        ."/* the globe reads these off its own element twice a second, so a designer\n"
        ."   change lands without reloading the browser source */\n"
        .".ol-checkin-globe {\n"
        ."  position: absolute; inset: 0;\n"
        ."  --globe-shell-color: var(--globe);\n"
        ."  --globe-shell-opacity: var(--globe-alpha);\n"
        ."  --globe-dot-color: var(--land);\n"
        ."  --globe-dot-size: var(--land-size);\n"
        ."  --globe-dot-tones: var(--land-tones);\n"
        ."  --globe-pin-color: var(--pin);\n"
        ."  --globe-rotation-seconds: var(--spin);\n"
        ."  --globe-tilt-degrees: var(--tilt);\n"
        ."}\n"
        .".ol-globe-label { color: var(--label); font-size: var(--label-size); letter-spacing: 0.08em; text-shadow: 1px 0 0 #000, -1px 0 0 #000, 0 1px 0 #000, 0 -1px 0 #000, 1px 1px 0 #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000; transition: opacity 0.3s; }\n"
        .".globe-hud { position: absolute; top: 24px; left: 24px; color: var(--accent); }\n"
        .".hud-latest { color: var(--text); margin-top: 4px; }\n"
        .".pin-list { position: absolute; right: 24px; top: 24px; color: var(--text); font-size: 12px; }\n"
        .'.pin-row span { color: var(--accent); }';

    /** Both off. land_tones decides whether an install was done already. */
    private const CONTROLS = [
        [
            'key' => 'land_tones',
            'label' => 'Mixed land tones',
            'type' => 'boolean',
            'value' => '0',
            'config' => ['min' => null, 'max' => null, 'step' => 1, 'reset_value' => 0, 'random' => false, 'random_interval' => null],
            'description' => 'Gives every continent tile one of three tones of the land color: the color itself, a little lighter or a little darker.',
        ],
        [
            'key' => 'font_bold',
            'label' => 'Bold text',
            'type' => 'boolean',
            'value' => '0',
            'config' => ['min' => null, 'max' => null, 'step' => 1, 'reset_value' => 0, 'random' => false, 'random_interval' => null],
            'description' => 'Shows the names, the HUD and the pin list in bold.',
        ],
    ];

    private const TAGS = ['c:land_tones', 'c:font_bold'];

    private const SIZE_KEY = 'land_dot_size';

    private const SIZE_DESCRIPTION_FROM = 'How big each continent dot is, in pixels.';

    private const SIZE_DESCRIPTION_TO = 'How big each continent tile is. 10 and the tiles nearly touch, 5 and they are half that wide.';

    public function up(): void
    {
        $recipeIds = DB::table('recipes')->where('slug', self::SLUG)->pluck('id');

        if ($recipeIds->isEmpty()) {
            return;
        }

        $patched = 0;
        $skipped = [];

        DB::table('recipe_instances')
            ->whereIn('recipe_id', $recipeIds)
            ->select('id', 'primitive_map')
            ->chunkById(100, function ($instances) use (&$patched, &$skipped) {
                foreach ($instances as $instance) {
                    $map = is_string($instance->primitive_map)
                        ? json_decode($instance->primitive_map, true)
                        : $instance->primitive_map;
                    $templateId = is_array($map) ? ($map['overlays'][self::OVERLAY_REF] ?? null) : null;

                    if (! $templateId) {
                        continue;
                    }

                    $this->describeSize((int) $templateId);
                    $outcome = $this->upgrade((int) $templateId);

                    if ($outcome === 'patched') {
                        $patched++;
                    } elseif ($outcome !== 'done') {
                        $skipped[$templateId] = $outcome;
                    }
                }
            });

        Log::info('Chat Checkin land tones and bold upgrade', ['patched' => $patched, 'skipped' => count($skipped)]);

        foreach ($skipped as $templateId => $reason) {
            Log::warning('Chat Checkin land tones and bold upgrade skipped an install', [
                'overlay_template_id' => $templateId,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * One install. Returns 'patched', 'done' (already has the controls), or
     * the reason it was left alone.
     */
    private function upgrade(int $templateId): string
    {
        $template = DB::table('overlay_templates')->where('id', $templateId)->first(['id', 'owner_id', 'css', 'template_tags']);

        if (! $template) {
            return 'overlay is gone';
        }

        $controls = DB::table('overlay_controls')->where('overlay_template_id', $templateId)->get(['key', 'sort_order']);

        if ($controls->contains('key', self::CONTROLS[0]['key'])) {
            return 'done';
        }

        if (str_replace("\r\n", "\n", trim((string) $template->css)) !== self::OLD_CSS) {
            return 'css edited';
        }

        $tags = json_decode((string) $template->template_tags, true);
        $tags = is_array($tags) ? $tags : [];
        foreach (self::TAGS as $tag) {
            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        $now = now();
        $next = ((int) $controls->max('sort_order')) + 1;

        $rows = [];
        foreach (self::CONTROLS as $i => $control) {
            $rows[] = [
                'overlay_template_id' => $template->id,
                'user_id' => $template->owner_id,
                'key' => $control['key'],
                'label' => $control['label'],
                'description' => $control['description'],
                'type' => $control['type'],
                'value' => $control['value'],
                'config' => json_encode($control['config']),
                'sort_order' => $next + $i,
                'source' => null,
                'source_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($template, $rows, $tags, $now) {
            DB::table('overlay_controls')->insert($rows);

            DB::table('overlay_templates')->where('id', $template->id)->update([
                'css' => self::NEW_CSS,
                'template_tags' => json_encode(array_values($tags)),
                'updated_at' => $now,
            ]);
        });

        return 'patched';
    }

    /** The size knob's new meaning, where its description is still the shipped one. */
    private function describeSize(int $templateId): void
    {
        DB::table('overlay_controls')
            ->where('overlay_template_id', $templateId)
            ->where('key', self::SIZE_KEY)
            ->where('description', self::SIZE_DESCRIPTION_FROM)
            ->update(['description' => self::SIZE_DESCRIPTION_TO]);
    }

    /**
     * The overlay is the streamer's own copy, and by the time this could run
     * backwards they may have switched the tones on. Nothing to undo.
     */
    public function down(): void {}
};
