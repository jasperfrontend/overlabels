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
    <div class="queue-title">[[[c:list:lane:count]]] player[[[if:c:list:lane:count != 1]]]s[[[endif]]]</div>
    [[[foreach:c:list:lane as p]]][[[if:loop.index <= 3]]]<div class="waiting">[[[p]]]</div>[[[endif]]][[[endforeach]]]
    <div class="queue-hint">!bowl to join</div>
  </div>
  <div class="lane">
    <div class="gutter top"></div>
    <div class="gutter bottom"></div>
    <div class="foul"></div>
    <div class="ball" id="ball"></div>
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
    <div class="bowler" id="bowler">[[[c:list:lane:last_removed]]]</div>
    <div class="score" id="score">[[[if:c:bowl_knocked = 10]]]STRIKE![[[elseif:c:bowl_knocked = 0]]]GUTTER[[[else]]][[[c:bowl_knocked]]] PINS[[[endif]]]</div>
    <div class="throw" id="throw">mods: !fbfirst &middot; !fbdraw</div>
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

  --ink: #e8eef5;
  --wood: #2a1f14;
  --wood-line: rgb(255 220 170 / 0.08);
  --edge: rgb(232 238 245 / 0.25);
  --accent: #ffb347;
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

.lane-widget {
  position: absolute;
  left: 250px;
  bottom: 40px;
  width: 1420px;
  height: 260px;
  display: grid;
  grid-template-columns: 200px 1200px;
  gap: 20px;
}

/* the waiting line, fed by the lane List */
.queue {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  padding: 14px 16px;
  border: 1px solid var(--edge);
  border-radius: 16px;
  background: rgb(6 16 25 / 0.75);
}

.queue-title {
  font-size: 11px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgb(232 238 245 / 0.6);
  margin-bottom: 4px;
}

.waiting {
  font-size: 16px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  padding: 3px 10px;
  border-radius: 999px;
  background: rgb(232 238 245 / 0.08);
}

.waiting:first-of-type {
  background: var(--accent);
  color: #1a1206;
}

.queue-hint {
  margin-top: 6px;
  font-size: 12px;
  color: rgb(232 238 245 / 0.55);
}

.lane {
  position: relative;
  width: 100%;
  height: 100%;
  border: 1px solid var(--edge);
  border-radius: 16px;
  background:
    repeating-linear-gradient(90deg, transparent 0 58px, var(--wood-line) 58px 60px),
    var(--wood);
  overflow: hidden;
}

.gutter {
  position: absolute;
  left: 0;
  right: 0;
  height: 18px;
  background: rgb(0 0 0 / 0.45);
}

.gutter.top {
  top: 0;
}

.gutter.bottom {
  bottom: 0;
}

.foul {
  position: absolute;
  left: 110px;
  top: 18px;
  bottom: 18px;
  width: 2px;
  background: rgb(232 238 245 / 0.18);
}

/* the ball: x along the lane, y into the gutter on a miss, spin from distance */
.ball {
  position: absolute;
  top: 50%;
  left: calc(60px + var(--bx) * 840px);
  width: 44px;
  height: 44px;
  margin: -22px 0 0 -22px;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 35%, #4a6cff, #0b1a6e 70%);
  box-shadow: 0 6px 14px rgb(0 0 0 / 0.6);
  transform: translateY(calc(var(--by) * 100px)) rotate(calc(var(--bx) * 1080deg)) scale(var(--pulse));
  opacity: var(--on);
}

.ball::before,
.ball::after {
  content: "";
  position: absolute;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: rgb(0 0 0 / 0.7);
  top: 14px;
  left: 16px;
}

.ball::after {
  top: 20px;
  left: 24px;
}

/* pins: the 1-2-3-4 triangle, front pin first (index 0 is the head pin) */
.pins {
  position: absolute;
  inset: 0;
}

.pin {
  position: absolute;
  width: 56px;
  display: grid;
  justify-items: center;
  gap: 3px;
  margin-left: -28px;
  margin-top: -30px;
  transform-origin: 50% 90%;
  transform: rotate(calc(var(--fall) * 78deg)) translate(calc(var(--fall) * 26px), calc(var(--fall) * 8px));
  opacity: calc(1 - var(--fall) * 0.7);
  transition: transform 0.35s cubic-bezier(0.3, 1.4, 0.6, 1), opacity 0.35s;
}

.pin img {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  border: 2px solid var(--ink);
  background: #10131a;
  object-fit: cover;
}

.pin span {
  font-size: 10px;
  font-weight: 600;
  white-space: nowrap;
  max-width: 70px;
  overflow: hidden;
  text-overflow: ellipsis;
  padding: 1px 6px;
  border-radius: 999px;
  background: rgb(0 0 0 / 0.55);
}

/* A filler pin stands in for a follower the channel does not have yet, so a
   new channel still bowls at ten. Same physics, dimmer face; a real follower
   takes its slot the moment they arrive. */
.pin-filler img {
  border-style: dashed;
  opacity: 0.55;
  filter: grayscale(1);
}

.pin-filler span {
  opacity: 0.7;
}

.pin:nth-child(1) { left: 930px; top: 130px; }
.pin:nth-child(2) { left: 995px; top: 90px; }
.pin:nth-child(3) { left: 995px; top: 170px; }
.pin:nth-child(4) { left: 1060px; top: 48px; }
.pin:nth-child(5) { left: 1060px; top: 130px; }
.pin:nth-child(6) { left: 1060px; top: 212px; }
.pin:nth-child(7) { left: 1125px; top: 34px; }
.pin:nth-child(8) { left: 1125px; top: 94px; }
.pin:nth-child(9) { left: 1125px; top: 154px; }
.pin:nth-child(10) { left: 1125px; top: 214px; }

.bowler {
  position: absolute;
  left: 140px;
  top: 30px;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: 0.04em;
  opacity: var(--bowler);
  transition: opacity 0.25s;
}

.bowler::after {
  content: " bowls";
  font-weight: 400;
  color: rgb(232 238 245 / 0.7);
}

.score {
  position: absolute;
  left: 140px;
  top: 58px;
  font-size: 48px;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: var(--accent);
  text-shadow: 0 2px 12px rgb(0 0 0 / 0.6);
  opacity: var(--show);
  transition: opacity 0.25s;
}

.throw {
  position: absolute;
  left: 140px;
  bottom: 30px;
  font-size: 14px;
  color: rgb(232 238 245 / 0.7);
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

