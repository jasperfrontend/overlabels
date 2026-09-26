---
name: Chat Emote Bubbles
type: static
author: Overlabels
---

# Chat Emote Bubbles

Every emote your chat types floats across the stream in a soap bubble, one bubble per emote, in the order they were typed. Ten controls set the look (bubble, snowflake or heart), the size, the color, the speed, the direction, how many bubbles can be on screen at once, where they appear and whether they pop at the edge or float off it. Every change lands in OBS the moment it is made.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Fonts and `<style>` blocks. No scripts - they are stripped on save. Nothing to load here: the overlay draws pictures, not text.

```html
<meta name="generator" content="Overlabels chat-emote-bubbles">
```

### `html`

The markup. One element per emote occurrence, keyed by the occurrence id so the renderer keeps the node - and its animation - across every update. The four random numbers on each bubble are stable per occurrence, which is what lets a bubble keep its course while new ones arrive behind it.

```html
<div class="ol-bubbles look-[[[c:look]]] dir-[[[c:direction]]] spawn-[[[c:spawn]]][[[if:c:pop_after > 0]]] popping[[[endif]]]">
  [[[foreach:emotes as e]]]
  <div class="bubble" data-key="[[[e.id]]]" style="--rx: [[[e.x]]]; --ry: [[[e.y]]]; --seed: [[[e.seed]]]; --n: [[[e.n]]];">
    <div class="sway"><div class="skin">[[[e.html]]]</div></div>
  </div>
  [[[endforeach]]]
</div>
```

### `css`

The styles. Every control lands in one custom property on `.ol-bubbles`, except the cap on how many bubbles show, which has to sit inside a selector: the newest N bubbles are the last N children, and `:nth-last-child` is the only thing that can count them.

```css
html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: transparent; overflow: hidden; }

.ol-bubbles {
  --size: [[[c:bubble_size]]]px;
  --tint: [[[c:bubble_color]]];
  --speed: [[[c:speed]]];
  --pop: [[[c:pop_after]]]s;
  --cx: [[[c:spawn_x]]];
  --cy: [[[c:spawn_y]]];
  /* Seconds a bubble takes to cross the whole screen at speed 1; speed 10 is ten times faster. */
  --cross: calc(24s / var(--speed));

  position: fixed;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
}

/* One bubble. Positioned by its centre at (--sx%, --sy%), then carried to the
   edge it floats toward. Hidden by default: the rule after this one shows the
   newest N, and a bubble that has fallen off that window stays hidden. */
.bubble {
  position: absolute;
  display: none;
  width: var(--size);
  height: var(--size);
  margin: calc(var(--size) / -2) 0 0 calc(var(--size) / -2);
  left: calc(var(--sx) * 1%);
  top: calc(var(--sy) * 1% + var(--offset));
  --offabs: 0px;
  --delay: calc(var(--n) * 0.12s);
  --dur: max(0.8s, calc(var(--cross) * var(--edge) / 100));
  --dist: calc(var(--edge) * 1vh + var(--offabs) + var(--size));
  animation: ol-rise var(--dur) linear var(--delay) both;
  will-change: transform;
}
.bubble:nth-last-child(-n + [[[c:max_bubbles ?? 40]]]) { display: block; }

/* Direction. --edge is how far the start point is from the edge the bubble
   floats toward, as a share of the screen height, so a bubble born halfway up
   takes half the time of one born at the bottom. */
.dir-up .bubble { --sign: -1; --edge: var(--sy); --offset: var(--offabs); }
.dir-down .bubble { --sign: 1; --edge: calc(100 - var(--sy)); --offset: calc(-1 * var(--offabs)); }

/* Where a bubble is born. random: anywhere on screen. edge: a random point on
   the edge it starts from. outside: the same, a little past the edge, so it
   floats in. cannon: one point, with a small spread so a burst does not stack. */
.spawn-random .bubble { --sx: var(--rx); --sy: var(--ry); }
.spawn-edge.dir-up .bubble, .spawn-outside.dir-up .bubble { --sx: var(--rx); --sy: 100; }
.spawn-edge.dir-down .bubble, .spawn-outside.dir-down .bubble { --sx: var(--rx); --sy: 0; }
.spawn-outside .bubble { --offabs: calc(var(--size) / 2 + 8px); }
.spawn-cannon .bubble { --sx: clamp(0, calc(var(--cx) + (var(--rx) - 50) / 10), 100); --sy: var(--cy); }

/* Popping: stop with the whole bubble just inside the edge instead of
   carrying on past it, then burst after the pop delay. */
.popping .bubble { --dist: calc(var(--edge) * 1vh + var(--offabs) - var(--size) / 2); }

/* The sideways wobble, on its own element so it never fights the rise. Every
   bubble gets its own period and phase from its seed. */
.sway {
  width: 100%;
  height: 100%;
  animation: ol-sway calc(2.2s + var(--seed) * 0.016s) ease-in-out calc(var(--seed) * -0.04s) infinite alternate;
}

/* The picture: grows in when the bubble is born, bursts when it pops. */
.skin {
  position: relative;
  width: 100%;
  height: 100%;
  animation: ol-in 0.45s cubic-bezier(0.2, 0.9, 0.3, 1.25) var(--delay) both;
}
.popping .skin {
  animation: ol-in 0.45s cubic-bezier(0.2, 0.9, 0.3, 1.25) var(--delay) both, ol-pop 0.32s ease-in calc(var(--delay) + var(--dur) + var(--pop)) forwards;
}
.skin img {
  position: absolute;
  left: 50%;
  top: 50%;
  height: calc(var(--size) * 0.56);
  width: auto;
  max-width: calc(var(--size) * 0.68);
  object-fit: contain;
  transform: translate(-50%, -50%);
}

/* Looks. */
.look-bubble .skin {
  border-radius: 50%;
  background: radial-gradient(circle at 32% 28%, color-mix(in srgb, var(--tint) 45%, transparent), color-mix(in srgb, var(--tint) 6%, transparent) 40%, color-mix(in srgb, var(--tint) 3%, transparent) 62%, color-mix(in srgb, var(--tint) 24%, transparent) 100%);
  box-shadow: inset 0 0 0 1.5px color-mix(in srgb, var(--tint) 78%, transparent), inset -5px -7px 12px color-mix(in srgb, var(--tint) 16%, transparent), 0 2px 10px rgba(0, 0, 0, 0.14);
}
.look-bubble .skin::after {
  content: "";
  position: absolute;
  top: 10%;
  left: 13%;
  width: 30%;
  height: 30%;
  border-radius: 50%;
  border-top: 3px solid color-mix(in srgb, var(--tint) 88%, transparent);
  border-left: 3px solid transparent;
  transform: rotate(-24deg);
}

.look-snow .skin::before {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--tint);
  opacity: 0.92;
  -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cg stroke='%23fff' stroke-width='4.5' stroke-linecap='round' fill='none'%3E%3Cg id='a'%3E%3Cpath d='M50 5v90M50 5l-9 9M50 5l9 9M50 95l-9-9M50 95l9-9M50 27l-8 8M50 27l8 8M50 73l-8-8M50 73l8-8'/%3E%3C/g%3E%3Cuse href='%23a' transform='rotate(60 50 50)'/%3E%3Cuse href='%23a' transform='rotate(120 50 50)'/%3E%3C/g%3E%3C/svg%3E") center / contain no-repeat;
  mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cg stroke='%23fff' stroke-width='4.5' stroke-linecap='round' fill='none'%3E%3Cg id='a'%3E%3Cpath d='M50 5v90M50 5l-9 9M50 5l9 9M50 95l-9-9M50 95l9-9M50 27l-8 8M50 27l8 8M50 73l-8-8M50 73l8-8'/%3E%3C/g%3E%3Cuse href='%23a' transform='rotate(60 50 50)'/%3E%3Cuse href='%23a' transform='rotate(120 50 50)'/%3E%3C/g%3E%3C/svg%3E") center / contain no-repeat;
}
.look-snow .skin img { height: calc(var(--size) * 0.46); max-width: calc(var(--size) * 0.5); }

.look-heart .skin::before,
.look-heart .skin::after {
  content: "";
  position: absolute;
  inset: 0;
  -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath d='M50 90C22 68 6 52 6 33A20 20 0 0 1 50 21A20 20 0 0 1 94 33C94 52 78 68 50 90Z'/%3E%3C/svg%3E") center / contain no-repeat;
  mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath d='M50 90C22 68 6 52 6 33A20 20 0 0 1 50 21A20 20 0 0 1 94 33C94 52 78 68 50 90Z'/%3E%3C/svg%3E") center / contain no-repeat;
}
.look-heart .skin::before { background: color-mix(in srgb, var(--tint) 30%, transparent); }
.look-heart .skin::after {
  background: color-mix(in srgb, var(--tint) 85%, transparent);
  -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath d='M50 90C22 68 6 52 6 33A20 20 0 0 1 50 21A20 20 0 0 1 94 33C94 52 78 68 50 90Z' fill='none' stroke='%23fff' stroke-width='5'/%3E%3C/svg%3E") center / contain no-repeat;
  mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath d='M50 90C22 68 6 52 6 33A20 20 0 0 1 50 21A20 20 0 0 1 94 33C94 52 78 68 50 90Z' fill='none' stroke='%23fff' stroke-width='5'/%3E%3C/svg%3E") center / contain no-repeat;
}
.look-heart .skin img { height: calc(var(--size) * 0.44); max-width: calc(var(--size) * 0.56); transform: translate(-50%, -56%); }

@keyframes ol-rise {
  to { transform: translateY(calc(var(--sign) * var(--dist))); }
}
@keyframes ol-sway {
  from { transform: translateX(calc(var(--size) * -0.28)) rotate(-7deg); }
  to { transform: translateX(calc(var(--size) * 0.28)) rotate(7deg); }
}
@keyframes ol-in {
  from { opacity: 0; transform: scale(0.35); }
  to { opacity: 1; transform: scale(1); }
}
@keyframes ol-pop {
  0% { opacity: 1; transform: scale(1); }
  55% { opacity: 0.9; transform: scale(1.28); }
  100% { opacity: 0; transform: scale(1.5); }
}
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 10 are defined by the overlay itself and are recreated, with the default values shown, for anyone who copies it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:look]]]` | text | Look | `bubble` | yes |
| `[[[c:bubble_size]]]` | number | Bubble size | `96` | yes |
| `[[[c:bubble_color]]]` | color | Bubble color | `#ffffff` | yes |
| `[[[c:speed]]]` | number | Float speed | `4` | yes |
| `[[[c:direction]]]` | text | Direction | `up` | yes |
| `[[[c:max_bubbles]]]` | number | Bubbles on screen at most | `40` | yes |
| `[[[c:pop_after]]]` | number | Pop after | `0` | yes |
| `[[[c:spawn]]]` | text | Spawn | `outside` | yes |
| `[[[c:spawn_x]]]` | number | Spawn X | `50` | yes |
| `[[[c:spawn_y]]]` | number | Spawn Y | `85` | yes |

### Control detail

- `c:look` - What each emote floats in. One of `bubble` (a soap bubble with a shine), `snow` (a snowflake behind the emote) or `heart` (a heart-shaped bubble).
- `c:bubble_size` - Width and height of a bubble in pixels. The emote inside scales with it.
  - min=32, max=240, step=1, reset_value=96, random=false, random_interval=null
- `c:bubble_color` - The color of the bubble's ring and shine, the snowflake, or the heart. White reads as soap; try a pale pink on the heart.
- `c:speed` - How fast a bubble travels, 1 to 10. At 1 a bubble takes 24 seconds to cross the screen, at 10 under three.
  - min=1, max=10, step=1, reset_value=4, random=false, random_interval=null
- `c:direction` - `up` floats bubbles toward the top of the screen, `down` sinks them toward the bottom. The spawn edge follows.
- `c:max_bubbles` - How many bubbles can be on screen at once. The newest ones show; older ones are dropped. A 500-character message can hold about 55 of the shortest emote.
  - min=1, max=100, step=1, reset_value=40, random=false, random_interval=null
- `c:pop_after` - 0 lets bubbles float off the edge of the screen. Any other number makes them gather at that edge and pop after this many seconds.
  - min=0, max=60, step=1, reset_value=0, random=false, random_interval=null
- `c:spawn` - Where a bubble appears. `outside` floats in from just past the edge it starts from, `edge` appears on that edge, `random` appears anywhere on screen, `cannon` appears at the point set by Spawn X and Spawn Y.
- `c:spawn_x` - For the cannon: how far across the screen a bubble appears, 0 at the left edge to 100 at the right.
  - min=0, max=100, step=1, reset_value=50, random=false, random_interval=null
- `c:spawn_y` - For the cannon: how far down the screen a bubble appears, 0 at the top edge to 100 at the bottom.
  - min=0, max=100, step=1, reset_value=85, random=false, random_interval=null

Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in seconds of when it last changed.

## Live data tags used

Beyond its controls, this overlay reads the emotes loop: every emote typed in chat lately, one item per emote occurrence, oldest first. It comes from Twitch directly over the overlay's own anonymous chat connection; nothing passes through Overlabels. A tag with no data renders as nothing.

- `[[[foreach:emotes as e]]]`
- `[[[e.html]]]`
- `[[[e.id]]]`
- `[[[e.n]]]`
- `[[[e.seed]]]`
- `[[[e.x]]]`
- `[[[e.y]]]`
- `[[[endforeach]]]`

## Copying this overlay

Installed by the Chat Emote Bubbles product at <https://overlabels.com/products/chat-emote-bubbles>. The install creates your own editable copy of the source above, along with the ten controls listed under Controls.
