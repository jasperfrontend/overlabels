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
<meta name="overlabels:engine" content="v1 2026-11-15 controls:10 sha256:830ffa3e2228">
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link href="https://fonts.bunny.net/css2?family=Albert+Sans:wght@400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
```

### `html`

The markup.

```html
<div data-overlabels-engine="v1" class="stage" data-falling="[[[tower.falling]]]" data-danger="[[[c:danger]]]" data-banner="[[[c:banner]]]" style="--count: [[[tower.count]]]">
  [[[if:c:show_hint]]]
  <div class="intro">
    <p class="eyebrow">Chat Tower</p>
    <h1>Type <span class="cmd">!stack</span> in chat</h1>
    <p class="sub">Your name goes on the block. Aim with <span class="cmd">!stack left</span> or <span class="cmd">!stack right</span> to fight the lean. Whoever knocks it over gets named.</p>
  </div>
  [[[endif]]]

  <dl class="facts">
    [[[if:c:tower:last_stacker_name]]]<div class="fact"><dt>Last block</dt><dd>[[[c:tower:last_stacker_name]]]</dd></div>[[[endif]]]
    <div class="fact"><dt>Topples this stream</dt><dd class="num">[[[c:tower:topples_this_stream]]]</dd></div>
  </dl>

  <div class="field">
    <div class="fallline left"><span>fall line</span></div>
    <div class="fallline right"><span>fall line</span></div>
    <div class="cam">
      [[[foreach:tower as block]]]<div class="block" data-key="[[[block.position]]]" style="--p: [[[block.position]]]; --x: [[[block.x]]]; --c: [[[block.color ?? #9146ff]]]"><span>[[[block.name]]]</span></div>[[[endforeach]]]
    </div>
    <div class="ground"></div>
    <div class="gauge">
      <div class="gauge-band"></div>
      <div class="gauge-pin"></div>
    </div>

    <!-- each pop-up lives in its own wrapper that is always there, so one
         appearing never moves another: a moved element restarts its CSS
         animation, which replayed the banner's delayed fade-in -->
    <div class="slot" id="slot-empty">
      [[[if:tower.count = 0]]]
      <div class="empty" id="tower-empty">
        <div class="ghost"></div>
        <p>Waiting for the first block</p>
      </div>
      [[[endif]]]
    </div>

    <div class="slot" id="slot-record">
      [[[if:c:record_flash]]]
      <div class="record-flash" id="record-flash">New record <b>[[[c:tower:tallest_tower_record]]]</b></div>
      [[[endif]]]
    </div>

    <div class="slot" id="slot-banner">
      [[[if:c:banner]]]
      <div class="banner" id="topple-banner">
        <div class="banner-name">[[[c:tower:last_topple_by]]]</div>
        <div class="banner-mid">toppled the tower at <b>[[[c:tower:last_topple_height]]]</b></div>
        <div class="banner-small">record stands at [[[c:tower:tallest_tower_record]]]</div>
      </div>
      [[[endif]]]
    </div>
  </div>

  <div class="score">
    <section class="height">
      <p class="label">Height</p>
      <p class="big num">[[[c:tower:tower_height]]]</p>
      <p class="status">[[[if:c:banner]]]tower collapsed![[[elseif:tower.count = 0]]]no tower yet[[[elseif:c:danger = 2]]]about to fall[[[elseif:c:danger = 1]]][[[c:tower:tower_room|round:1]]] from falling[[[else]]]standing[[[endif]]]</p>
    </section>
    <section class="record">
      <p class="label">Record</p>
      <p class="big num">[[[c:tower:tallest_tower_record]]]</p>
      <ol class="roster">[[[foreach:c:list:tower_record as name]]][[[if:loop.index <= 4]]]<li>[[[name]]]</li>[[[endif]]][[[endforeach]]]</ol>
      [[[if:c:list:tower_record:count > 5]]]<p class="more">[[[c:list:tower_record:count]]] builders in all</p>[[[endif]]]
    </section>
  </div>
</div>
```

### `css`

The stylesheet. Tags work in here too.

```css
:root {
  --wave: [[[c:sway|round:3]]];
  --flash: #ff2d55;
  --pin: [[[c:lean_pct|round:1]]]%;
  --band: [[[c:band_pct|round:1]]]%;

  /* the Plinko palette: deep violet field, pink accent, orange and hot pink for danger */
  --ink: #fafafa;
  --ink-soft: #e9d5ff;
  --ink-muted: #a78bfa;
  --pink: #f472b6;
  --pink-deep: #ec4899;
  --orange: #f97316;
  --green: #4ade80;
  --field: #1d0b30;
  --field-deep: #120720;
  --line: rgb(167 139 250 / 0.35);
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
.cmd { font-family: var(--mono); color: var(--pink); white-space: nowrap; }

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
.record .big { font-size: 96px; color: var(--pink); }
.status { margin-top: 8px; font-size: 24px; font-weight: 600; color: var(--ink-soft); }
.stage[data-danger="1"] .status { color: var(--orange); }
.stage[data-danger="2"] .status { color: var(--pink-deep); animation: pulse 0.8s ease-in-out infinite alternate; }
/* for as long as the topple banner is up (the fall included) */
.stage[data-banner="1"] .status { color: var(--pink); animation: none; }

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
.stage[data-danger="1"] .fallline { border-color: var(--orange); }
.stage[data-danger="1"] .fallline span { color: var(--orange); }
.stage[data-danger="2"] .fallline { border-color: var(--pink-deep); }
.stage[data-danger="2"] .fallline span { color: var(--pink-deep); }

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
  background: rgb(244 114 182 / 0.3);
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
.stage[data-danger="1"] .gauge-pin { background: var(--orange); box-shadow: 0 0 16px rgb(249 115 22 / 0.8); }
.stage[data-danger="2"] .gauge-pin { background: var(--pink-deep); box-shadow: 0 0 20px rgb(236 72 153 / 0.9); }

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
    inset 0 0 0 100px rgb(18 7 32 / 0.28),
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
  8%, 30%, 66%, 88% { background: var(--flash); box-shadow: inset 0 -5px 0 rgb(0 0 0 / 0.3), 0 0 32px rgb(255 45 85 / 0.95), 0 4px 0 rgb(0 0 0 / 0.45); }
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
  background: rgb(18 7 32 / 0.92);
  border-top: 2px solid var(--pink);
  border-bottom: 2px solid var(--pink);
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
  color: var(--pink);
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
  background: rgb(18 7 32 / 0.92);
  border-top: 2px solid var(--green);
  border-bottom: 2px solid var(--green);
  text-align: center;
  font-family: var(--mono);
  font-size: 22px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--green);
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
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 10 are defined by the overlay itself and are recreated, with the default values shown, for anyone who copies it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:amp]]]` | expression | Sway amplitude |  | no |
| `[[[c:sway]]]` | expression | Sway wave |  | yes |
| `[[[c:lean_pct]]]` | expression | Gauge pin |  | yes |
| `[[[c:band_pct]]]` | expression | Gauge band |  | yes |
| `[[[c:danger]]]` | expression | Danger |  | yes |
| `[[[c:topple_age]]]` | expression | Seconds since the last topple |  | no |
| `[[[c:banner]]]` | expression | Topple banner visible |  | yes |
| `[[[c:record_age]]]` | expression | Seconds since the record moved |  | no |
| `[[[c:record_flash]]]` | expression | Record flash visible |  | yes |
| `[[[c:show_hint]]]` | boolean | Show how to play | `1` | yes |

### Control detail

- `c:amp` - How far the top of the tower swings, in block widths. Grows with height, so the safe zone shrinks the taller it gets.
  - expression: `0.055 * c.tower.tower_height`
- `c:sway` - Where the tower is in its swing, from -1 to 1. A 2.6 second wave. Each block swings 0.055 block widths for every row it sits above the ground.
  - expression: `sin(2 * PI * now_ms() / 2600)`
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
- `c:show_hint` - The how-to-play text on the left: type !stack, aim left or right. Switch off once your chat knows the game.
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

This overlay reads 1 List(s). Lists hold their own data and are not copied with an overlay - create one with a matching slug under /lists.

- `[[[c:list:tower_record]]]` - list slug `tower_record`

## Live data tags used

Beyond its controls, this overlay reads 3 data tag(s). These resolve against Twitch channel data and, for alerts, the firing event. A tag with no data renders as nothing unless it carries a `?? default`.

- `[[[tower]]]`
- `[[[tower.count]]]`
- `[[[tower.falling]]]`

## Copying this overlay

Open <https://overlabels.test/overlay/jura-patmos-volga-jaipur/public> while logged in to Overlabels and press **Copy**. That creates your own editable copy of the source above, along with the controls listed under Controls. It does not copy the author's integrations, Lists.

You can also paste the source into a new overlay by hand at <https://overlabels.test/templates/create>.
