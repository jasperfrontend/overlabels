<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Give every UNEDITED install of Twitch Chat Plinko, Chat Tower and Follower
 * Bowling the color controls its designer edits.
 *
 * On 2026-11-20 the three full-screen products got a designer: their :root
 * palette now reads `[[[c:*_color]]]` controls, and each manifest declares
 * the groups and the Twilight / Nordic / Moss themes. A fresh install gets
 * the controls from the overlay document. An existing install does not, so
 * its designer page would edit nothing.
 *
 * An install is upgraded only when its overlay is still exactly what we
 * shipped (Jasper, 2026-11-20): HTML equal to the current version, CSS equal
 * to one of the versions released with that HTML. Anything else is somebody's
 * own work and is left alone, logged by install id; its owner reinstalls to
 * get the designer. Each upgraded install, all or nothing:
 *  - css replaced with the palette-bound stylesheet, exactly as the installer
 *    stores it, and the color tags added to the template_tags allowlist;
 *  - one color control per palette entry, with the default the document
 *    declares, skipping a key the overlay already has.
 * The colors render exactly as before: the defaults ARE the old palette.
 *
 * Every value is frozen here: reading the recipe on disk would install
 * whatever the document says on the day this runs.
 */
return new class extends Migration
{
    private const PRODUCTS = [
        'twitch-chat-plinko' => [
            'slugs' => [
                'twitch-chat-plinko',
            ],
            'overlay_ref' => 'plinko',
            'html_sha256' => '217d9a3ceeb436acb8790e344ff1496b70caab9541e4f29d299febd9c0a35a50',
            'old_css_sha256' => [
                '94a619968493c1c3fa4e891628deda915f1991c67bead04378a2e29a003c51f7',
                '4469217560052eebf7e4cb2077f6b975853cf790191b6316b36913be01dd8567',
                'bb3d1c5b911b12cc56328588a90f4a238363bc91c98e6e13a36db282436bd68c',
            ],
            'css' => self::PLINKO_CSS,
            'tags' => [
                'c:text_color',
                'c:soft_text_color',
                'c:muted_color',
                'c:highlight_color',
                'c:background_color',
            ],
            'controls' => [
                [
                    'key' => 'background_color',
                    'label' => 'Background',
                    'description' => 'Behind everything, and the tag that carries a chatter\'s name on their ball.',
                    'value' => '#120720',
                    'sort_order' => 0,
                ],
                [
                    'key' => 'text_color',
                    'label' => 'Text',
                    'description' => 'The heading, the names in the lists, and the name on each ball.',
                    'value' => '#fafafa',
                    'sort_order' => 1,
                ],
                [
                    'key' => 'soft_text_color',
                    'label' => 'Soft text',
                    'description' => 'The how-to-play sentence under the heading.',
                    'value' => '#e9d5ff',
                    'sort_order' => 2,
                ],
                [
                    'key' => 'muted_color',
                    'label' => 'Pegs and labels',
                    'description' => 'The pegs, the slot numbers and dividers, the list titles and the lines between rows.',
                    'value' => '#a78bfa',
                    'sort_order' => 3,
                ],
                [
                    'key' => 'highlight_color',
                    'label' => 'Highlight',
                    'description' => 'The command, the points, a peg lighting up when a ball hits it, the landing flash, and the ball of a chatter who never picked a name color.',
                    'value' => '#f472b6',
                    'sort_order' => 4,
                ],
            ],
        ],
        'chat-tower' => [
            'slugs' => [
                'chat-tower',
                'chat_tower',
            ],
            'overlay_ref' => 'tower',
            'html_sha256' => 'fcd528c0b8b4c17a9b5f563253495747be4706061141296b7596284008c35a70',
            'old_css_sha256' => [
                'b3238a879ceba47889bc6da186f4576d86a0ba196be7ec6f688f829735d50d31',
                'f09752426e4041b9f2427b105ac5867b6a6ba2ec09415f1955649c9dfaf1fbd9',
            ],
            'css' => self::TOWER_CSS,
            'tags' => [
                'c:text_color',
                'c:soft_text_color',
                'c:muted_color',
                'c:highlight_color',
                'c:warning_color',
                'c:danger_color',
                'c:flash_color',
                'c:record_color',
                'c:panel_color',
                'c:background_color',
            ],
            'controls' => [
                [
                    'key' => 'background_color',
                    'label' => 'Background',
                    'description' => 'Behind everything and behind the topple and record banners, plus the light shade on every block that keeps the names readable.',
                    'value' => '#120720',
                    'sort_order' => 10,
                ],
                [
                    'key' => 'panel_color',
                    'label' => 'Gauge track',
                    'description' => 'The lean gauge under the tower, behind the pin.',
                    'value' => '#1d0b30',
                    'sort_order' => 11,
                ],
                [
                    'key' => 'text_color',
                    'label' => 'Text',
                    'description' => 'The heading, the stats, the gauge pin while the tower stands safe, and the line under the toppler\'s name.',
                    'value' => '#fafafa',
                    'sort_order' => 12,
                ],
                [
                    'key' => 'soft_text_color',
                    'label' => 'Soft text',
                    'description' => 'The how-to-play sentence, and the status line while the tower stands safe.',
                    'value' => '#e9d5ff',
                    'sort_order' => 13,
                ],
                [
                    'key' => 'muted_color',
                    'label' => 'Labels and lines',
                    'description' => 'Labels, the fall lines, the ground, the dashed block waiting for the first one, and the lines between rows.',
                    'value' => '#a78bfa',
                    'sort_order' => 14,
                ],
                [
                    'key' => 'highlight_color',
                    'label' => 'Highlight',
                    'description' => 'The command, the record height, the band on the gauge, and the topple banner with the toppler\'s name.',
                    'value' => '#f472b6',
                    'sort_order' => 15,
                ],
                [
                    'key' => 'warning_color',
                    'label' => 'Close to falling',
                    'description' => 'The status, fall lines and gauge pin when the tower gets close to falling.',
                    'value' => '#f97316',
                    'sort_order' => 16,
                ],
                [
                    'key' => 'danger_color',
                    'label' => 'About to fall',
                    'description' => 'The status, fall lines and gauge pin when the next block could topple it.',
                    'value' => '#ec4899',
                    'sort_order' => 17,
                ],
                [
                    'key' => 'flash_color',
                    'label' => 'Topple flash',
                    'description' => 'The block that toppled the tower, flashing before it falls.',
                    'value' => '#ff2d55',
                    'sort_order' => 18,
                ],
                [
                    'key' => 'record_color',
                    'label' => 'New record',
                    'description' => 'The flash when a standing tower beats the record.',
                    'value' => '#4ade80',
                    'sort_order' => 19,
                ],
            ],
        ],
        'follower-bowling' => [
            'slugs' => [
                'follower-bowling',
                'follower_bowling',
            ],
            'overlay_ref' => 'lane',
            'html_sha256' => 'cb1d4cd93f2dd4d2942ffad6d367313d774cbf0313dfe1acebfbb06eb6dfe94c',
            'old_css_sha256' => [
                'c9bad6563ace791fa5f7dd58dfe5568b5616e5995cc30b2caee91f4cd344ae6a',
                '00b564b2d52726d6cd7c6df30d06688868a44512a2dcc43f55b98bbc225de98a',
            ],
            'css' => self::BOWLING_CSS,
            'tags' => [
                'c:text_color',
                'c:soft_text_color',
                'c:muted_color',
                'c:highlight_color',
                'c:panel_color',
                'c:background_color',
            ],
            'controls' => [
                [
                    'key' => 'background_color',
                    'label' => 'Background',
                    'description' => 'Behind everything: the gutters, the holes the pins leave, and the edge around the ball and the pins.',
                    'value' => '#120720',
                    'sort_order' => 20,
                ],
                [
                    'key' => 'panel_color',
                    'label' => 'Lane',
                    'description' => 'The lane the ball rolls down.',
                    'value' => '#1d0b30',
                    'sort_order' => 21,
                ],
                [
                    'key' => 'text_color',
                    'label' => 'Text',
                    'description' => 'The heading, how many are in line, the follower names on the pins, and the name under a strike.',
                    'value' => '#fafafa',
                    'sort_order' => 22,
                ],
                [
                    'key' => 'soft_text_color',
                    'label' => 'Soft text',
                    'description' => 'The how-to-play sentence, the score line, and "Waiting for a bowler".',
                    'value' => '#e9d5ff',
                    'sort_order' => 23,
                ],
                [
                    'key' => 'muted_color',
                    'label' => 'Labels and lines',
                    'description' => 'Labels, the place numbers in line, the foul line, the ring around each follower\'s picture, and the lines between rows.',
                    'value' => '#a78bfa',
                    'sort_order' => 24,
                ],
                [
                    'key' => 'highlight_color',
                    'label' => 'Highlight',
                    'description' => 'The command, first place in line, the bowler\'s name, the ball, the head pin\'s ring and the strike banner.',
                    'value' => '#f472b6',
                    'sort_order' => 25,
                ],
            ],
        ],
    ];

    private const PLINKO_CSS = <<<'PLINKO_CSS'
:root {
  /* the palette, set from the designer */
  --ink: [[[c:text_color]]];
  --ink-soft: [[[c:soft_text_color]]];
  --ink-muted: [[[c:muted_color]]];
  --highlight: [[[c:highlight_color]]];
  --field-deep: [[[c:background_color]]];
  --line: color-mix(in srgb, var(--ink-muted) 35%, transparent);
  --mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 1920px; height: 1080px; overflow: hidden; }
body {
  background: var(--field-deep);
  color: var(--ink);
  font-family: "Albert Sans", system-ui, sans-serif;
}

.stage { position: relative; width: 1920px; height: 1080px; }

/* The board fills the stage; the module centres the pyramid in it. */
.ol-plinko {
  position: absolute;
  inset: 0;
  --plinko-rows: 14;
  --plinko-max-balls: 150;
  --plinko-rate: 10;
  --plinko-peg-color: var(--ink-muted);
  --plinko-accent: var(--highlight);
  --plinko-ball-color: var(--highlight);
  --plinko-label-bg: var(--field-deep);
  --plinko-label-color: var(--ink);
  --plinko-slot-color: var(--ink-muted);
  --plinko-font: "Albert Sans", system-ui, sans-serif;
}

/* ---------- left: how to play, then who is next ---------- */
.intro {
  position: absolute;
  left: 64px;
  top: 64px;
  width: 380px;
}
.eyebrow {
  font-family: var(--mono);
  font-size: 16px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-muted);
}
h1 { margin-top: 12px; font-size: 56px; line-height: 1.05; font-weight: 800; }
.cmd { font-family: var(--mono); color: var(--highlight); white-space: nowrap; }
.sub { margin-top: 20px; font-size: 21px; line-height: 1.5; color: var(--ink-soft); }

/* ---------- the module's scoreboard ---------- */
.ol-plinko-hud { position: absolute; inset: 0; pointer-events: none; }

.ol-plinko-count {
  position: absolute;
  left: 64px;
  bottom: 64px;
  width: 360px;
  font-family: var(--mono);
  font-size: 18px;
  color: var(--ink-muted);
}
.ol-plinko-count b { color: var(--ink); font-weight: 600; }

/* Names are never cut or wrapped: a column grows to fit the longest one. */
.ol-plinko-hud ol { position: absolute; min-width: 360px; width: max-content; list-style: none; }
.ol-plinko-hud ol::before {
  display: block;
  margin-bottom: 10px;
  font-family: var(--mono);
  font-size: 15px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-muted);
}
.ol-plinko-hud li {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 8px 0;
  border-top: 1px solid var(--line);
  font-size: 19px;
  font-weight: 600;
  white-space: nowrap;
}
.ol-plinko-hud li:last-child { border-bottom: 1px solid var(--line); }
.ol-plinko-points { font-family: var(--mono); color: var(--highlight); }

.ol-plinko-next { left: 64px; top: 420px; }
.ol-plinko-next::before { content: "Next up"; }
.ol-plinko-next:empty { display: none; }

.ol-plinko-recent { right: 64px; top: 64px; }
.ol-plinko-recent::before { content: "Just landed"; }

.ol-plinko-top { right: 64px; top: 520px; }
.ol-plinko-top::before { content: "Top scores"; }
PLINKO_CSS;

    private const TOWER_CSS = <<<'TOWER_CSS'
:root {
  --wave: [[[c:sway|round:3]]];
  --pin: [[[c:lean_pct|round:1]]]%;
  --band: [[[c:band_pct|round:1]]]%;

  /* the palette, set from the designer */
  --ink: [[[c:text_color]]];
  --ink-soft: [[[c:soft_text_color]]];
  --ink-muted: [[[c:muted_color]]];
  --highlight: [[[c:highlight_color]]];
  --warning: [[[c:warning_color]]];
  --danger: [[[c:danger_color]]];
  --flash: [[[c:flash_color]]];
  --record: [[[c:record_color]]];
  --field: [[[c:panel_color]]];
  --field-deep: [[[c:background_color]]];
  --line: color-mix(in srgb, var(--ink-muted) 35%, transparent);
  --mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace;

  /* one block is 4 units wide and 1 unit tall */
  --unit: 60px;
  /* rows that fit above the ground before the camera starts following the top */
  --rows: 13;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 1920px; height: 1080px; overflow: hidden; }
body {
  background: var(--field-deep);
  color: var(--ink);
  font-family: "Albert Sans", system-ui, sans-serif;
  -webkit-font-smoothing: antialiased;
}

.stage { position: relative; width: 1920px; height: 1080px; background: var(--field-deep); }
.num { font-variant-numeric: tabular-nums; }
.cmd { font-family: var(--mono); color: var(--highlight); white-space: nowrap; }

/* ---------- left: how to play, then the running facts ---------- */
.intro { position: absolute; left: 64px; top: 64px; width: 380px; }
.eyebrow,
.label,
.facts dt {
  font-family: var(--mono);
  font-size: 16px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-muted);
}
h1 { margin-top: 12px; font-size: 56px; line-height: 1.05; font-weight: 800; }
.sub { margin-top: 20px; font-size: 21px; line-height: 1.5; color: var(--ink-soft); }

.facts { position: absolute; left: 64px; bottom: 64px; width: 380px; }
.fact {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 0;
  border-top: 1px solid var(--line);
}
.fact:last-child { border-bottom: 1px solid var(--line); }
.facts dd {
  font-size: 28px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ---------- right: height and record ---------- */
.score { position: absolute; right: 64px; top: 64px; width: 380px; text-align: center; }
.record { margin-top: 56px; }
.big { margin-top: 4px; font-size: 144px; font-weight: 800; line-height: 0.95; letter-spacing: -0.03em; }
.record .big { font-size: 96px; color: var(--highlight); }
.status { margin-top: 8px; font-size: 24px; font-weight: 600; color: var(--ink-soft); }
.stage[data-danger="1"] .status { color: var(--warning); }
.stage[data-danger="2"] .status { color: var(--danger); animation: pulse 0.8s ease-in-out infinite alternate; }
/* for as long as the topple banner is up (the fall included) */
.stage[data-banner="1"] .status { color: var(--highlight); animation: none; }

.roster { margin-top: 20px; list-style: none; }
.roster li {
  padding: 10px 0;
  border-top: 1px solid var(--line);
  font-size: 21px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.roster li:last-child { border-bottom: 1px solid var(--line); }
.roster:empty { display: none; }
.more { margin-top: 12px; font-family: var(--mono); font-size: 16px; color: var(--ink-muted); }

/* ---------- centre: the field ---------- */
.field {
  position: absolute;
  left: 480px;
  top: 0;
  width: 960px;
  height: 1080px;
  overflow: hidden;
}

/* both lines sit 4 units either side of the base centre: the top block
   falls when its sway carries it past one */
.fallline {
  position: absolute;
  top: 64px;
  bottom: 160px;
  width: 0;
  border-left: 2px dashed var(--line);
  transition: border-color 0.3s ease;
}
.fallline.left { left: calc(50% - var(--unit) * 4 - 1px); }
.fallline.right { left: calc(50% + var(--unit) * 4 - 1px); }
.fallline span {
  position: absolute;
  top: -4px;
  left: 14px;
  font-family: var(--mono);
  font-size: 14px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--ink-muted);
  white-space: nowrap;
}
.fallline.left span { left: auto; right: 14px; }
.stage[data-danger="1"] .fallline { border-color: var(--warning); }
.stage[data-danger="1"] .fallline span { color: var(--warning); }
.stage[data-danger="2"] .fallline { border-color: var(--danger); }
.stage[data-danger="2"] .fallline span { color: var(--danger); }

.ground {
  position: absolute;
  left: 48px;
  right: 48px;
  bottom: 156px;
  height: 4px;
  border-radius: 2px;
  background: var(--ink-muted);
}

/* the lean gauge spans exactly fall line to fall line: the pin is the top
   block, the band either side of it is how far the sway carries it */
.gauge {
  position: absolute;
  left: calc(50% - var(--unit) * 4);
  width: calc(var(--unit) * 8);
  bottom: 104px;
  height: 14px;
  border: 1px solid var(--line);
  border-radius: 7px;
  background: var(--field);
}
.gauge::before {
  content: "";
  position: absolute;
  left: 50%;
  top: -6px;
  bottom: -6px;
  width: 2px;
  margin-left: -1px;
  background: var(--line);
}
.gauge-band {
  position: absolute;
  top: 0;
  bottom: 0;
  left: calc(var(--pin) - var(--band));
  width: calc(var(--band) * 2);
  border-radius: 7px;
  background: color-mix(in srgb, var(--highlight) 30%, transparent);
}
.gauge-pin {
  position: absolute;
  top: -8px;
  left: var(--pin);
  width: 8px;
  height: 28px;
  margin-left: -4px;
  border-radius: 4px;
  background: var(--ink);
  transition: left 0.35s ease;
}
.stage[data-danger="1"] .gauge-pin { background: var(--warning); box-shadow: 0 0 16px color-mix(in srgb, var(--warning) 80%, transparent); }
.stage[data-danger="2"] .gauge-pin { background: var(--danger); box-shadow: 0 0 20px color-mix(in srgb, var(--danger) 90%, transparent); }

/* the camera follows the top: once the tower is taller than the field the
   whole stack slides down, block by block. It reads the number of blocks on
   screen, not the height control: during a topple the fallen blocks stay in
   the data for three seconds while the height is already 0, so the camera
   holds still and the tower falls from where it stood */
.cam {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 160px;
  height: 0;
  transform: translateY(calc(max(0, var(--count) - var(--rows)) * var(--unit)));
  transition: transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}

/* a block sits at its own x (units from the base centre) and position (1 =
   the base); the sway is a lean about the base, so each block moves in
   proportion to how high up it is */
.block {
  position: absolute;
  width: calc(var(--unit) * 4);
  height: calc(var(--unit) - 4px);
  left: calc(50% - var(--unit) * 2 + var(--x) * var(--unit));
  bottom: calc((var(--p) - 1) * var(--unit));
  /* each block swings 0.055 block widths per row it sits above the ground,
     so the top swings 0.055 x height, the physics' sway. Built from the
     block's own position and not the height control, which drops to 0 the
     instant the tower topples and would snap the tower straight */
  transform: translateX(calc(0.055 * var(--p) * var(--wave) * var(--unit)));
  /* a block the camera has slid below the ground is hidden: 1 above, 0 below */
  opacity: clamp(0, var(--p) - max(0, var(--count) - var(--rows)), 1);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 14px;
  border-radius: 6px;
  background: var(--c);
  /* the first shadow darkens any chat colour enough for white text to read */
  box-shadow:
    inset 0 0 0 100px color-mix(in srgb, var(--field-deep) 28%, transparent),
    inset 0 -5px 0 rgb(0 0 0 / 0.3),
    inset 0 2px 0 rgb(255 255 255 / 0.3),
    0 4px 0 rgb(0 0 0 / 0.45);
  font-size: 23px;
  font-weight: 800;
  color: #fff;
  text-shadow: 0 0 2px rgb(0 0 0 / 0.9), 0 1px 2px rgb(0 0 0 / 0.9), 0 0 8px rgb(0 0 0 / 0.45);
  animation: land 0.32s cubic-bezier(0.2, 0.9, 0.3, 1.3);
}
.block span { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

@keyframes land {
  from { margin-bottom: 28px; opacity: 0.3; }
  to { margin-bottom: 0; opacity: 1; }
}

/* the topple, Coyote Time. tower.falling is set for three seconds (the
   renderer's hold). The block that did it lands on top and stays put while
   the tower drops out from under it, top first, each block toward the side
   it leaned. Left hanging in mid-air, the culprit flashes red twice, then
   drops straight down on its own, shrinking into the distance. The banner
   naming them lands after that.

   Keyframes, not transitions: the culprit arrives in the same render as the
   fall, and a transition on a brand new element jumps straight to its end
   state. The tower's stagger is capped so a 50-block window still clears
   before the culprit goes.

   0.00s  culprit lands                  0.25s  tower starts dropping
   0.90s  first red flash                1.25s  second red flash
   ~1.85s tower gone (tallest case)      1.90s  culprit drops
   2.45s  culprit gone                   2.40s  banner lands */
.stage[data-falling="1"] .block {
  --wait: calc(250ms + min((var(--count) - 1 - var(--p)) * 25ms, 600ms));
  animation:
    fall 1s cubic-bezier(0.45, 0, 0.9, 0.45) var(--wait) both,
    fade 0.3s ease-in calc(var(--wait) + 0.75s) both;
}

@keyframes fall {
  to { transform: translate(calc(var(--x) * 220px), 1100px) rotate(calc(var(--x) * 30deg)); }
}

@keyframes fade {
  to { opacity: 0; }
}

/* the culprit is always the last block: it sits on top of the held tower */
.stage[data-falling="1"] .block:last-child {
  transform: none;
  animation:
    land 0.32s cubic-bezier(0.2, 0.9, 0.3, 1.3),
    coyote-flash 0.7s linear 0.9s forwards,
    coyote-drop 0.55s cubic-bezier(0.55, 0, 1, 0.45) 1.9s both,
    fade 0.15s ease-in 2.3s both;
}

/* two hard flashes: off, ON, off, ON, off. "forwards", never "both": a
   backwards fill would paint the first keyframe during the 0.9 s wait */
@keyframes coyote-flash {
  0%, 38%, 58%, 96%, 100% { background: var(--c); }
  8%, 30%, 66%, 88% { background: var(--flash); box-shadow: inset 0 -5px 0 rgb(0 0 0 / 0.3), 0 0 32px color-mix(in srgb, var(--flash) 95%, transparent), 0 4px 0 rgb(0 0 0 / 0.45); }
}

@keyframes coyote-drop {
  to { transform: translateY(700px) scale(0.12); }
}

/* ---------- empty ground ---------- */
.empty {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 160px;
  display: flex;
  flex-direction: column-reverse;
  align-items: center;
  gap: 20px;
  animation: empty-in 0.5s ease-out both;
}
/* the empty ground waits until the topple banner has gone, so the banner
   keeps the screen to itself; it fades in fresh when it comes back */
.stage[data-banner="1"] .empty { display: none; }
@keyframes empty-in {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: none; }
}
.ghost {
  width: calc(var(--unit) * 4);
  height: calc(var(--unit) - 4px);
  border: 2px dashed var(--ink-muted);
  border-radius: 6px;
  animation: pulse 1.6s ease-in-out infinite alternate;
}
.empty p { font-family: var(--mono); font-size: 18px; color: var(--ink-muted); }

/* ---------- the moments ---------- */
.banner {
  position: absolute;
  left: 0;
  right: 0;
  top: 40%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 36px 24px;
  background: color-mix(in srgb, var(--field-deep) 92%, transparent);
  border-top: 2px solid var(--highlight);
  border-bottom: 2px solid var(--highlight);
  text-align: center;
  /* held back until the culprit has dropped out of sight */
  animation: banner-in 0.45s cubic-bezier(0.2, 0.9, 0.3, 1.4) 2.4s both;
}
@keyframes banner-in {
  from { opacity: 0; transform: translateY(-50%) scale(0.6); }
  to { opacity: 1; transform: translateY(-50%) scale(1); }
}
.banner-name {
  max-width: 100%;
  font-size: 64px;
  font-weight: 800;
  line-height: 1.1;
  color: var(--highlight);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.banner-mid { font-size: 28px; color: var(--ink); }
.banner-mid b { font-weight: 800; }
.banner-small { font-family: var(--mono); font-size: 18px; color: var(--ink-muted); }

.record-flash {
  position: absolute;
  left: 0;
  right: 0;
  top: 120px;
  padding: 18px 24px;
  background: color-mix(in srgb, var(--field-deep) 92%, transparent);
  border-top: 2px solid var(--record);
  border-bottom: 2px solid var(--record);
  text-align: center;
  font-family: var(--mono);
  font-size: 22px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--record);
  animation: banner-in-top 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.2);
}
.record-flash b { font-size: 32px; margin-left: 8px; }
@keyframes banner-in-top {
  from { opacity: 0; transform: scale(0.92); }
  to { opacity: 1; transform: scale(1); }
}

@keyframes pulse {
  from { opacity: 1; }
  to { opacity: 0.45; }
}
TOWER_CSS;

    private const BOWLING_CSS = <<<'BOWLING_CSS'
:root {
  --bx: [[[c:ball_x|round:3]]];
  --by: [[[c:ball_y|round:3]]];
  --pulse: [[[c:ball_pulse|round:3]]];
  --on: [[[c:ball_on]]];
  --show: [[[c:bowl_show]]];
  --bowler: [[[c:bowler_show]]];
  --pin0: [[[c:pin_0]]];
  --pin1: [[[c:pin_1]]];
  --pin2: [[[c:pin_2]]];
  --pin3: [[[c:pin_3]]];
  --pin4: [[[c:pin_4]]];
  --pin5: [[[c:pin_5]]];
  --pin6: [[[c:pin_6]]];
  --pin7: [[[c:pin_7]]];
  --pin8: [[[c:pin_8]]];
  --pin9: [[[c:pin_9]]];

  /* the palette, set from the designer */
  --ink: [[[c:text_color]]];
  --ink-soft: [[[c:soft_text_color]]];
  --ink-muted: [[[c:muted_color]]];
  --highlight: [[[c:highlight_color]]];
  --field: [[[c:panel_color]]];
  --field-deep: [[[c:background_color]]];
  --line: color-mix(in srgb, var(--ink-muted) 35%, transparent);
  --mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 1920px; height: 1080px; overflow: hidden; }
body {
  background: var(--field-deep);
  color: var(--ink);
  font-family: "Albert Sans", system-ui, sans-serif;
  -webkit-font-smoothing: antialiased;
}

.stage { position: relative; width: 1920px; height: 1080px; background: var(--field-deep); }
.num { font-variant-numeric: tabular-nums; }
.cmd { font-family: var(--mono); color: var(--highlight); white-space: nowrap; }

.eyebrow,
.label {
  font-family: var(--mono);
  font-size: 16px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-muted);
}

/* ---------- left: how to play, then the line ---------- */
.intro { position: absolute; left: 64px; top: 64px; width: 380px; }
h1 { margin-top: 12px; font-size: 56px; line-height: 1.05; font-weight: 800; }
.sub { margin-top: 20px; font-size: 21px; line-height: 1.5; color: var(--ink-soft); }

.line { position: absolute; left: 64px; top: 440px; width: 380px; }
.queue { margin-top: 10px; list-style: none; counter-reset: place; }
.queue li {
  display: flex;
  gap: 14px;
  padding: 10px 0;
  border-top: 1px solid var(--line);
  font-size: 21px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  counter-increment: place;
}
.queue li::before {
  content: counter(place);
  flex: none;
  width: 22px;
  font-family: var(--mono);
  color: var(--ink-muted);
}
.queue li:first-child::before { color: var(--highlight); }
.queue li:last-child { border-bottom: 1px solid var(--line); }

.count {
  position: absolute;
  left: 64px;
  bottom: 64px;
  width: 380px;
  font-family: var(--mono);
  font-size: 18px;
  color: var(--ink-muted);
}
.count b { color: var(--ink); font-weight: 700; }

/* ---------- centre: the lane, seen from the bowler ---------- */
.field {
  position: absolute;
  left: 480px;
  top: 0;
  width: 960px;
  height: 1080px;
  overflow: hidden;
}

.lane {
  position: absolute;
  left: 140px;
  width: 680px;
  top: 40px;
  bottom: 40px;
  border-left: 2px solid var(--line);
  border-right: 2px solid var(--line);
  background: var(--field);
}

.gutter {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 56px;
  background: var(--field-deep);
}
.gutter.left { left: 0; border-right: 1px solid var(--line); }
.gutter.right { right: 0; border-left: 1px solid var(--line); }

/* the foul line the ball waits behind */
.foul {
  position: absolute;
  left: 56px;
  right: 56px;
  top: 760px;
  height: 3px;
  background: var(--ink-muted);
}

/* aiming arrows on the boards, pointing at the rack */
.arrows {
  position: absolute;
  left: 56px;
  right: 56px;
  top: 600px;
  display: flex;
  justify-content: space-evenly;
}
.arrows i {
  width: 0;
  height: 0;
  border-left: 12px solid transparent;
  border-right: 12px solid transparent;
  border-bottom: 26px solid var(--line);
}
.arrows i:nth-child(2),
.arrows i:nth-child(4) { margin-top: 36px; }
.arrows i:nth-child(3) { margin-top: 72px; }

/* ---------- pins: the head pin nearest the ball, the back row of four on top ---------- */
.pins { position: absolute; inset: 0; }

.pin {
  position: absolute;
  width: 128px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  transform-origin: 50% 100%;
  transform: translateY(calc(var(--fall) * -36px)) rotate(calc(var(--fall) * var(--dir, 1) * 80deg));
  opacity: calc(1 - var(--fall) * 0.75);
  transition:
    transform 0.35s cubic-bezier(0.3, 1.4, 0.6, 1),
    opacity 0.35s;
}

.pin img {
  width: 80px;
  height: 80px;
  border-radius: 9999px;
  border: 3px solid var(--ink-muted);
  background: var(--field-deep);
  object-fit: cover;
}

.pin span {
  max-width: 128px;
  padding: 2px 8px;
  border-radius: 6px;
  background: var(--field-deep);
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* the head pin carries the pink ring */
.pin:nth-child(1) img { border-color: var(--highlight); }

/* a stand-in for a follower the channel does not have yet, so a new
   channel still bowls at ten: same physics, dimmer face; a real follower
   takes its slot the moment they arrive */
.pin-filler img {
  border-style: dashed;
  opacity: 0.55;
  filter: grayscale(1);
}
.pin-filler span { color: var(--ink-muted); }

/* rows from the ball: 1, 2, 3, 4. --dir is the side a pin falls to */
.pin:nth-child(1)  { left: 416px; top: 428px; --dir: 1; }
.pin:nth-child(2)  { left: 348px; top: 312px; --dir: -1; }
.pin:nth-child(3)  { left: 484px; top: 312px; --dir: 1; }
.pin:nth-child(4)  { left: 280px; top: 196px; --dir: -1; }
.pin:nth-child(5)  { left: 416px; top: 196px; --dir: 1; }
.pin:nth-child(6)  { left: 552px; top: 196px; --dir: 1; }
.pin:nth-child(7)  { left: 212px; top: 80px; --dir: -1; }
.pin:nth-child(8)  { left: 348px; top: 80px; --dir: -1; }
.pin:nth-child(9)  { left: 484px; top: 80px; --dir: 1; }
.pin:nth-child(10) { left: 620px; top: 80px; --dir: 1; }

/* the ball: waits behind the foul line pulsing, rolls up the lane into the
   rack and gets smaller as it goes; on a gutter ball it curves off to the
   right and drops into the gutter */
.ball {
  position: absolute;
  left: 430px;
  top: 840px;
  width: 100px;
  height: 100px;
  border-radius: 9999px;
  background: var(--highlight);
  box-shadow:
    inset -10px -12px 0 rgb(0 0 0 / 0.22),
    inset 6px 6px 0 rgb(255 255 255 / 0.25),
    0 0 0 3px var(--field-deep);
  transform:
    translate(calc(var(--on) * var(--by) * 312px), calc(var(--on) * var(--bx) * -560px))
    scale(calc(var(--pulse) * (1 - var(--on) * var(--bx) * 0.32)))
    rotate(calc(var(--on) * var(--bx) * 1080deg));
  /* between throws it rests on the approach, dimmed */
  opacity: calc(0.3 + var(--on) * 0.7);
  z-index: 2;
}

.hole {
  position: absolute;
  width: 14px;
  height: 14px;
  border-radius: 9999px;
  background: var(--field-deep);
}
.hole-1 { left: 42px; top: 26px; width: 15px; height: 15px; }
.hole-2 { left: 28px; top: 48px; }
.hole-3 { left: 54px; top: 52px; }

/* ---------- the strike moment ---------- */
.strike {
  position: absolute;
  left: 0;
  right: 0;
  top: 560px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 28px 24px;
  background: color-mix(in srgb, var(--field-deep) 92%, transparent);
  border-top: 2px solid var(--highlight);
  border-bottom: 2px solid var(--highlight);
  text-align: center;
  opacity: var(--show);
  transform: scale(calc(0.6 + var(--show) * 0.4));
  transition:
    opacity 0.3s ease,
    transform 0.45s cubic-bezier(0.2, 0.9, 0.3, 1.4);
  z-index: 3;
}
.strike-word { font-size: 96px; font-weight: 800; line-height: 1; letter-spacing: -0.02em; color: var(--highlight); }
.strike-name {
  max-width: 100%;
  font-size: 28px;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ---------- right: who bowls, and how it went ---------- */
.score { position: absolute; right: 64px; top: 64px; width: 380px; text-align: center; }
.result { margin-top: 56px; opacity: var(--bowler); transition: opacity 0.25s; }

/* two lines in one place, one fading in as the other fades out */
.swap { display: grid; }
.swap > * { grid-area: 1 / 1; transition: opacity 0.25s; }

.bowler {
  margin-top: 8px;
  font-size: 44px;
  font-weight: 800;
  line-height: 1.15;
  color: var(--highlight);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  opacity: var(--bowler);
}
.bowler-idle {
  margin-top: 8px;
  align-self: center;
  font-size: 24px;
  font-weight: 600;
  color: var(--ink-soft);
  opacity: calc(1 - var(--bowler));
}

.big { margin-top: 4px; font-size: 144px; font-weight: 800; line-height: 0.95; letter-spacing: -0.03em; }
#score { opacity: var(--show); }
.dash { color: var(--ink-muted); opacity: calc(1 - var(--show)); }

.status { margin-top: 8px; font-size: 24px; font-weight: 600; color: var(--ink-soft); }
.status-result { opacity: var(--show); }
.status-rolling { color: var(--ink-muted); opacity: calc(var(--bowler) - var(--show)); }
.stage[data-knocked="10"] .status-result { color: var(--highlight); font-weight: 800; }
BOWLING_CSS;

    public function up(): void
    {
        foreach (self::PRODUCTS as $product => $spec) {
            $recipeIds = DB::table('recipes')->whereIn('slug', $spec['slugs'])->pluck('id');

            if ($recipeIds->isEmpty()) {
                continue;
            }

            $upgraded = 0;
            $skipped = [];

            DB::table('recipe_instances')
                ->whereIn('recipe_id', $recipeIds)
                ->select('id', 'user_id', 'primitive_map')
                ->chunkById(100, function ($instances) use ($spec, &$upgraded, &$skipped) {
                    foreach ($instances as $instance) {
                        $outcome = $this->upgrade($instance, $spec);

                        if ($outcome === 'upgraded') {
                            $upgraded++;
                        } elseif ($outcome !== 'done') {
                            $skipped[$instance->id] = $outcome;
                        }
                    }
                });

            Log::info('Palette controls migration', ['product' => $product, 'upgraded' => $upgraded, 'skipped' => count($skipped)]);

            foreach ($skipped as $instanceId => $reason) {
                Log::warning('Palette controls migration skipped an install', [
                    'product' => $product,
                    'recipe_instance_id' => $instanceId,
                    'reason' => $reason,
                ]);
            }
        }
    }

    /**
     * One install. Returns 'upgraded', 'done' (already on the palette
     * stylesheet), or the reason it was left alone.
     *
     * @param  array<string, mixed>  $spec
     */
    private function upgrade(object $instance, array $spec): string
    {
        $map = is_string($instance->primitive_map)
            ? json_decode($instance->primitive_map, true)
            : $instance->primitive_map;
        $templateId = is_array($map) ? ($map['overlays'][$spec['overlay_ref']] ?? null) : null;

        if (! $templateId) {
            return 'no overlay in the install';
        }

        $template = DB::table('overlay_templates')->where('id', $templateId)->first(['id', 'html', 'css', 'template_tags']);

        if (! $template) {
            return 'overlay is gone';
        }

        $css = (string) $template->css;

        if ($css === $spec['css']) {
            return 'done';
        }

        if (hash('sha256', (string) $template->html) !== $spec['html_sha256']) {
            return 'html edited, or an older version of the overlay';
        }

        if (! in_array(hash('sha256', $css), $spec['old_css_sha256'], true)) {
            return 'css edited';
        }

        $tags = json_decode((string) $template->template_tags, true);
        $tags = array_values(array_unique(array_merge(is_array($tags) ? $tags : [], $spec['tags'])));
        $now = now();

        DB::transaction(function () use ($instance, $templateId, $spec, $tags, $now) {
            DB::table('overlay_templates')->where('id', $templateId)->update([
                'css' => $spec['css'],
                'template_tags' => json_encode($tags),
                'updated_at' => $now,
            ]);

            $existing = DB::table('overlay_controls')
                ->where('overlay_template_id', $templateId)
                ->pluck('key')
                ->all();

            foreach ($spec['controls'] as $control) {
                if (in_array($control['key'], $existing, true)) {
                    continue;
                }

                DB::table('overlay_controls')->insert([
                    'overlay_template_id' => $templateId,
                    'user_id' => $instance->user_id,
                    'key' => $control['key'],
                    'label' => $control['label'],
                    'description' => $control['description'],
                    'type' => 'color',
                    'value' => $control['value'],
                    'config' => null,
                    'sort_order' => $control['sort_order'],
                    'source' => null,
                    'source_managed' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return 'upgraded';
    }

    /**
     * The stylesheet it replaced is not kept, and the new one renders the
     * same colors until someone changes one. Nothing to undo.
     */
    public function down(): void {}
};
