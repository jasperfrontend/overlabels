---
name: "Follower bowling lane"
type: static
author: Overlabels
---

# Follower bowling lane

Chatters type !bowl to get in line, first come first served. Every fifteen seconds the next one in line bowls: the ball rolls for them, your ten newest followers are the pins, and the outcome is decided from the moment they left the line.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Fonts and `<style>` blocks. No scripts - they are stripped on save.

```html
<meta name="generator" content="Overlabels /engine">
<meta name="overlabels:engine" content="v1 2026-11-16 controls:20 sha256:fe0091e8729a">
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link href="https://fonts.bunny.net/css2?family=Albert+Sans:wght@400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
```

### `html`

The markup.

```html
<div data-overlabels-engine="v1" class="stage" id="lane-widget" data-last-throw="[[[c:list:lane:last_removed_at]]]" data-knocked="[[[c:bowl_knocked]]]">
  <div class="intro">
    <p class="eyebrow">Follower Bowling</p>
    <h1>Type <span class="cmd">!bowl</span> in chat</h1>
    <p class="sub">You join the line. When it is your turn the ball rolls for you, and the pins are this channel's ten newest followers.</p>
  </div>

  [[[if:c:list:lane:count > 0]]]
  <div class="line">
    <p class="label">Next up</p>
    <ol class="queue">[[[foreach:c:list:lane as p]]][[[if:loop.index <= 4]]]<li>[[[p]]]</li>[[[endif]]][[[endforeach]]]</ol>
  </div>
  [[[endif]]]

  <p class="count">[[[if:c:list:lane:count > 0]]]<b>[[[c:list:lane:count]]]</b> in line[[[else]]]Nobody in line yet[[[endif]]]</p>

  <div class="field">
    <div class="lane">
      <div class="gutter left"></div>
      <div class="gutter right"></div>
      <div class="arrows"><i></i><i></i><i></i><i></i><i></i></div>
      <div class="foul"></div>
    </div>

    <div class="pins" id="pins">
      [[[foreach:channel_followers as f]]][[[if:loop.index <= 9]]]<div class="pin" data-key="[[[f.user_id]]]" style="--fall: var(--pin[[[loop.index]]])"><img src="[[[f.user_profile_image_url]]]" alt=""><span>[[[f.user_name]]]</span></div>[[[endif]]][[[endforeach]]]
      [[[if:channel_followers.0.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin0)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.1.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin1)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.2.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin2)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.3.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin3)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.4.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin4)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.5.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin5)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.6.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin6)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.7.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin7)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.8.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin8)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
      [[[if:channel_followers.9.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pin9)"><img src="https://images.overlabels.com/overlays/twitch-avatar.png" alt=""><span>?</span></div>[[[endif]]]
    </div>

    <div class="ball" id="ball"><span class="hole hole-1"></span><span class="hole hole-2"></span><span class="hole hole-3"></span></div>

    <div class="slot" id="slot-strike">
      [[[if:c:bowl_knocked = 10]]]
      <div class="strike" id="strike">
        <div class="strike-word">STRIKE!</div>
        <div class="strike-name">[[[c:list:lane:last_removed]]] knocked down all ten</div>
      </div>
      [[[endif]]]
    </div>
  </div>

  <div class="score">
    <section class="now">
      <p class="label">Bowling</p>
      <div class="swap">
        <p class="bowler" id="bowler">[[[c:list:lane:last_removed]]]</p>
        <p class="bowler-idle">Waiting for a bowler</p>
      </div>
    </section>
    <section class="result">
      <p class="label">Pins down</p>
      <div class="swap">
        <p class="big num" id="score">[[[c:bowl_knocked]]]</p>
        <p class="big num dash">?</p>
      </div>
      <div class="swap status">
        <p class="status-result" id="status">[[[if:c:bowl_knocked = 10]]]strike![[[elseif:c:bowl_knocked = 0]]]gutter ball[[[else]]]of 10[[[endif]]]</p>
        <p class="status-rolling">rolling...</p>
      </div>
    </section>
  </div>
</div>
```

### `css`

The stylesheet. Tags work in here too.

```css
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
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 26 are defined by the overlay itself and are recreated, with the default values shown, for anyone who copies it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:bowl_t]]]` | expression | Last throw (Unix s) |  | no |
| `[[[c:bowl_age]]]` | expression | Seconds since the throw |  | no |
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
| `[[[c:background_color]]]` | color | Background | `#120720` | yes |
| `[[[c:panel_color]]]` | color | Lane | `#1d0b30` | yes |
| `[[[c:text_color]]]` | color | Text | `#fafafa` | yes |
| `[[[c:soft_text_color]]]` | color | Soft text | `#e9d5ff` | yes |
| `[[[c:muted_color]]]` | color | Labels and lines | `#a78bfa` | yes |
| `[[[c:highlight_color]]]` | color | Highlight | `#f472b6` | yes |

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
- `c:background_color` - Behind everything: the gutters, the holes the pins leave, and the edge around the ball and the pins.
- `c:panel_color` - The lane the ball rolls down.
- `c:text_color` - The heading, how many are in line, the follower names on the pins, and the name under a strike.
- `c:soft_text_color` - The how-to-play sentence, the score line, and "Waiting for a bowler".
- `c:muted_color` - Labels, the place numbers in line, the foul line, the ring around each follower's picture, and the lines between rows.
- `c:highlight_color` - The command, first place in line, the bowler's name, the ball, the head pin's ring and the strike banner.

Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in seconds of when it last changed.
