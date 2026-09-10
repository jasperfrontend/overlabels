---
name: Chat Tower
type: static
author: Overlabels
---

# Chat Tower

One shared tower your chat builds with !stack. Every block leans a little, the tower sways more the taller it gets, and when the lean crosses a fall line it comes down and the viewer who placed the last block gets named. The HUD shows height, room to fall, the all-time record and who built it.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.test/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Fonts and `<style>` blocks. No scripts - they are stripped on save.

```html
<meta name="generator" content="Overlabels /engine">
<meta name="overlabels:engine" content="v1 2026-09-10 controls:11 sha256:76be735a970d">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
```

### `html`

The markup.

```html
<div data-overlabels-engine="v1" class="tower-stage" id="tower-stage" data-falling="[[[tower.falling]]]" style="--count: [[[tower.count]]]">
  <div class="hud">
    <div class="hud-top">
      <div class="hud-height">
        <span class="hud-label">Height</span>
        <span class="hud-number" id="height">[[[c:tower:tower_height]]]</span>
        <span class="hud-sub">[[[if:c:danger = 2]]]about to fall[[[elseif:c:danger = 1]]][[[c:tower:tower_room|round:1]]] from falling[[[else]]]standing[[[endif]]]</span>
      </div>
      <div class="hud-record">
        <span class="hud-label">Record</span>
        <span class="hud-number hud-number-record" id="record">[[[c:tower:tallest_tower_record]]]</span>
        <span class="hud-sub hud-roster">[[[foreach:c:list:tower_record as name]]][[[if:loop.index <= 3]]]<span class="roster-name">[[[name]]]</span>[[[endif]]][[[endforeach]]][[[if:c:list:tower_record:count > 4]]]<span class="roster-more">[[[c:list:tower_record:count]]] builders</span>[[[endif]]]</span>
      </div>
    </div>
    <div class="gauge">
      <div class="gauge-band"></div>
      <div class="gauge-pin [[[if:c:danger = 2]]]bad[[[elseif:c:danger = 1]]]warn[[[endif]]]"></div>
    </div>
    <div class="hud-foot">
      <span class="hud-last">[[[if:c:tower:last_stacker_name]]]last block <b>[[[c:tower:last_stacker_name]]]</b>[[[endif]]]</span>
      <span class="hud-topples">[[[c:tower:topples_this_stream]]] topple[[[if:c:tower:topples_this_stream != 1]]]s[[[endif]]] this stream</span>
    </div>
  </div>

  <div class="field">
    <div class="fallline left"><span>fall line</span></div>
    <div class="fallline right"><span>fall line</span></div>
    <div class="ground"></div>
    <div class="cam" id="cam">
      [[[foreach:tower as block]]]<div class="block" data-key="[[[block.position]]]" style="--p: [[[block.position]]]; --x: [[[block.x]]]; background: [[[block.color ?? #9146ff]]]">[[[block.name]]]</div>[[[endforeach]]]
    </div>
    [[[if:tower.count = 0]]]
    <div class="empty">
      <div class="ghost"></div>
      [[[if:c:show_hint]]]<div class="hint"><span class="chip">!stack</span><span class="hint-text">lays the first block</span></div>[[[endif]]]
    </div>
    [[[endif]]]

    [[[if:c:banner]]]
    <div class="banner" id="banner">
      <div class="banner-big">TOPPLED AT [[[c:tower:last_topple_height]]]</div>
      <div class="banner-mid">last block by <b>[[[c:tower:last_topple_by]]]</b></div>
      <div class="banner-small">record stands at [[[c:tower:tallest_tower_record]]]</div>
    </div>
    [[[endif]]]

    [[[if:c:record_flash]]]
    <div class="record-flash" id="record-flash">NEW RECORD <b>[[[c:tower:tallest_tower_record]]]</b></div>
    [[[endif]]]
  </div>

  [[[if:c:show_hint]]]
  <div class="legend">
    <span class="chip">!stack</span><span class="chip">!stack left</span><span class="chip">!stack right</span><span class="legend-text">to build</span>
  </div>
  [[[endif]]]
</div>
```

### `css`

The stylesheet. Tags work in here too.

```css
:root {
  --sway: [[[c:sway|round:3]]];
  --cam: [[[c:cam|round:0]]]px;
  --pin: [[[c:lean_pct|round:1]]]%;
  --band: [[[c:band_pct|round:1]]]%;

  /* the hero's palette: deep violet field, pink accent, one orange for danger */
  --ink: #fafafa;
  --ink-soft: #e9d5ff;
  --ink-muted: #a78bfa;
  --violet: #a78bfa;
  --pink: #f472b6;
  --pink-deep: #ec4899;
  --orange: #f97316;
  --green: #4ade80;
  --field: #1d0b30;
  --field-deep: #120720;
  --line: rgb(167 139 250 / 0.35);
  --mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace;

  /* one block is 4 units wide and 1 unit tall; a unit is 40px */
  --unit: 40px;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html,
body {
  width: 1920px;
  height: 1080px;
  overflow: hidden;
  background: transparent;
}

body {
  color: var(--ink);
  font-family: "Albert Sans", system-ui, sans-serif;
  -webkit-font-smoothing: antialiased;
}

/* a 560 x 1000 column on the right edge of a 1080p canvas */
.tower-stage {
  position: absolute;
  right: 40px;
  top: 40px;
  width: 560px;
  height: 1000px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* ---------- the HUD ---------- */
.hud {
  flex: none;
  padding: 20px 24px 18px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: rgb(18 7 32 / 0.82);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.hud-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
}

.hud-height,
.hud-record {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.hud-record {
  text-align: right;
  align-items: flex-end;
}

.hud-label {
  font-family: var(--mono);
  font-size: 12px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--pink);
}

.hud-number {
  font-size: 68px;
  font-weight: 800;
  line-height: 0.95;
  letter-spacing: -0.03em;
  font-variant-numeric: tabular-nums;
  color: var(--ink);
}

.hud-number-record {
  color: var(--pink);
  font-size: 44px;
  line-height: 1.1;
}

.hud-sub {
  font-size: 15px;
  color: var(--ink-soft);
}

.hud-roster {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 4px 8px;
  max-width: 260px;
  font-family: var(--mono);
  font-size: 12px;
  color: var(--ink-muted);
  line-height: 1.4;
}

.roster-more {
  color: var(--pink);
}

/* the lean gauge: 0% is the left fall line, 100% the right one; the band is
   the sway, the pin the top block */
.gauge {
  position: relative;
  height: 10px;
  border: 1px solid var(--line);
  border-radius: 6px;
  background: rgb(29 11 48 / 0.9);
}

.gauge::before {
  content: "";
  position: absolute;
  left: 50%;
  top: -3px;
  bottom: -3px;
  width: 1px;
  background: var(--line);
}

.gauge-band {
  position: absolute;
  top: 0;
  bottom: 0;
  left: calc(var(--pin) - var(--band));
  width: calc(var(--band) * 2);
  border-radius: 6px;
  background: rgb(244 114 182 / 0.22);
}

.gauge-pin {
  position: absolute;
  top: -5px;
  width: 5px;
  height: 18px;
  margin-left: -2px;
  left: var(--pin);
  border-radius: 3px;
  background: var(--ink);
  transition: left 0.35s ease;
}

.gauge-pin.warn {
  background: var(--orange);
  box-shadow: 0 0 12px rgb(249 115 22 / 0.7);
}

.gauge-pin.bad {
  background: var(--pink-deep);
  box-shadow: 0 0 16px rgb(236 72 153 / 0.9);
}

.hud-foot {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-family: var(--mono);
  font-size: 12px;
  color: var(--ink-muted);
}

.hud-foot b {
  color: var(--ink-soft);
  font-weight: 700;
}

/* ---------- the field: fall lines, ground, the tower ---------- */
.field {
  position: relative;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.fallline {
  position: absolute;
  top: 0;
  bottom: 34px;
  width: 0;
  border-left: 1px dashed var(--line);
}

.fallline.left {
  left: calc(50% - var(--unit) * 4);
}

.fallline.right {
  left: calc(50% + var(--unit) * 4);
}

.fallline span {
  position: absolute;
  top: 8px;
  left: 8px;
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--ink-muted);
  white-space: nowrap;
}

.fallline.left span {
  left: auto;
  right: 8px;
}

.ground {
  position: absolute;
  left: 40px;
  right: 40px;
  bottom: 34px;
  height: 2px;
  background: var(--line);
}

/* the camera follows the top: once the tower is taller than the field the
   whole stack slides down, block by block */
.cam {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 34px;
  height: 0;
  transform: translateY(var(--cam));
  transition: transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}

/* a block sits at its own x (units from the base centre) and position (1 =
   the base); the sway is a lean about the base, so each block moves in
   proportion to how high up it is */
.block {
  position: absolute;
  width: calc(var(--unit) * 4);
  height: var(--unit);
  left: calc(50% - var(--unit) * 2 + var(--x) * var(--unit));
  bottom: calc((var(--p) - 1) * var(--unit));
  transform: translateX(calc(var(--sway) * var(--p) / max(var(--count), 1) * var(--unit)));
  border-radius: 4px;
  box-shadow:
    inset 0 -3px 0 rgb(0 0 0 / 0.35),
    inset 0 1px 0 rgb(255 255 255 / 0.22),
    0 2px 0 rgb(0 0 0 / 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 12px;
  font-family: var(--mono);
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  text-shadow: 0 1px 0 rgb(0 0 0 / 0.7);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  animation: land 0.32s cubic-bezier(0.2, 0.9, 0.3, 1.3);
}

@keyframes land {
  from {
    margin-bottom: 18px;
    opacity: 0.3;
  }

  to {
    margin-bottom: 0;
    opacity: 1;
  }
}

/* the topple: tower.falling is set for three seconds after the fall, the
   blocks tumble toward their own side, top first, then the data clears */
.tower-stage[data-falling="1"] .block {
  animation: none;
  transform: translate(calc(var(--x) * 120px + 40px), 820px) rotate(calc(var(--x) * 90deg + 35deg));
  opacity: 0;
  transition:
    transform 1.4s cubic-bezier(0.45, 0, 0.9, 0.45) calc((var(--count) - var(--p)) * 30ms),
    opacity 0.5s ease-in calc(1.1s + (var(--count) - var(--p)) * 30ms);
}

/* ---------- empty ground ---------- */
.empty {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 34px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.ghost {
  width: calc(var(--unit) * 4);
  height: var(--unit);
  border: 2px dashed var(--line);
  border-radius: 4px;
}

.hint {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}

.hint-text {
  font-size: 15px;
  color: var(--ink-soft);
}

/* ---------- the moment ---------- */
.banner {
  position: absolute;
  left: 0;
  right: 0;
  top: 46%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 26px 20px;
  background: rgb(18 7 32 / 0.86);
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
  text-align: center;
  animation: banner-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.2);
}

@keyframes banner-in {
  from {
    opacity: 0;
    transform: translateY(-50%) scale(0.92);
  }

  to {
    opacity: 1;
    transform: translateY(-50%) scale(1);
  }
}

.banner-big {
  font-size: 44px;
  font-weight: 800;
  letter-spacing: 0.02em;
  color: var(--pink);
  text-shadow: 0 0 24px rgb(244 114 182 / 0.55);
}

.banner-mid {
  font-size: 20px;
  color: var(--ink);
}

.banner-mid b {
  font-weight: 800;
}

.banner-small {
  font-family: var(--mono);
  font-size: 13px;
  color: var(--ink-muted);
}

.record-flash {
  position: absolute;
  left: 50%;
  top: 40px;
  transform: translateX(-50%);
  padding: 10px 22px;
  border: 1px solid rgb(74 222 128 / 0.6);
  border-radius: 9999px;
  background: rgb(18 7 32 / 0.86);
  font-family: var(--mono);
  font-size: 15px;
  letter-spacing: 0.14em;
  color: var(--green);
  white-space: nowrap;
  animation: land 0.32s cubic-bezier(0.2, 0.9, 0.3, 1.3);
}

.record-flash b {
  font-size: 18px;
}

/* ---------- chips ---------- */
.legend {
  flex: none;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 36px;
}

.chip {
  display: inline-flex;
  align-items: center;
  font-family: var(--mono);
  font-size: 14px;
  padding: 7px 14px;
  border-radius: 9999px;
  color: var(--ink-soft);
  background: rgb(18 7 32 / 0.82);
  border: 1px solid var(--line);
}

.legend-text {
  margin-left: 4px;
  font-size: 14px;
  color: var(--ink-muted);
}
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 11 are defined by the overlay itself and are recreated, with the default values shown, for anyone who copies it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:amp]]]` | expression | Sway amplitude |  | no |
| `[[[c:sway]]]` | expression | Sway |  | yes |
| `[[[c:cam]]]` | expression | Camera |  | yes |
| `[[[c:lean_pct]]]` | expression | Gauge pin |  | yes |
| `[[[c:band_pct]]]` | expression | Gauge band |  | yes |
| `[[[c:danger]]]` | expression | Danger |  | no |
| `[[[c:topple_age]]]` | expression | Seconds since the last topple |  | no |
| `[[[c:banner]]]` | expression | Topple banner visible |  | no |
| `[[[c:record_age]]]` | expression | Seconds since the record moved |  | no |
| `[[[c:record_flash]]]` | expression | Record flash visible |  | no |
| `[[[c:show_hint]]]` | boolean | Show the command hints | `1` | no |

### Control detail

- `c:amp` - How far the top of the tower swings, in block widths. Grows with height, so the safe zone shrinks the taller it gets.
  - expression: `0.055 * c.tower.tower_height`
- `c:sway` - Where the top block is in its swing right now, in block widths. A 2.6 second wave.
  - expression: `c.amp * sin(2 * PI * now_ms() / 2600)`
- `c:cam` - Pixels the whole tower slides down once it is taller than the field, so the top stays in view.
  - expression: `max(0, c.tower.tower_height - 17) * 40`
- `c:lean_pct` - The top block on the gauge: 0 is the left fall line, 100 the right one, 50 dead centre.
  - expression: `clamp(50 + c.tower.tower_lean / 4 * 50, 0, 100)`
- `c:band_pct` - Half the sway as a share of the gauge, drawn either side of the pin.
  - expression: `clamp(c.amp / 4 * 50, 0, 50)`
- `c:danger` - 0 standing, 1 close (under 1.8 of room), 2 about to fall (under 0.8).
  - expression: `c.tower.tower_room < 0.8 ? 2 : (c.tower.tower_room < 1.8 ? 1 : 0)`
- `c:topple_age` - Anchored on the last_topple_height control, which is written on every topple.
  - expression: `(now_ms() - c.tower.last_topple_height_at * 1000) / 1000`
- `c:banner` - Nine seconds after a topple. Never before the first one.
  - expression: `c.tower.last_topple_height > 0 && c.topple_age > -1 && c.topple_age < 9 ? 1 : 0`
- `c:record_age` - The record control is written only on a strict beat, so its timestamp is when the record last moved.
  - expression: `(now_ms() - c.tower.tallest_tower_record_at * 1000) / 1000`
- `c:record_flash` - Six seconds after the record moves. Not for the very first block.
  - expression: `c.tower.tallest_tower_record > 1 && c.record_age > -1 && c.record_age < 6 ? 1 : 0`
- `c:show_hint` - The !stack chips under the tower and on the empty ground. Switch off once your chat knows the game.
  - min=null, max=null, step=1, reset_value=1, random=false, random_interval=null

Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in seconds of when it last changed.

## Requirements

This overlay reads live data from: **Chat Tower**. Those integrations must be connected under Settings -> Integrations before the tags below resolve to anything. Connecting a service provisions its controls automatically - they are not part of the copy, and they are account-wide rather than per-overlay.

### Chat Tower

| Tag | Type | Control |
|---|---|---|
| `[[[c:tower:last_stacker_name]]]` | text | Last Stacker Name |
| `[[[c:tower:last_topple_by]]]` | text | Last Topple By |
| `[[[c:tower:last_topple_height]]]` | number | Last Topple Height |
| `[[[c:tower:tallest_tower_record]]]` | number | Tallest Tower Record (all time) |
| `[[[c:tower:topples_this_stream]]]` | counter | Topples This Stream |
| `[[[c:tower:tower_height]]]` | number | Tower Height |
| `[[[c:tower:tower_room]]]` | number | Tower Room To Fall |

### Lists

This overlay reads 1 List(s). Lists hold their own data and are not copied with an overlay - create one with a matching slug under /dashboard/lists.

- `[[[c:list:tower_record]]]` - list slug `tower_record`

## Live data tags used

Beyond its controls, this overlay reads 14 data tag(s). These resolve against Twitch channel data and, for alerts, the firing event. A tag with no data renders as nothing unless it carries a `?? default`.

- `[[[block.color]]]`
- `[[[block.name]]]`
- `[[[block.position]]]`
- `[[[block.x]]]`
- `[[[else]]]`
- `[[[endforeach]]]`
- `[[[endif]]]`
- `[[[if:c:banner]]]`
- `[[[if:c:record_flash]]]`
- `[[[if:c:show_hint]]]`
- `[[[if:c:tower:last_stacker_name]]]`
- `[[[name]]]`
- `[[[tower.count]]]`
- `[[[tower.falling]]]`

## Copying this overlay

Open <https://overlabels.test/overlay/triglav-bologna-arran-ebro/public> while logged in to Overlabels and press **Copy**. That creates your own editable copy of the source above, along with the controls listed under Controls. It does not copy the author's integrations, Lists.

You can also paste the source into a new overlay by hand at <https://overlabels.test/templates/create>.
