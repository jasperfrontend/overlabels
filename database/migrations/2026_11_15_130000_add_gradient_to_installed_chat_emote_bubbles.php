<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give every installed Chat Emote Bubbles overlay the two-color gradient.
 *
 * The recipe gained `bubble_color_2` and `gradient_angle` on 2026-11-15, and
 * its CSS now paints every look with a gradient between the two colors. A
 * recipe is copied into an overlay at install time with no update path, so an
 * overlay installed before that has neither the controls nor the CSS - and
 * since the designer's presets now name both keys, no preset ever shows as
 * the active one on it either.
 *
 * Each install gets, all or nothing:
 *  - its CSS patched in place, snippet by snippet. Not replaced wholesale: the
 *    overlay is the streamer's own editable copy, so a streamer who changed a
 *    margin keeps the margin. Every snippet below must be found exactly once,
 *    or the install is left exactly as it is and logged. A streamer who
 *    rewrote one of the look rules has CSS this cannot safely reason about.
 *  - the two controls, right after `bubble_color`, with the second color set
 *    to the first and the angle at 0, so nothing on stream changes until the
 *    streamer touches them.
 *  - the two tags added to `template_tags`, the render allowlist, which is
 *    otherwise only rebuilt when the overlay is saved.
 *
 * Installs from 2026-09-26 carry a first version of the CSS with a shine arc
 * on `.look-bubble .skin::after`, which the gradient's ring now uses; that
 * block is removed when it is there, verbatim.
 *
 * Matched through the install record, the way the vocabulary backfill of
 * 2026-09-27 was. Every snippet is frozen here: reading the recipe on disk
 * would patch in whatever the document says on the day this runs.
 */
return new class extends Migration
{
    private const SLUG = 'chat-emote-bubbles';

    private const OVERLAY_REF = 'bubbles';

    /** Each `from` must occur exactly once, or the install is skipped. */
    private const PATCHES = [
        [
            'from' => "  --tint: [[[c:bubble_color]]];\n",
            'to' => "  --tint: [[[c:bubble_color]]];\n"
                ."  --tint2: [[[c:bubble_color_2]]];\n"
                ."  /* The gradient runs from the first color at the top to the second at the\n"
                ."     bottom, and the angle turns that clockwise: 90 runs right to left, 180\n"
                ."     bottom to top. CSS's own 0deg points up, hence the 180deg. */\n"
                ."  --flow: calc(180deg + [[[c:gradient_angle]]]deg);\n"
                ."  --fill: linear-gradient(var(--flow), var(--tint), var(--tint2));\n",
        ],
        [
            'from' => ".look-bubble .skin {\n"
                ."  border-radius: 50%;\n"
                ."  background: radial-gradient(circle at 32% 28%, color-mix(in srgb, var(--tint) 45%, transparent), color-mix(in srgb, var(--tint) 6%, transparent) 40%, color-mix(in srgb, var(--tint) 3%, transparent) 62%, color-mix(in srgb, var(--tint) 24%, transparent) 100%);\n"
                ."  box-shadow: inset 0 0 0 1.5px color-mix(in srgb, var(--tint) 78%, transparent), inset -5px -7px 12px color-mix(in srgb, var(--tint) 16%, transparent), 0 2px 10px rgba(0, 0, 0, 0.14);\n"
                ."}\n",
            'to' => ".look-bubble .skin {\n"
                ."  border-radius: 50%;\n"
                ."  box-shadow: inset -5px -7px 12px color-mix(in srgb, color-mix(in srgb, var(--tint), var(--tint2)) 16%, transparent), 0 2px 10px rgba(0, 0, 0, 0.14);\n"
                ."}\n"
                .".look-bubble .skin::before,\n"
                .".look-bubble .skin::after {\n"
                ."  content: \"\";\n"
                ."  position: absolute;\n"
                ."  inset: 0;\n"
                ."  border-radius: 50%;\n"
                ."  background: var(--fill);\n"
                ."}\n"
                ."/* The film: strongest at the shine, thin across the middle, thicker at the rim. */\n"
                .".look-bubble .skin::before {\n"
                ."  -webkit-mask: radial-gradient(circle at 32% 28%, rgb(0 0 0 / 0.45), rgb(0 0 0 / 0.06) 40%, rgb(0 0 0 / 0.03) 62%, rgb(0 0 0 / 0.24) 100%);\n"
                ."  mask: radial-gradient(circle at 32% 28%, rgb(0 0 0 / 0.45), rgb(0 0 0 / 0.06) 40%, rgb(0 0 0 / 0.03) 62%, rgb(0 0 0 / 0.24) 100%);\n"
                ."}\n"
                ."/* The ring. */\n"
                .".look-bubble .skin::after {\n"
                ."  opacity: 0.78;\n"
                ."  -webkit-mask: radial-gradient(closest-side, transparent calc(100% - 2px), #000 calc(100% - 0.5px));\n"
                ."  mask: radial-gradient(closest-side, transparent calc(100% - 2px), #000 calc(100% - 0.5px));\n"
                ."}\n",
        ],
        [
            'from' => "  background: var(--tint);\n  opacity: 0.92;\n",
            'to' => "  background: var(--fill);\n  opacity: 0.92;\n",
        ],
        [
            'from' => ".look-heart .skin::before { background: color-mix(in srgb, var(--tint) 30%, transparent); }\n",
            'to' => ".look-heart .skin::before { background: var(--fill); opacity: 0.3; }\n",
        ],
        [
            'from' => "  background: color-mix(in srgb, var(--tint) 85%, transparent);\n",
            'to' => "  background: var(--fill);\n  opacity: 0.85;\n",
        ],
    ];

    /** Applied when present, skipped when not: neither decides anything. */
    private const OPTIONAL_PATCHES = [
        // The 2026-09-26 shine arc, which would fight the ring for ::after.
        [
            'from' => ".look-bubble .skin::after {\n"
                ."  content: \"\";\n"
                ."  position: absolute;\n"
                ."  top: 10%;\n"
                ."  left: 13%;\n"
                ."  width: 30%;\n"
                ."  height: 30%;\n"
                ."  border-radius: 50%;\n"
                ."  border-top: 3px solid color-mix(in srgb, var(--tint) 88%, transparent);\n"
                ."  border-left: 3px solid transparent;\n"
                ."  transform: rotate(-24deg);\n"
                ."}\n"
                ."\n",
            'to' => '',
        ],
        [
            'from' => "/* Looks. */\n",
            'to' => "/* Looks. Each one paints the two-color gradient and cuts it to shape with a\n"
                ."   mask, because a gradient cannot be mixed, shadowed or bordered the way a\n"
                ."   single color can. */\n",
        ],
    ];

    private const TAGS = ['c:bubble_color_2', 'c:gradient_angle'];

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

                    $outcome = $this->upgrade((int) $templateId);

                    if ($outcome === 'patched') {
                        $patched++;
                    } elseif ($outcome !== 'done') {
                        $skipped[$templateId] = $outcome;
                    }
                }
            });

        Log::info('Chat Emote Bubbles gradient backfill', ['patched' => $patched, 'skipped' => count($skipped)]);

        foreach ($skipped as $templateId => $reason) {
            Log::warning('Chat Emote Bubbles gradient backfill skipped an install', [
                'overlay_template_id' => $templateId,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * One install. Returns 'patched', 'done' (already has the gradient), or
     * the reason it was left alone.
     */
    private function upgrade(int $templateId): string
    {
        $template = DB::table('overlay_templates')->where('id', $templateId)->first(['id', 'owner_id', 'css', 'template_tags']);

        if (! $template) {
            return 'overlay is gone';
        }

        $controls = DB::table('overlay_controls')->where('overlay_template_id', $templateId)->get(['id', 'key', 'value', 'sort_order']);
        $color = $controls->firstWhere('key', 'bubble_color');

        if ($controls->contains('key', 'bubble_color_2') || $controls->contains('key', 'gradient_angle')) {
            return 'done';
        }

        if (! $color) {
            return 'no bubble_color control';
        }

        $css = (string) $template->css;

        foreach (self::OPTIONAL_PATCHES as $patch) {
            if (substr_count($css, $patch['from']) === 1) {
                $css = str_replace($patch['from'], $patch['to'], $css);
            }
        }

        foreach (self::PATCHES as $i => $patch) {
            if (substr_count($css, $patch['from']) !== 1) {
                return "css edited: snippet {$i} not found exactly once";
            }
            $css = str_replace($patch['from'], $patch['to'], $css);
        }

        $tags = json_decode((string) $template->template_tags, true);
        $tags = is_array($tags) ? $tags : [];
        foreach (self::TAGS as $tag) {
            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        $now = now();
        $after = (int) $color->sort_order;

        DB::transaction(function () use ($templateId, $template, $css, $tags, $color, $after, $now) {
            // Make room right after bubble_color, where a fresh install puts
            // them. updated_at is left alone: it is every control's `_at`.
            DB::table('overlay_controls')
                ->where('overlay_template_id', $templateId)
                ->where('sort_order', '>', $after)
                ->increment('sort_order', 2);

            DB::table('overlay_controls')->insert([
                [
                    'overlay_template_id' => $templateId,
                    'user_id' => $template->owner_id,
                    'key' => 'bubble_color_2',
                    'label' => 'Second color',
                    'description' => 'Where the gradient ends. The same as Bubble color gives one plain color.',
                    'type' => 'color',
                    'value' => (string) $color->value,
                    'config' => null,
                    'sort_order' => $after + 1,
                    'source' => null,
                    'source_managed' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'overlay_template_id' => $templateId,
                    'user_id' => $template->owner_id,
                    'key' => 'gradient_angle',
                    'label' => 'Gradient angle',
                    'description' => 'Turns the gradient, in degrees. 0 runs from Bubble color at the top to Second color at the bottom, 90 runs right to left, 180 bottom to top, 270 left to right.',
                    'type' => 'number',
                    'value' => '0',
                    'config' => json_encode(['min' => 0, 'max' => 360, 'step' => 1, 'reset_value' => 0, 'random' => false, 'random_interval' => null]),
                    'sort_order' => $after + 2,
                    'source' => null,
                    'source_managed' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            DB::table('overlay_templates')->where('id', $templateId)->update([
                'css' => $css,
                'template_tags' => json_encode(array_values($tags)),
                'updated_at' => $now,
            ]);
        });

        return 'patched';
    }

    /**
     * The overlay is the streamer's own copy, and by the time this could run
     * backwards they may have set a gradient they like. Nothing to undo.
     */
    public function down(): void {}
};
