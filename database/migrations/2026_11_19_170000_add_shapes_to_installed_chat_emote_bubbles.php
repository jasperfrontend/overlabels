<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give every installed Chat Emote Bubbles overlay the Shape look.
 *
 * The recipe gained a fifth look on 2026-11-19: twenty shapes drawn around
 * the emote, cut out of the gradient by an SVG mask in the markup, and four
 * controls to go with it (`shape`, `border_width`, `fill_strength`,
 * `breathing_room`). A recipe is copied into an overlay at install time with
 * no update path, so an overlay installed before that has none of it, and
 * since the designer's presets now name all four keys, no preset ever shows
 * as the active one on it either.
 *
 * Each install gets, all or nothing:
 *  - its HTML and CSS patched in place, snippet by snippet, never replaced:
 *    the overlay is the streamer's own editable copy. Every snippet must be
 *    found exactly once, or the install is left exactly as it is and logged.
 *    The CSS anchor is the gradient's `--fill` line, so an install the
 *    2026-11-15 gradient backfill had to skip is skipped here too.
 *  - the four controls where a fresh install puts them, `shape` right after
 *    `look` and the other three right after `gradient_angle`, at the recipe's
 *    defaults. `look` stays as it is, so nothing on stream changes until the
 *    streamer picks the Shape look.
 *  - how far a bubble travels off the screen, how far outside it starts and
 *    where it stops to pop, measured by its picture instead of its size.
 *    Every look but Shape keeps those equal to what they were.
 *  - the four tags added to `template_tags`, the render allowlist, which is
 *    otherwise only rebuilt when the overlay is saved.
 *
 * Alongside, and only where they are still exactly what the recipe shipped:
 * the Shape choice in the `look` select (before Plain), and the descriptions
 * of `look`, `bubble_size` and `bubble_color` that now mention the shape. A
 * row the streamer changed keeps their version.
 *
 * Matched through the install record, like the earlier bubbles backfills.
 * Every snippet is frozen here: reading the recipe on disk would patch in
 * whatever the document says on the day this runs.
 */
return new class extends Migration
{
    private const SLUG = 'chat-emote-bubbles';

    private const OVERLAY_REF = 'bubbles';

    private const SVG_DEFS = <<<'HTML'
      <svg class="ol-shape-defs" aria-hidden="true">
        <defs>
          <path id="ol-shape-ghost" transform="translate(32 36)" d="M-32,40 V-4 A32,32 0 0 1 32,-4 V40 L21,32 L11,40 L0,32 L-11,40 L-21,32 Z"/>
          <path id="ol-shape-cat" transform="translate(34.78 42)" d="M-34,-10 L-30,-42 L-10,-26 Q0,-29 10,-26 L30,-42 L34,-10 Q40,30 0,36 Q-40,30 -34,-10 Z"/>
          <path id="ol-shape-bauble" transform="translate(33 45)" d="M4,-41 A4,4 0 1 1 -4,-41 A4,4 0 1 1 4,-41 Z M-9,-37 H9 V-25 H-9 Z M33,6 A33,33 0 1 1 -33,6 A33,33 0 1 1 33,6 Z"/>
          <path id="ol-shape-drop" transform="translate(32 44)" d="M0,-44 C14,-24 32,-6 32,12 A32,32 0 0 1 -32,12 C-32,-6 -14,-24 0,-44 Z"/>
          <path id="ol-shape-starburst" transform="translate(42 42)" d="M0.00,-42.00 L8.80,-32.84 L21.00,-36.37 L24.04,-24.04 L36.37,-21.00 L32.84,-8.80 L42.00,0.00 L32.84,8.80 L36.37,21.00 L24.04,24.04 L21.00,36.37 L8.80,32.84 L0.00,42.00 L-8.80,32.84 L-21.00,36.37 L-24.04,24.04 L-36.37,21.00 L-32.84,8.80 L-42.00,0.00 L-32.84,-8.80 L-36.37,-21.00 L-24.04,-24.04 L-21.00,-36.37 L-8.80,-32.84 Z"/>
          <path id="ol-shape-octagram" transform="translate(42 42)" d="M0.00,-42.00 L11.48,-27.72 L29.70,-29.70 L27.72,-11.48 L42.00,0.00 L27.72,11.48 L29.70,29.70 L11.48,27.72 L0.00,42.00 L-11.48,27.72 L-29.70,29.70 L-27.72,11.48 L-42.00,0.00 L-27.72,-11.48 L-29.70,-29.70 L-11.48,-27.72 Z"/>
          <path id="ol-shape-hexagon" transform="translate(34.64 40)" d="M0.00,-40.00 L34.64,-20.00 L34.64,20.00 L0.00,40.00 L-34.64,20.00 L-34.64,-20.00 Z"/>
          <path id="ol-shape-octagon" transform="translate(36.96 36.96)" d="M15.31,-36.96 L36.96,-15.31 L36.96,15.31 L15.31,36.96 L-15.31,36.96 L-36.96,15.31 L-36.96,-15.31 L-15.31,-36.96 Z"/>
          <path id="ol-shape-squircle" transform="translate(36 36)" d="M-36,-14 Q-36,-36 -14,-36 H14 Q36,-36 36,-14 V14 Q36,36 14,36 H-14 Q-36,36 -36,14 Z"/>
          <path id="ol-shape-paw" transform="translate(39 39)" d="M-28,16 A28,24 0 1 1 28,16 A28,24 0 1 1 -28,16 Z M-21,-12 A9,9 0 1 1 -39,-12 A9,9 0 1 1 -21,-12 Z M-3,-30 A9,9 0 1 1 -21,-30 A9,9 0 1 1 -3,-30 Z M21,-30 A9,9 0 1 1 3,-30 A9,9 0 1 1 21,-30 Z M39,-12 A9,9 0 1 1 21,-12 A9,9 0 1 1 39,-12 Z"/>
          <path id="ol-shape-flame" transform="translate(30.19 44)" d="M0,-44 C10,-26 34,-14 30,12 C28,32 14,40 0,40 C-14,40 -28,32 -30,12 C-32,-4 -18,-10 -14,-28 C-8,-18 -4,-16 -2,-20 C2,-28 0,-36 0,-44 Z"/>
          <path id="ol-shape-decagram" transform="translate(39.94 42)" d="M0.00,-42.00 L9.89,-30.43 L24.69,-33.98 L25.89,-18.81 L39.94,-12.98 L32.00,0.00 L39.94,12.98 L25.89,18.81 L24.69,33.98 L9.89,30.43 L0.00,42.00 L-9.89,30.43 L-24.69,33.98 L-25.89,18.81 L-39.94,12.98 L-32.00,0.00 L-39.94,-12.98 L-25.89,-18.81 L-24.69,-33.98 L-9.89,-30.43 Z"/>
          <path id="ol-shape-heptagon" transform="translate(39 40)" d="M0.00,-40.00 L31.27,-24.94 L39.00,8.90 L17.36,36.04 L-17.36,36.04 L-39.00,8.90 L-31.27,-24.94 Z"/>
          <path id="ol-shape-pentagon" transform="translate(39.94 42)" d="M0.00,-42.00 L39.94,-12.98 L24.69,33.98 L-24.69,33.98 L-39.94,-12.98 Z"/>
          <path id="ol-shape-hexagram" transform="translate(36.37 42)" d="M0.00,-42.00 L12.50,-21.65 L36.37,-21.00 L25.00,0.00 L36.37,21.00 L12.50,21.65 L0.00,42.00 L-12.50,21.65 L-36.37,21.00 L-25.00,0.00 L-36.37,-21.00 L-12.50,-21.65 Z"/>
          <path id="ol-shape-decagon" transform="translate(40 38.04)" d="M12.36,-38.04 L32.36,-23.51 L40.00,0.00 L32.36,23.51 L12.36,38.04 L-12.36,38.04 L-32.36,23.51 L-40.00,0.00 L-32.36,-23.51 L-12.36,-38.04 Z"/>
          <path id="ol-shape-diamond" transform="translate(32 44)" d="M0.00,-44.00 L32.00,0.00 L0.00,44.00 L-32.00,0.00 Z"/>
          <path id="ol-shape-star" transform="translate(41.85 44)" d="M0.00,-44.00 L14.11,-19.42 L41.85,-13.60 L22.83,7.42 L25.86,35.60 L0.00,24.00 L-25.86,35.60 L-22.83,7.42 L-41.85,-13.60 L-14.11,-19.42 Z"/>
          <path id="ol-shape-triangle" transform="translate(39.84 46)" d="M0.00,-46.00 L39.84,23.00 L-39.84,23.00 Z"/>
          <path id="ol-shape-dodecagon" transform="translate(38.64 38.64)" d="M10.35,-38.64 L28.28,-28.28 L38.64,-10.35 L38.64,10.35 L28.28,28.28 L10.35,38.64 L-10.35,38.64 L-28.28,28.28 L-38.64,10.35 L-38.64,-10.35 L-28.28,-28.28 L-10.35,-38.64 Z"/>
          <clipPath id="ol-shape-clip"><use href="#ol-shape-[[[c:shape]]]"/></clipPath>
          <mask id="ol-shape-mask">
            <g class="ol-shape-fit">
              <use class="ol-shape-fill" href="#ol-shape-[[[c:shape]]]"/>
              <use class="ol-shape-ring" href="#ol-shape-[[[c:shape]]]" clip-path="url(#ol-shape-clip)"/>
            </g>
          </mask>
        </defs>
      </svg>
    HTML;

    private const CSS_VARS = <<<'CSS'
      /* The Shape look, as bare numbers (pixels and a fraction), because the SVG
         mask scales by them. */
      --size-n: [[[c:bubble_size]]];
      --border: [[[c:border_width]]];
      --room: [[[c:breathing_room]]];
      --fill-strength: calc([[[c:fill_strength]]] / 100);
      /* How far a bubble's picture reaches from its centre. A bubble that floats
         off the screen travels this far past the edge, so none of it is left
         showing. The Shape look can reach further than the size and sets its own. */
      --reach: var(--size);
      /* How far the picture reaches from its centre on the side it travels toward:
         the side that shows first when it floats in from outside, and the side
         that meets the edge when it gathers there to pop. */
      --lead: calc(var(--size) / 2);
    CSS;

    private const CSS_SHAPES = <<<'CSS'
    /* Shape: the gradient cut out by the mask in the markup. The emote keeps the
       size the bubble look gives it and the shape grows around it, so a star or a
       triangle comes out bigger than a squircle. --k is the pixels per unit of the
       drawing that fits half the emote, the breathing room and the border inside
       the shape's emote circle. Its reach is the taller side of the drawing from
       that circle's centre, plus what the sway's 7 degree tilt lifts a corner by
       (sin 7deg is 0.12, times half the width), never less than the size. */
    .look-shape {
      --k: calc((var(--size-n) * 0.28 + var(--room) + var(--border)) / var(--sr));
      --reach: max(var(--size), calc((max(-1 * var(--oy), var(--bh) + var(--oy)) + var(--bw) * 0.07) * var(--k) * 1px));
    }
    /* The lead is the drawing's top going up and its bottom going down, with the
       same allowance for the tilt. */
    .look-shape.dir-up { --lead: calc((-1 * var(--oy) + var(--bw) * 0.07) * var(--k) * 1px); }
    .look-shape.dir-down { --lead: calc((var(--bh) + var(--oy) + var(--bw) * 0.07) * var(--k) * 1px); }
    .look-shape .skin::before {
      content: "";
      position: absolute;
      left: calc(50% + var(--ox) * var(--k) * 1px);
      top: calc(50% + var(--oy) * var(--k) * 1px);
      width: calc(var(--bw) * var(--k) * 1px);
      height: calc(var(--bh) * var(--k) * 1px);
      background: var(--fill);
      -webkit-mask: url(#ol-shape-mask);
      mask: url(#ol-shape-mask);
    }
    .ol-shape-defs { position: absolute; width: 0; height: 0; overflow: hidden; }
    .ol-shape-fit { transform: scale(var(--k)); }
    .ol-shape-fill { fill: #fff; fill-opacity: var(--fill-strength); }
    /* In the drawing's units, hence the division by --k. The px matters: Firefox
       drops a unitless calc() here and draws a hairline. */
    .ol-shape-ring { fill: none; stroke: #fff; stroke-width: calc(2px * var(--border) / var(--k)); stroke-linejoin: round; }

    /* Each drawing, in its own units: the radius of the circle the emote sits in
       (--sr), where the drawing's box starts from that circle's centre (--ox,
       --oy), and the box's size (--bw, --bh). */
    .shape-ghost { --sr: 28; --ox: -32; --oy: -36; --bw: 64; --bh: 76; }
    .shape-cat { --sr: 27; --ox: -34.78; --oy: -46; --bw: 69.57; --bh: 78; }
    .shape-bauble { --sr: 33; --ox: -33; --oy: -51; --bw: 66; --bh: 84; }
    .shape-drop { --sr: 29; --ox: -32; --oy: -56; --bw: 64; --bh: 88; }
    .shape-starburst { --sr: 34; --ox: -42; --oy: -42; --bw: 84; --bh: 84; }
    .shape-octagram { --sr: 30; --ox: -42; --oy: -42; --bw: 84; --bh: 84; }
    .shape-hexagon { --sr: 34.64; --ox: -34.64; --oy: -40; --bw: 69.28; --bh: 80; }
    .shape-octagon { --sr: 36.96; --ox: -36.96; --oy: -36.96; --bw: 73.92; --bh: 73.92; }
    .shape-squircle { --sr: 36; --ox: -36; --oy: -36; --bw: 72; --bh: 72; }
    .shape-paw { --sr: 24; --ox: -39; --oy: -55; --bw: 78; --bh: 79; }
    .shape-flame { --sr: 25; --ox: -30.19; --oy: -58; --bw: 60.64; --bh: 84; }
    .shape-decagram { --sr: 32; --ox: -39.94; --oy: -42; --bw: 79.88; --bh: 84; }
    .shape-heptagon { --sr: 36.04; --ox: -39; --oy: -40; --bw: 78; --bh: 76.04; }
    .shape-pentagon { --sr: 33.98; --ox: -39.94; --oy: -42; --bw: 79.88; --bh: 75.98; }
    .shape-hexagram { --sr: 25; --ox: -36.37; --oy: -42; --bw: 72.74; --bh: 84; }
    .shape-decagon { --sr: 38.04; --ox: -40; --oy: -38.04; --bw: 80; --bh: 76.08; }
    .shape-diamond { --sr: 25.88; --ox: -32; --oy: -44; --bw: 64; --bh: 88; }
    .shape-star { --sr: 24; --ox: -41.85; --oy: -44; --bw: 83.7; --bh: 79.6; }
    .shape-triangle { --sr: 23; --ox: -39.84; --oy: -46; --bw: 79.68; --bh: 69; }
    .shape-dodecagon { --sr: 38.64; --ox: -38.64; --oy: -38.64; --bw: 77.28; --bh: 77.28; }
    CSS;

    private const HEART_IMG = '.look-heart .skin img { height: calc(var(--size) * 0.44); max-width: calc(var(--size) * 0.56); transform: translate(-50%, -56%); }';

    /** Each `from` must occur exactly once, or the install is skipped. */
    private const HTML_PATCHES = [
        [
            'from' => '<div class="ol-bubbles look-[[[c:look]]] dir-[[[c:direction]]]',
            'to' => '<div class="ol-bubbles look-[[[c:look]]] shape-[[[c:shape]]] dir-[[[c:direction]]]',
        ],
        [
            'from' => "  [[[foreach:emotes as e]]]\n",
            'to' => self::SVG_DEFS."\n  [[[foreach:emotes as e]]]\n",
        ],
    ];

    /** Each `from` must occur exactly once, or the install is skipped. */
    private const CSS_PATCHES = [
        [
            'from' => "  --fill: linear-gradient(var(--flow), var(--tint), var(--tint2));\n",
            'to' => "  --fill: linear-gradient(var(--flow), var(--tint), var(--tint2));\n".self::CSS_VARS."\n",
        ],
        [
            'from' => self::HEART_IMG."\n",
            'to' => self::HEART_IMG."\n\n".self::CSS_SHAPES."\n",
        ],
        // A floating-off bubble used to travel one size past the edge, which a
        // shape taller than the size outlives: its top stayed on screen.
        [
            'from' => "  --dist: calc(var(--edge) * 1vh + var(--offabs) + var(--size));\n",
            'to' => "  --dist: calc(var(--edge) * 1vh + var(--offabs) + var(--reach));\n",
        ],
        // The same for the side the bubble travels toward: a shape taller than
        // the size peeked in before floating in from outside, and was cut off
        // where it gathered at the edge to pop.
        [
            'from' => ".spawn-outside .bubble { --offabs: calc(var(--size) / 2 + 8px); }\n",
            'to' => ".spawn-outside .bubble { --offabs: calc(var(--lead) + 8px); }\n",
        ],
        [
            'from' => ".popping .bubble { --dist: calc(var(--edge) * 1vh + var(--offabs) - var(--size) / 2); }\n",
            'to' => ".popping .bubble { --dist: calc(var(--edge) * 1vh + var(--offabs) - var(--lead)); }\n",
        ],
    ];

    private const TAGS = ['c:shape', 'c:border_width', 'c:fill_strength', 'c:breathing_room'];

    private const SHIPPED_LOOKS = [
        ['value' => 'bubble', 'label' => 'Bubble', 'hint' => 'A soap bubble.'],
        ['value' => 'snow', 'label' => 'Snowflake', 'hint' => 'A snowflake behind the emote.'],
        ['value' => 'heart', 'label' => 'Heart', 'hint' => 'A heart-shaped bubble.'],
        ['value' => 'plain', 'label' => 'Plain', 'hint' => 'No bubble, just the emote.'],
    ];

    private const SHAPE_LOOK = ['value' => 'shape', 'label' => 'Choose another shape', 'hint' => 'One of twenty shapes, picked under Shape.'];

    /** key => [the description the recipe shipped, the one it has now]. */
    private const DESCRIPTIONS = [
        'look' => [
            'What each emote floats in. One of `bubble` (a soap bubble), `snow` (a snowflake behind the emote), `heart` (a heart-shaped bubble) or `plain` (no bubble, just the emote).',
            'What each emote floats in. One of `bubble` (a soap bubble), `snow` (a snowflake behind the emote), `heart` (a heart-shaped bubble), `shape` (one of twenty shapes, picked under Shape) or `plain` (no bubble, just the emote).',
        ],
        'bubble_size' => [
            'Width and height of a bubble in pixels. The emote inside scales with it.',
            'Width and height of a bubble in pixels. The emote inside scales with it. On the Shape look it sets the emote\'s size, and the shape grows around it.',
        ],
        'bubble_color' => [
            'The color of the bubble\'s ring and glow, the snowflake, or the heart, where the gradient starts. White reads as soap; try a pale pink on the heart.',
            'The color of the bubble\'s ring and glow, the snowflake, the heart or the shape, where the gradient starts. White reads as soap; try a pale pink on the heart.',
        ],
    ];

    private const SHAPES = [
        ['value' => 'ghost', 'label' => 'Ghost', 'hint' => 'A little ghost with a wavy hem.'],
        ['value' => 'cat', 'label' => 'Cat head', 'hint' => 'A cat\'s head with two pointed ears.'],
        ['value' => 'bauble', 'label' => 'Christmas bauble', 'hint' => 'A round bauble with its cap on.'],
        ['value' => 'drop', 'label' => 'Water drop', 'hint' => 'A drop, point up.'],
        ['value' => 'starburst', 'label' => 'Starburst badge', 'hint' => 'A badge with twelve short points.'],
        ['value' => 'octagram', 'label' => 'Octagram', 'hint' => 'An eight-pointed star.'],
        ['value' => 'hexagon', 'label' => 'Hexagon', 'hint' => 'Six sides, point up.'],
        ['value' => 'octagon', 'label' => 'Octagon', 'hint' => 'Eight sides, like a stop sign.'],
        ['value' => 'squircle', 'label' => 'Squircle', 'hint' => 'A square with soft round corners.'],
        ['value' => 'paw', 'label' => 'Paw print', 'hint' => 'A paw pad with four toes.'],
        ['value' => 'flame', 'label' => 'Flame', 'hint' => 'A flickering flame.'],
        ['value' => 'decagram', 'label' => 'Decagram', 'hint' => 'A ten-pointed star.'],
        ['value' => 'heptagon', 'label' => 'Heptagon', 'hint' => 'Seven sides.'],
        ['value' => 'pentagon', 'label' => 'Pentagon', 'hint' => 'Five sides, point up.'],
        ['value' => 'hexagram', 'label' => 'Hexagram', 'hint' => 'A six-pointed star.'],
        ['value' => 'decagon', 'label' => 'Decagon', 'hint' => 'Ten sides, almost round.'],
        ['value' => 'diamond', 'label' => 'Diamond', 'hint' => 'A tall diamond.'],
        ['value' => 'star', 'label' => 'Star', 'hint' => 'A five-pointed star.'],
        ['value' => 'triangle', 'label' => 'Triangle', 'hint' => 'Three sides, point up.'],
        ['value' => 'dodecagon', 'label' => 'Dodecagon', 'hint' => 'Twelve sides, nearly a circle.'],
    ];

    private const ONLY_ON_SHAPE = ['c:look = shape'];

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

        Log::info('Chat Emote Bubbles shapes backfill', ['patched' => $patched, 'skipped' => count($skipped)]);

        foreach ($skipped as $templateId => $reason) {
            Log::warning('Chat Emote Bubbles shapes backfill skipped an install', [
                'overlay_template_id' => $templateId,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * One install. Returns 'patched', 'done' (already has the shapes), or the
     * reason it was left alone.
     */
    private function upgrade(int $templateId): string
    {
        $template = DB::table('overlay_templates')->where('id', $templateId)->first(['id', 'owner_id', 'html', 'css', 'template_tags']);

        if (! $template) {
            return 'overlay is gone';
        }

        $controls = DB::table('overlay_controls')->where('overlay_template_id', $templateId)->get(['id', 'key', 'type', 'description', 'config', 'sort_order']);

        foreach (self::TAGS as $tag) {
            if ($controls->contains('key', substr($tag, 2))) {
                return 'done';
            }
        }

        $look = $controls->firstWhere('key', 'look');
        $angle = $controls->firstWhere('key', 'gradient_angle');

        if (! $look) {
            return 'no look control';
        }
        if (! $angle) {
            return 'no gradient_angle control';
        }

        $html = $this->patched((string) $template->html, self::HTML_PATCHES);
        if (! is_string($html['result'])) {
            return "html edited: snippet {$html['failed']} not found exactly once";
        }

        $css = $this->patched((string) $template->css, self::CSS_PATCHES);
        if (! is_string($css['result'])) {
            return "css edited: snippet {$css['failed']} not found exactly once";
        }

        $tags = json_decode((string) $template->template_tags, true);
        $tags = is_array($tags) ? $tags : [];
        foreach (self::TAGS as $tag) {
            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        $now = now();

        DB::transaction(function () use ($templateId, $template, $controls, $look, $angle, $html, $css, $tags, $now) {
            // Make room the way a fresh install orders them. updated_at is left
            // alone on every existing row: it is that control's `_at`.
            $lookOrder = (int) $look->sort_order;
            $angleOrder = (int) $angle->sort_order + ((int) $angle->sort_order > $lookOrder ? 1 : 0);

            DB::table('overlay_controls')->where('overlay_template_id', $templateId)->where('sort_order', '>', $lookOrder)->increment('sort_order');
            DB::table('overlay_controls')->where('overlay_template_id', $templateId)->where('sort_order', '>', $angleOrder)->increment('sort_order', 3);

            $row = fn (string $key, string $label, string $type, string $value, string $description, array $config, int $order) => [
                'overlay_template_id' => $templateId,
                'user_id' => $template->owner_id,
                'key' => $key,
                'label' => $label,
                'description' => $description,
                'type' => $type,
                'value' => $value,
                'config' => json_encode($config),
                'sort_order' => $order,
                'source' => null,
                'source_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            DB::table('overlay_controls')->insert([
                $row('shape', 'Shape', 'text', 'ghost',
                    'For the Shape look: which shape each emote floats in. The emote is the same size in every shape; the shape grows around it.',
                    ['choices' => self::SHAPES, 'enabled_if' => self::ONLY_ON_SHAPE], $lookOrder + 1),
                $row('border_width', 'Border width', 'number', '3',
                    'For the Shape look: how thick the shape\'s outline is, in pixels. The shape grows with it, so the emote keeps its breathing room. 0 draws no outline.',
                    ['min' => 0, 'max' => 8, 'step' => 0.5, 'reset_value' => 3, 'random' => false, 'random_interval' => null, 'enabled_if' => self::ONLY_ON_SHAPE], $angleOrder + 1),
                $row('fill_strength', 'Fill strength', 'number', '30',
                    'For the Shape look: how strongly the gradient fills the inside of the shape, from 0 (see-through, just the outline) to 100 (solid). The outline is always at full strength.',
                    ['min' => 0, 'max' => 100, 'step' => 1, 'reset_value' => 30, 'random' => false, 'random_interval' => null, 'enabled_if' => self::ONLY_ON_SHAPE], $angleOrder + 2),
                $row('breathing_room', 'Breathing room', 'number', '5',
                    'For the Shape look: the space in pixels between the emote and the inside of the outline.',
                    ['min' => 0, 'max' => 12, 'step' => 1, 'reset_value' => 5, 'random' => false, 'random_interval' => null, 'enabled_if' => self::ONLY_ON_SHAPE], $angleOrder + 3),
            ]);

            // The Shape choice goes in before Plain, only on a select that is
            // still exactly the recipe's.
            $config = json_decode((string) $look->config, true);
            if (is_array($config) && ($config['choices'] ?? null) === self::SHIPPED_LOOKS) {
                $config['choices'] = [...array_slice(self::SHIPPED_LOOKS, 0, 3), self::SHAPE_LOOK, self::SHIPPED_LOOKS[3]];
                DB::table('overlay_controls')->where('id', $look->id)->update(['config' => json_encode($config)]);
            }

            foreach (self::DESCRIPTIONS as $key => [$shipped, $current]) {
                $control = $controls->firstWhere('key', $key);
                if ($control && $control->description === $shipped) {
                    DB::table('overlay_controls')->where('id', $control->id)->update(['description' => $current]);
                }
            }

            DB::table('overlay_templates')->where('id', $templateId)->update([
                'html' => $html['result'],
                'css' => $css['result'],
                'template_tags' => json_encode(array_values($tags)),
                'updated_at' => $now,
            ]);
        });

        return 'patched';
    }

    /**
     * Every patch applied, or `result` null and the index of the first one
     * whose `from` was not there exactly once.
     *
     * @return array{result: ?string, failed: ?int}
     */
    private function patched(string $source, array $patches): array
    {
        foreach ($patches as $i => $patch) {
            if (substr_count($source, $patch['from']) !== 1) {
                return ['result' => null, 'failed' => $i];
            }
            $source = str_replace($patch['from'], $patch['to'], $source);
        }

        return ['result' => $source, 'failed' => null];
    }

    /**
     * The overlay is the streamer's own copy, and by the time this could run
     * backwards they may have picked a shape they like. Nothing to undo.
     */
    public function down(): void {}
};
