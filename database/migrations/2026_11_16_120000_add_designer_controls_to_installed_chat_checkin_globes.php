<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give every installed Chat Checkin globe the designer's controls.
 *
 * The recipe gained a designer on 2026-11-16: twelve look controls (colors,
 * dot size, spin, tilt, the names next to the pins, the font) and a
 * `hometown` text control, with the globe's CSS reading them instead of
 * hard-coded values. A recipe is copied into an overlay at install time with
 * no update path, so an install from before that has neither the controls nor
 * the CSS, and its designer page would render only the two panel switches.
 *
 * Each install gets, all or nothing:
 *  - its CSS replaced, but only when it is still exactly the CSS the recipe
 *    shipped. The overlay is the streamer's own editable copy, so a streamer
 *    who changed it keeps it, and the install is skipped and logged.
 *  - the thirteen controls, after the two panel switches it already has, set
 *    to the Classic preset: the exact look the old CSS hard-coded, so nothing
 *    on stream changes until the streamer opens the designer. (The one
 *    exception is the font: generic `monospace` is not a Bunny Fonts family,
 *    so Classic names JetBrains Mono.)
 *  - `hometown` set to the home place in the account's Chat Checkin
 *    integration settings, so a streamer who had one keeps their distances:
 *    from now on the control is what BotCheckinController measures from.
 *  - the HUD's "farthest from home" line naming the hometown, when the line
 *    is still the one the recipe shipped.
 *  - the new tags added to `template_tags`, the render allowlist, which is
 *    otherwise only rebuilt when the overlay is saved.
 *
 * Matched through the install record. Every snippet and value is frozen
 * here: reading the recipe on disk would patch in whatever the document says
 * on the day this runs.
 */
return new class extends Migration
{
    private const SLUG = 'chat-checkin';

    private const OVERLAY_REF = 'globe';

    /** The CSS every install before 2026-11-16 was given, as stored. */
    private const OLD_CSS = "body { background: transparent; margin: 0; }\n"
        .".globe-stage { position: relative; width: 100vw; height: 100vh; font-family: monospace; }\n"
        .".ol-checkin-globe {\n"
        ."  position: absolute; inset: 0;\n"
        ."  --globe-shell-color: #000;\n"
        ."  --globe-shell-opacity: 0.5;\n"
        ."  --globe-dot-color: #2dd4bf;\n"
        ."  --globe-pin-color: #bfe3e0;\n"
        ."  --globe-rotation-seconds: 30;\n"
        ."  --globe-dot-size: 5;\n"
        ."}\n"
        .".ol-globe-label { color: #dfe8e8; font-size: 13px; letter-spacing: 0.08em; text-shadow: 0 0 6px #000; transition: opacity 0.3s; }\n"
        .".globe-hud { position: absolute; top: 24px; left: 24px; color: #1daaff; }\n"
        .".hud-latest { color: #8fb6b3; margin-top: 4px; }\n"
        .".pin-list { position: absolute; right: 24px; top: 24px; color: #6f8f8c; font-size: 12px; }\n"
        .'.pin-row span { color: #1daaff; }';

    private const NEW_CSS = ":root {\n"
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

    /** Applied when the line is still the shipped one; skipped otherwise. */
    private const HTML_FROM = '      farthest from home: [[[c:checkin:farthest_checkin_this_stream|distance:km]]]km';

    private const HTML_TO = '      farthest from [[[if:c:hometown]]][[[c:hometown]]][[[else]]]home[[[endif]]]: [[[c:checkin:farthest_checkin_this_stream|distance:km]]]km';

    /**
     * The controls, in order, at the Classic preset's values. `hometown`'s
     * value is filled per account.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: array<string, mixed>|null, 5: string}>
     */
    private const CONTROLS = [
        ['land_color', 'Land color', 'color', '#2dd4bf', null, 'The color of the dots that draw the continents.'],
        ['land_dot_size', 'Land dot size', 'number', '5', ['min' => 1, 'max' => 10, 'step' => 0.5, 'reset_value' => 5, 'random' => false, 'random_interval' => null], 'How big each continent dot is, in pixels.'],
        ['globe_color', 'Globe color', 'color', '#000000', null, 'The color of the globe itself, under the continents.'],
        ['globe_opacity', 'Globe opacity', 'number', '0.5', ['min' => 0, 'max' => 1, 'step' => 0.05, 'reset_value' => 0.92, 'random' => false, 'random_interval' => null], 'How solid the globe is. 1 is fully solid; lower lets the far side of the world show through faintly.'],
        ['spin_seconds', 'Seconds per turn', 'number', '30', ['min' => 0, 'max' => 240, 'step' => 5, 'reset_value' => 60, 'random' => false, 'random_interval' => null], 'How many seconds one full turn takes. 0 stops the globe.'],
        ['tilt', 'Tilt', 'number', '18', ['min' => -40, 'max' => 40, 'step' => 1, 'reset_value' => 18, 'random' => false, 'random_interval' => null], 'How far the top of the globe leans toward you, in degrees.'],
        ['pin_color', 'Pin color', 'color', '#bfe3e0', null, 'The pins that stick out where viewers checked in.'],
        ['label_color', 'Name color', 'color', '#dfe8e8', null, 'The viewer names floating next to the pins.'],
        ['label_size', 'Name size', 'number', '13', ['min' => 9, 'max' => 32, 'step' => 1, 'reset_value' => 14, 'random' => false, 'random_interval' => null], 'The size of the viewer names next to the pins, in pixels.'],
        ['font', 'Font', 'text', 'JetBrains Mono', ['webfont' => true], 'The font for the names, the HUD and the pin list, any family Bunny Fonts serves. The overlay loads it itself.'],
        ['text_color', 'Text color', 'color', '#8fb6b3', null, 'The HUD\'s detail lines and the pin list.'],
        ['accent_color', 'Accent color', 'color', '#1daaff', null, 'The HUD\'s headline and the places in the pin list.'],
        ['hometown', 'Your location', 'text', '', null, 'Where you stream from, as City, country code - like Rotterdam, NL. The farthest checkin is measured from here and the HUD names it. Leave it empty to measure nothing.'],
    ];

    private const TAGS = [
        'c:land_color', 'c:land_dot_size', 'c:globe_color', 'c:globe_opacity', 'c:spin_seconds', 'c:tilt',
        'c:pin_color', 'c:label_color', 'c:label_size', 'c:font', 'c:text_color', 'c:accent_color',
        'c:hometown', 'if:c:hometown',
    ];

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
            ->select('id', 'user_id', 'primitive_map')
            ->chunkById(100, function ($instances) use (&$patched, &$skipped) {
                foreach ($instances as $instance) {
                    $map = is_string($instance->primitive_map)
                        ? json_decode($instance->primitive_map, true)
                        : $instance->primitive_map;
                    $templateId = is_array($map) ? ($map['overlays'][self::OVERLAY_REF] ?? null) : null;

                    if (! $templateId) {
                        continue;
                    }

                    $outcome = $this->upgrade((int) $templateId, (int) $instance->user_id);

                    if ($outcome === 'patched') {
                        $patched++;
                    } elseif ($outcome !== 'done') {
                        $skipped[$templateId] = $outcome;
                    }
                }
            });

        Log::info('Chat Checkin designer backfill', ['patched' => $patched, 'skipped' => count($skipped)]);

        foreach ($skipped as $templateId => $reason) {
            Log::warning('Chat Checkin designer backfill skipped an install', [
                'overlay_template_id' => $templateId,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * One install. Returns 'patched', 'done' (already has the controls), or
     * the reason it was left alone.
     */
    private function upgrade(int $templateId, int $userId): string
    {
        $template = DB::table('overlay_templates')->where('id', $templateId)->first(['id', 'owner_id', 'html', 'css', 'template_tags']);

        if (! $template) {
            return 'overlay is gone';
        }

        $controls = DB::table('overlay_controls')->where('overlay_template_id', $templateId)->get(['key', 'sort_order']);

        if ($controls->contains('key', 'land_color') || $controls->contains('key', 'hometown')) {
            return 'done';
        }

        if (str_replace("\r\n", "\n", trim((string) $template->css)) !== self::OLD_CSS) {
            return 'css edited';
        }

        $html = (string) $template->html;
        if (substr_count($html, self::HTML_FROM) === 1) {
            $html = str_replace(self::HTML_FROM, self::HTML_TO, $html);
        }

        $tags = json_decode((string) $template->template_tags, true);
        $tags = is_array($tags) ? $tags : [];
        foreach (self::TAGS as $tag) {
            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        $hometown = $this->hometownFor($userId);
        $next = ((int) $controls->max('sort_order')) + 1;
        $now = now();

        $rows = [];
        foreach (self::CONTROLS as $i => [$key, $label, $type, $value, $config, $description]) {
            $rows[] = [
                'overlay_template_id' => $templateId,
                'user_id' => $template->owner_id,
                'key' => $key,
                'label' => $label,
                'description' => $description,
                'type' => $type,
                'value' => $key === 'hometown' ? $hometown : $value,
                'config' => $config === null ? null : json_encode($config),
                'sort_order' => $next + $i,
                'source' => null,
                'source_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($templateId, $html, $tags, $rows, $now) {
            DB::table('overlay_controls')->insert($rows);

            DB::table('overlay_templates')->where('id', $templateId)->update([
                'html' => $html,
                'css' => self::NEW_CSS,
                'template_tags' => json_encode(array_values($tags)),
                'updated_at' => $now,
            ]);
        });

        return 'patched';
    }

    /** The home place label from the account's Chat Checkin settings, or ''. */
    private function hometownFor(int $userId): string
    {
        $settings = DB::table('external_integrations')
            ->where('user_id', $userId)
            ->where('service', 'checkin')
            ->value('settings');

        $settings = is_string($settings) ? json_decode($settings, true) : $settings;

        return is_array($settings) ? (string) ($settings['home_place_label'] ?? '') : '';
    }

    /**
     * The overlay is the streamer's own copy, and by the time this could run
     * backwards they may have picked a look they like. Nothing to undo.
     */
    public function down(): void {}
};
