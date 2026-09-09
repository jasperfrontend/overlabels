---
name: "Follower bowling lane"
type: static
author: Overlabels
---

# Follower bowling lane

Chatters type !bowl to get in line; a mod runs !list lane pop first (next up) or !list lane draw (raffle) and whoever was removed bowls: the ball rolls for them, your ten latest followers are the pins, and the outcome is decided from the removal timestamp. Switch on the gobowl control to show it.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Fonts and `<style>` blocks. No scripts - they are stripped on save.

```html
<meta name="generator" content="Overlabels /engine">
<meta name="overlabels:engine" content="v1 2026-08-30 controls:20 sha256:4d2afc21caa1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@400;600;800&display=swap" rel="stylesheet">
```

### `html`

The markup.

```html
[[[if:c:gobowl]]]
<div data-overlabels-engine="v1" class="lane-widget" id="lane-widget" data-last-throw="[[[c:list:lane:last_removed_at]]]">
  <div class="queue" id="queue">
    <div class="queue-head">
      <span class="queue-label">In line</span>
      <span class="queue-rule"></span>
      <span class="queue-count">[[[c:list:lane:count]]]</span>
    </div>
    <div class="queue-list">
      [[[foreach:c:list:lane as p]]][[[if:loop.index <= 2]]]<div class="waiting"><span class="waiting-index"></span><span class="waiting-name">[[[p]]]</span></div>[[[endif]]][[[endforeach]]]
    </div>
    <div class="queue-foot">
      <div class="queue-join"><span class="chip chip-cmd">!bowl</span><span class="queue-join-text">to join</span></div>
      <div class="queue-hint">Your ten newest followers are the pins.</div>
    </div>
  </div>
  <div class="lane">
    <div class="gutter top"></div>
    <div class="gutter bottom"></div>
    <div class="foul"></div>
    <div class="arrow arrow-big"></div>
    <div class="arrow arrow-small"></div>
    <div class="ball-trail"></div>
    <div class="ball" id="ball"><span class="hole hole-1"></span><span class="hole hole-2"></span><span class="hole hole-3"></span></div>
    <div class="pins" id="pins">
      [[[foreach:channel_followers as f]]][[[if:loop.index <= 9]]]<div class="pin" data-key="[[[f.user_id]]]" style="--fall: var(--pin[[[loop.index]]])"><img src="[[[f.user_profile_image_url]]]" alt=""><span>[[[f.user_name]]]</span></div>[[[endif]]][[[endforeach]]]
      [[[if:channel_followers.count <= 0]]]<div class="pin pin-filler" style="--fall: var(--pin0)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.count <= 1]]]<div class="pin pin-filler" style="--fall: var(--pin1)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.count <= 2]]]<div class="pin pin-filler" style="--fall: var(--pin2)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.count <= 3]]]<div class="pin pin-filler" style="--fall: var(--pin3)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.count <= 4]]]<div class="pin pin-filler" style="--fall: var(--pin4)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.count <= 5]]]<div class="pin pin-filler" style="--fall: var(--pin5)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.count <= 6]]]<div class="pin pin-filler" style="--fall: var(--pin6)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.count <= 7]]]<div class="pin pin-filler" style="--fall: var(--pin7)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.count <= 8]]]<div class="pin pin-filler" style="--fall: var(--pin8)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.count <= 9]]]<div class="pin pin-filler" style="--fall: var(--pin9)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
    </div>
    <div class="bowler chip" id="bowler">[[[c:list:lane:last_removed]]]</div>
    <div class="score" id="score">[[[if:c:bowl_knocked = 10]]]STRIKE![[[elseif:c:bowl_knocked = 0]]]GUTTER[[[else]]][[[c:bowl_knocked]]] PINS[[[endif]]]</div>
    <div class="throw" id="throw"><span class="chip chip-mods">mods</span><span class="throw-cmds">!fbfirst &middot; !fbdraw</span></div>
  </div>
</div>
[[[endif]]]
```

### `css`

The stylesheet. Tags work in here too.

```css
:root {
  --bt: [[[c:bowl_t]]];
  --age: [[[c:bowl_age|round:2]]];
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

  /* the hero's palette: deep violet field, pink accents, blue ball */
  --ink: #fafafa;
  --ink-soft: #e9d5ff;
  --ink-muted: #9ca3af;
  --violet: #a78bfa;
  --violet-soft: #c4b5fd;
  --pink: #f472b6;
  --pink-deep: #ec4899;
  --blue: #60a5fa;
  --field: #1d0b30;
  --field-deep: #2c074b;
  --glow: #650e8f;
  --mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
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

/* the whole thing is a 1840 x 440 strip along the bottom of a 1080p canvas */
.lane-widget {
  position: absolute;
  left: 40px;
  bottom: 60px;
  width: 1840px;
  height: 440px;
  display: grid;
  grid-template-columns: 340px 1460px;
  gap: 40px;
}

/* ---------- the queue panel, fed by the lane List ---------- */
.queue {
  display: flex;
  flex-direction: column;
  padding: 28px 26px;
  border-radius: 8px;
  border: 1px solid rgb(167 139 250 / 0.35);
  background:
    radial-gradient(120% 70% at 15% 0%, rgb(101 14 143 / 0.55), transparent 70%),
    linear-gradient(180deg, var(--field-deep), var(--field));
  box-shadow: 0 0 60px rgb(101 14 143 / 0.35);
}

.queue-head {
  display: flex;
  align-items: center;
  gap: 10px;
}

.queue-label {
  font-family: var(--mono);
  font-size: 13px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--pink);
}

.queue-rule {
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, rgb(244 114 182 / 0.5), rgb(167 139 250 / 0.05));
}

.queue-count {
  font-family: var(--mono);
  font-size: 13px;
  color: var(--violet-soft);
}

.queue-list {
  margin-top: 22px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  counter-reset: place;
}

.waiting {
  display: flex;
  align-items: center;
  gap: 12px;
  counter-increment: place;
}

.waiting-index::before {
  content: counter(place);
  display: inline-block;
  width: 24px;
  font-family: var(--mono);
  font-size: 15px;
  font-weight: 700;
  color: var(--violet);
}

.waiting-name {
  font-size: 17px;
  font-weight: 500;
  color: var(--ink-soft);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* first in line is next up: pink, bold, brighter */
.waiting:first-child .waiting-index::before {
  color: var(--pink);
}

.waiting:first-child .waiting-name {
  font-weight: 600;
  color: var(--ink);
}

.queue-foot {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.queue-join {
  display: flex;
  align-items: center;
  gap: 10px;
}

.queue-join-text {
  font-size: 15px;
  color: var(--violet-soft);
}

.queue-hint {
  font-size: 14px;
  color: var(--ink-muted);
}

.chip {
  display: inline-flex;
  align-items: center;
  font-family: var(--mono);
  border-radius: 9999px;
}

.chip-cmd {
  font-size: 16px;
  padding: 9px 16px;
  color: var(--blue);
  background: rgb(59 130 246 / 0.12);
}

.chip-mods {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  padding: 5px 11px;
  color: var(--pink);
  background: rgb(236 72 153 / 0.12);
}

/* ---------- the lane ---------- */
.lane {
  position: relative;
  width: 100%;
  height: 100%;
  border-radius: 8px;
  overflow: hidden;
  border-top: 1px solid rgb(167 139 250 / 0.35);
  border-bottom: 1px solid rgb(167 139 250 / 0.35);
  box-shadow: 0 0 90px rgb(101 14 143 / 0.55);
  background:
    repeating-linear-gradient(180deg, rgb(167 139 250 / 0.09) 0 1px, transparent 1px 40px),
    linear-gradient(90deg, rgb(29 11 48 / 0.95), rgb(101 14 143 / 0.42) 62%, rgb(244 114 182 / 0.2));
}

.gutter {
  position: absolute;
  left: 0;
  right: 0;
  height: 14px;
  background: linear-gradient(90deg, rgb(139 92 246 / 0.2), rgb(236 72 153 / 0.5));
  box-shadow: inset 0 0 12px 0 rgb(236 72 153 / 0.5);
}

.gutter.top {
  top: 0;
}

.gutter.bottom {
  bottom: 0;
}

.foul {
  position: absolute;
  left: 108px;
  top: 14px;
  bottom: 14px;
  width: 2px;
  background: linear-gradient(180deg, rgb(244 114 182 / 0), rgb(244 114 182 / 0.55), rgb(244 114 182 / 0));
}

/* aiming arrows on the boards */
.arrow {
  position: absolute;
  width: 0;
  height: 0;
}

.arrow-big {
  left: 196px;
  top: 184px;
  border-top: 36px solid transparent;
  border-bottom: 36px solid transparent;
  border-left: 24px solid rgb(244 114 182 / 0.14);
}

.arrow-small {
  left: 288px;
  top: 202px;
  border-top: 18px solid transparent;
  border-bottom: 18px solid transparent;
  border-left: 16px solid rgb(244 114 182 / 0.1);
}

/* the ball: x along the lane from the foul line to the rack, y into the
   gutter on a miss, spin from distance, the pulse on release; the trail
   follows it */
.ball-trail {
  position: absolute;
  top: 204px;
  left: calc(var(--bx) * 850px - 40px);
  width: 300px;
  height: 32px;
  border-radius: 9999px;
  background: linear-gradient(90deg, rgb(59 130 246 / 0), rgb(59 130 246 / 0.4));
  filter: blur(10px);
  opacity: calc(var(--on) * var(--bx));
}

.ball {
  position: absolute;
  top: 178px;
  left: calc(224px + var(--bx) * 850px);
  width: 84px;
  height: 84px;
  border-radius: 9999px;
  background: radial-gradient(circle at 34% 28%, var(--blue), #2563eb 55%, #1e40af);
  box-shadow:
    0 0 50px rgb(59 130 246 / 0.6),
    inset -7px -9px 18px rgb(0 0 0 / 0.45);
  transform: translateY(calc(var(--by) * 140px)) rotate(calc(var(--bx) * 1080deg)) scale(var(--pulse));
  opacity: var(--on);
}

.hole {
  position: absolute;
  width: 11px;
  height: 11px;
  border-radius: 9999px;
  background: radial-gradient(circle at 40% 35%, #163a80, #0b1d43);
  box-shadow:
    inset 0 2px 3px rgb(0 0 0 / 0.75),
    0 1px 0 rgb(147 197 253 / 0.3);
}

.hole-1 {
  left: 34px;
  top: 22px;
  width: 12px;
  height: 12px;
}

.hole-2 {
  left: 22px;
  top: 41px;
}

.hole-3 {
  left: 44px;
  top: 45px;
}

/* ---------- pins: apex left toward the ball, 1-2-3-4 columns ---------- */
.pins {
  position: absolute;
  left: 1072px;
  top: 66px;
  width: 308px;
  height: 308px;
}

.pin {
  position: absolute;
  width: 92px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  transform-origin: 50% 90%;
  transform: rotate(calc(var(--fall) * 78deg)) translate(calc(var(--fall) * 26px), calc(var(--fall) * 8px));
  opacity: calc(1 - var(--fall) * 0.7);
  transition:
    transform 0.35s cubic-bezier(0.3, 1.4, 0.6, 1),
    opacity 0.35s;
}

.pin img {
  width: 56px;
  height: 56px;
  border-radius: 9999px;
  border: 2px solid rgb(167 139 250 / 0.9);
  background: radial-gradient(circle at 35% 30%, #4c1d6f, var(--field));
  box-shadow: 0 0 18px rgb(139 92 246 / 0.35);
  object-fit: cover;
}

.pin span {
  max-width: 92px;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink-soft);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* the head pin carries the pink ring */
.pin:nth-child(1) img {
  border-color: var(--pink-deep);
  box-shadow: 0 0 28px rgb(236 72 153 / 0.6);
}

.pin:nth-child(1) span {
  font-weight: 700;
  color: var(--ink);
}

/* a stand-in for a follower the channel does not have yet, so a new
   channel still bowls at ten: same physics, dimmer face; a real follower
   takes its slot the moment they arrive */
.pin-filler img {
  border-style: dashed;
  opacity: 0.55;
  filter: grayscale(1);
}

.pin-filler span {
  opacity: 0.7;
}

.pin:nth-child(1) { left: -18px; top: 126px; }
.pin:nth-child(2) { left: 66px; top: 84px; }
.pin:nth-child(3) { left: 66px; top: 168px; }
.pin:nth-child(4) { left: 150px; top: 42px; }
.pin:nth-child(5) { left: 150px; top: 126px; }
.pin:nth-child(6) { left: 150px; top: 210px; }
.pin:nth-child(7) { left: 234px; top: 0; }
.pin:nth-child(8) { left: 234px; top: 84px; }
.pin:nth-child(9) { left: 234px; top: 168px; }
.pin:nth-child(10) { left: 234px; top: 252px; }

/* ---------- who bowls, what happened, who runs the lane ---------- */
.bowler {
  position: absolute;
  left: 32px;
  top: 34px;
  gap: 10px;
  font-size: 14px;
  padding: 8px 16px;
  color: #f9a8d4;
  background: rgb(29 11 48 / 0.72);
  opacity: var(--bowler);
  transition: opacity 0.25s;
}

.bowler::after {
  content: "bowls";
  color: var(--violet-soft);
}

.score {
  position: absolute;
  left: 32px;
  top: 84px;
  font-size: 56px;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--ink);
  text-shadow:
    0 0 24px rgb(244 114 182 / 0.55),
    0 2px 12px rgb(0 0 0 / 0.6);
  opacity: var(--show);
  transition: opacity 0.25s;
}

.throw {
  position: absolute;
  left: 32px;
  bottom: 34px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.throw-cmds {
  font-family: var(--mono);
  font-size: 15px;
  color: var(--violet-soft);
}
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 21 are defined by the overlay itself and are recreated, with the default values shown, for anyone who installs it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:bowl_t]]]` | expression | Last throw (Unix s) |  | yes |
| `[[[c:bowl_age]]]` | expression | Seconds since the throw |  | yes |
| `[[[c:bowl_seed]]]` | expression | Throw seed |  | no |
| `[[[c:bowl_knocked]]]` | expression | Pins knocked |  | yes |
| `[[[c:ball_x]]]` | expression | Ball position |  | yes |
| `[[[c:ball_pulse]]]` | expression | Ball wind-up |  | yes |
| `[[[c:ball_y]]]` | expression | Ball drift |  | yes |
| `[[[c:ball_on]]]` | expression | Ball visible |  | yes |
| `[[[c:bowl_show]]]` | expression | Score visible |  | yes |
| `[[[c:bowler_show]]]` | expression | Bowler name visible |  | yes |
| `[[[c:pin_0]]]` | expression | Pin 1 down |  | yes |
| `[[[c:pin_1]]]` | expression | Pin 2 down |  | yes |
| `[[[c:pin_2]]]` | expression | Pin 3 down |  | yes |
| `[[[c:pin_3]]]` | expression | Pin 4 down |  | yes |
| `[[[c:pin_4]]]` | expression | Pin 5 down |  | yes |
| `[[[c:pin_5]]]` | expression | Pin 6 down |  | yes |
| `[[[c:pin_6]]]` | expression | Pin 7 down |  | yes |
| `[[[c:pin_7]]]` | expression | Pin 8 down |  | yes |
| `[[[c:pin_8]]]` | expression | Pin 9 down |  | yes |
| `[[[c:pin_9]]]` | expression | Pin 10 down |  | yes |
| `[[[c:gobowl]]]` | boolean | gobowl | `0` | no |

### Control detail

- `c:bowl_t` - When someone was last removed from the lane List by pop or draw. 0 until that has happened.
  - expression: `c.list["lane:last_removed_at"]`
- `c:bowl_age` - The removal broadcast is queued (worker sleeps 3 s on prod) and the stamp is whole seconds, so it can arrive up to 3.5 s late. Everything below waits for that.
  - expression: `(now_ms() - c.bowl_t * 1000) / 1000`
- `c:bowl_seed` - 0..1, stable for one throw, different for the next: the removal timestamp through the shader noise trick.
  - expression: `fract(sin(c.bowl_t * 0.001 + 78.233) * 43758.5453)`
- `c:bowl_knocked` - 0 = gutter (12%), 10 = strike (15%), otherwise 1..9 with the middle counts most likely.
  - expression: `c.bowl_seed < 0.12 ? 0 : (c.bowl_seed > 0.85 ? 10 : 1 + floor(8.999 * acos(1 - 2 * (c.bowl_seed - 0.12) / 0.73) / PI))`
- `c:ball_x` - Waits at the foul line until 3.5 s after the stamp, then a 1.6 s roll to the pins.
  - expression: `clamp((c.bowl_age - 3.5) / 1.6, 0, 1)`
- `c:ball_pulse` - Pulses at the line while waiting for the roll, so the pick is visible the instant it arrives.
  - expression: `c.bowl_age < 3.5 ? 1 + 0.18 * abs(sin(c.bowl_age * 6)) : 1`
- `c:ball_y` - Curves into the gutter on a 0.
  - expression: `c.bowl_knocked == 0 ? c.ball_x * c.ball_x : 0`
- `c:ball_on`
  - expression: `c.bowl_t > 0 && c.bowl_age > -1 && c.bowl_age < 12 ? 1 : 0`
- `c:bowl_show`
  - expression: `c.bowl_t > 0 && c.bowl_age > 5.2 && c.bowl_age < 12 ? 1 : 0`
- `c:bowler_show` - From the pick until the result clears.
  - expression: `c.bowl_t > 0 && c.bowl_age > -1 && c.bowl_age < 12 ? 1 : 0`
- `c:pin_0` - Falls when the ball arrives, if the throw knocked that many; stands back up after 12 s.
  - expression: `c.bowl_t > 0 && c.bowl_knocked > 0 && c.bowl_age > 5.1 && c.bowl_age < 12 ? 1 : 0`
- `c:pin_1`
  - expression: `c.bowl_t > 0 && c.bowl_knocked > 1 && c.bowl_age > 5.15 && c.bowl_age < 12 ? 1 : 0`
- `c:pin_2`
  - expression: `c.bowl_t > 0 && c.bowl_knocked > 2 && c.bowl_age > 5.2 && c.bowl_age < 12 ? 1 : 0`
- `c:pin_3`
  - expression: `c.bowl_t > 0 && c.bowl_knocked > 3 && c.bowl_age > 5.25 && c.bowl_age < 12 ? 1 : 0`
- `c:pin_4`
  - expression: `c.bowl_t > 0 && c.bowl_knocked > 4 && c.bowl_age > 5.3 && c.bowl_age < 12 ? 1 : 0`
- `c:pin_5`
  - expression: `c.bowl_t > 0 && c.bowl_knocked > 5 && c.bowl_age > 5.35 && c.bowl_age < 12 ? 1 : 0`
- `c:pin_6`
  - expression: `c.bowl_t > 0 && c.bowl_knocked > 6 && c.bowl_age > 5.4 && c.bowl_age < 12 ? 1 : 0`
- `c:pin_7`
  - expression: `c.bowl_t > 0 && c.bowl_knocked > 7 && c.bowl_age > 5.45 && c.bowl_age < 12 ? 1 : 0`
- `c:pin_8`
  - expression: `c.bowl_t > 0 && c.bowl_knocked > 8 && c.bowl_age > 5.5 && c.bowl_age < 12 ? 1 : 0`
- `c:pin_9`
  - expression: `c.bowl_t > 0 && c.bowl_knocked > 9 && c.bowl_age > 5.55 && c.bowl_age < 12 ? 1 : 0`
- `c:gobowl` - This enables bowling on stream

Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in seconds of when it last changed.

