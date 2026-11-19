---
name: Chat Emote Bubbles
type: static
author: Overlabels
---

# Chat Emote Bubbles

Every emote your chat types floats across the stream in a soap bubble, one bubble per emote, in the order they were typed. Sixteen controls set the look (bubble, snowflake, heart, one of twenty shapes, or plain), the size, the two colors and the angle of the gradient between them, a shape's border, fill and breathing room, the speed, the direction, how many bubbles can be on screen at once, where they appear and whether they pop at the edge or float off it. Every change lands in OBS the moment it is made.

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

The markup. One element per emote occurrence, keyed by the occurrence id so the renderer keeps the node - and its animation - across every update. The four random numbers on each bubble are stable per occurrence, which is what lets a bubble keep its course while new ones arrive behind it. The SVG before the loop draws nothing itself. It holds the twenty drawings of the Shape look and the one mask every bubble is cut out with: the chosen shape, filled at the fill strength, plus its border. The border is drawn at twice its width and clipped by the shape, so only the inner half shows and the outline never moves. It sits before the loop so it never counts as one of the newest bubbles.

```html
<div class="ol-bubbles look-[[[c:look]]] shape-[[[c:shape]]] dir-[[[c:direction]]] spawn-[[[c:spawn]]][[[if:c:pop_after > 0]]] popping[[[endif]]]">
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
  --tint2: [[[c:bubble_color_2]]];
  /* The gradient runs from the first color at the top to the second at the
     bottom, and the angle turns that clockwise: 90 runs right to left, 180
     bottom to top. CSS's own 0deg points up, hence the 180deg. */
  --flow: calc(180deg + [[[c:gradient_angle]]]deg);
  --fill: linear-gradient(var(--flow), var(--tint), var(--tint2));
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
  --dist: calc(var(--edge) * 1vh + var(--offabs) + var(--reach));
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
.spawn-outside .bubble { --offabs: calc(var(--lead) + 8px); }
.spawn-cannon .bubble { --sx: clamp(0, calc(var(--cx) + (var(--rx) - 50) / 10), 100); --sy: var(--cy); }

/* Popping: stop with the whole bubble just inside the edge instead of
   carrying on past it, then burst after the pop delay. */
.popping .bubble { --dist: calc(var(--edge) * 1vh + var(--offabs) - var(--lead)); }

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

/* Looks. Each one paints the two-color gradient and cuts it to shape with a
   mask, because a gradient cannot be mixed, shadowed or bordered the way a
   single color can. */
.look-bubble .skin {
  border-radius: 50%;
  box-shadow: inset -5px -7px 12px color-mix(in srgb, color-mix(in srgb, var(--tint), var(--tint2)) 16%, transparent), 0 2px 10px rgba(0, 0, 0, 0.14);
}
.look-bubble .skin::before,
.look-bubble .skin::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: var(--fill);
}
/* The film: strongest at the shine, thin across the middle, thicker at the rim. */
.look-bubble .skin::before {
  -webkit-mask: radial-gradient(circle at 32% 28%, rgb(0 0 0 / 0.45), rgb(0 0 0 / 0.06) 40%, rgb(0 0 0 / 0.03) 62%, rgb(0 0 0 / 0.24) 100%);
  mask: radial-gradient(circle at 32% 28%, rgb(0 0 0 / 0.45), rgb(0 0 0 / 0.06) 40%, rgb(0 0 0 / 0.03) 62%, rgb(0 0 0 / 0.24) 100%);
}
/* The ring. */
.look-bubble .skin::after {
  opacity: 0.78;
  -webkit-mask: radial-gradient(closest-side, transparent calc(100% - 2px), #000 calc(100% - 0.5px));
  mask: radial-gradient(closest-side, transparent calc(100% - 2px), #000 calc(100% - 0.5px));
}
.look-snow .skin::before {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--fill);
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
.look-heart .skin::before { background: var(--fill); opacity: 0.3; }
.look-heart .skin::after {
  background: var(--fill);
  opacity: 0.85;
  -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath d='M50 90C22 68 6 52 6 33A20 20 0 0 1 50 21A20 20 0 0 1 94 33C94 52 78 68 50 90Z' fill='none' stroke='%23fff' stroke-width='5'/%3E%3C/svg%3E") center / contain no-repeat;
  mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath d='M50 90C22 68 6 52 6 33A20 20 0 0 1 50 21A20 20 0 0 1 94 33C94 52 78 68 50 90Z' fill='none' stroke='%23fff' stroke-width='5'/%3E%3C/svg%3E") center / contain no-repeat;
}
.look-heart .skin img { height: calc(var(--size) * 0.44); max-width: calc(var(--size) * 0.56); transform: translate(-50%, -56%); }

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

/* Plain: no shape at all, so the emote fills the box the bubble would have. */
.look-plain .skin img { height: calc(var(--size) * 0.8); max-width: var(--size); }

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

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 16 are defined by the overlay itself and are recreated, with the default values shown, for anyone who copies it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:look]]]` | text | Look | `bubble` | yes |
| `[[[c:shape]]]` | text | Shape | `ghost` | yes |
| `[[[c:bubble_size]]]` | number | Bubble size | `96` | yes |
| `[[[c:bubble_color]]]` | color | Bubble color | `#ffffff` | yes |
| `[[[c:bubble_color_2]]]` | color | Second color | `#ffffff` | yes |
| `[[[c:gradient_angle]]]` | number | Gradient angle | `0` | yes |
| `[[[c:border_width]]]` | number | Border width | `3` | yes |
| `[[[c:fill_strength]]]` | number | Fill strength | `30` | yes |
| `[[[c:breathing_room]]]` | number | Breathing room | `5` | yes |
| `[[[c:speed]]]` | number | Float speed | `4` | yes |
| `[[[c:direction]]]` | text | Direction | `up` | yes |
| `[[[c:max_bubbles]]]` | number | Bubbles on screen at most | `40` | yes |
| `[[[c:pop_after]]]` | number | Pop after | `0` | yes |
| `[[[c:spawn]]]` | text | Spawn | `outside` | yes |
| `[[[c:spawn_x]]]` | number | Spawn X | `50` | yes |
| `[[[c:spawn_y]]]` | number | Spawn Y | `85` | yes |

### Control detail

- `c:look` - What each emote floats in. One of `bubble` (a soap bubble), `snow` (a snowflake behind the emote), `heart` (a heart-shaped bubble), `shape` (one of twenty shapes, picked under Shape) or `plain` (no bubble, just the emote).
  - choices=[{"value":"bubble","label":"Bubble","hint":"A soap bubble."},{"value":"snow","label":"Snowflake","hint":"A snowflake behind the emote."},{"value":"heart","label":"Heart","hint":"A heart-shaped bubble."},{"value":"shape","label":"Choose another shape","hint":"One of twenty shapes, picked under Shape."},{"value":"plain","label":"Plain","hint":"No bubble, just the emote."}]
- `c:shape` - For the Shape look: which shape each emote floats in. The emote is the same size in every shape; the shape grows around it.
  - choices=[{"value":"ghost","label":"Ghost","hint":"A little ghost with a wavy hem."},{"value":"cat","label":"Cat head","hint":"A cat's head with two pointed ears."},{"value":"bauble","label":"Christmas bauble","hint":"A round bauble with its cap on."},{"value":"drop","label":"Water drop","hint":"A drop, point up."},{"value":"starburst","label":"Starburst badge","hint":"A badge with twelve short points."},{"value":"octagram","label":"Octagram","hint":"An eight-pointed star."},{"value":"hexagon","label":"Hexagon","hint":"Six sides, point up."},{"value":"octagon","label":"Octagon","hint":"Eight sides, like a stop sign."},{"value":"squircle","label":"Squircle","hint":"A square with soft round corners."},{"value":"paw","label":"Paw print","hint":"A paw pad with four toes."},{"value":"flame","label":"Flame","hint":"A flickering flame."},{"value":"decagram","label":"Decagram","hint":"A ten-pointed star."},{"value":"heptagon","label":"Heptagon","hint":"Seven sides."},{"value":"pentagon","label":"Pentagon","hint":"Five sides, point up."},{"value":"hexagram","label":"Hexagram","hint":"A six-pointed star."},{"value":"decagon","label":"Decagon","hint":"Ten sides, almost round."},{"value":"diamond","label":"Diamond","hint":"A tall diamond."},{"value":"star","label":"Star","hint":"A five-pointed star."},{"value":"triangle","label":"Triangle","hint":"Three sides, point up."},{"value":"dodecagon","label":"Dodecagon","hint":"Twelve sides, nearly a circle."}], enabled_if=["c:look = shape"]
- `c:bubble_size` - Width and height of a bubble in pixels. The emote inside scales with it. On the Shape look it sets the emote's size, and the shape grows around it.
  - min=32, max=240, step=1, reset_value=96, random=false, random_interval=null
- `c:bubble_color` - The color of the bubble's ring and glow, the snowflake, the heart or the shape, where the gradient starts. White reads as soap; try a pale pink on the heart.
  - enabled_if=["c:look != plain"]
- `c:bubble_color_2` - Where the gradient ends. The same as Bubble color gives one plain color.
  - enabled_if=["c:look != plain"]
- `c:gradient_angle` - Turns the gradient, in degrees. 0 runs from Bubble color at the top to Second color at the bottom, 90 runs right to left, 180 bottom to top, 270 left to right.
  - min=0, max=360, step=1, reset_value=0, random=false, random_interval=null, enabled_if=["c:look != plain"], angle_colors=["bubble_color","bubble_color_2"]
- `c:border_width` - For the Shape look: how thick the shape's outline is, in pixels. The shape grows with it, so the emote keeps its breathing room. 0 draws no outline.
  - min=0, max=8, step=0.5, reset_value=3, random=false, random_interval=null, enabled_if=["c:look = shape"]
- `c:fill_strength` - For the Shape look: how strongly the gradient fills the inside of the shape, from 0 (see-through, just the outline) to 100 (solid). The outline is always at full strength.
  - min=0, max=100, step=1, reset_value=30, random=false, random_interval=null, enabled_if=["c:look = shape"]
- `c:breathing_room` - For the Shape look: the space in pixels between the emote and the inside of the outline.
  - min=0, max=12, step=1, reset_value=5, random=false, random_interval=null, enabled_if=["c:look = shape"]
- `c:speed` - How fast a bubble travels, 1 to 10. At 1 a bubble takes 24 seconds to cross the screen, at 10 under three.
  - min=1, max=10, step=1, reset_value=4, random=false, random_interval=null
- `c:direction` - `up` floats bubbles toward the top of the screen, `down` sinks them toward the bottom. The spawn edge follows.
  - choices=[{"value":"up","label":"Up","hint":"Floats toward the top of the screen."},{"value":"down","label":"Down","hint":"Sinks toward the bottom. The spawn edge follows."}]
- `c:max_bubbles` - How many bubbles can be on screen at once. The newest ones show; older ones are dropped. A 500-character message can hold about 55 of the shortest emote.
  - min=1, max=100, step=1, reset_value=40, random=false, random_interval=null
- `c:pop_after` - 0 lets bubbles float off the edge of the screen. Any other number makes them gather at that edge and pop after this many seconds.
  - min=0, max=60, step=1, reset_value=0, random=false, random_interval=null
- `c:spawn` - Where a bubble appears. `outside` floats in from just past the edge it starts from, `edge` appears on that edge, `random` appears anywhere on screen, `cannon` appears at the point set by Spawn X and Spawn Y.
  - choices=[{"value":"outside","label":"Outside","hint":"Floats in from just past the edge it starts from."},{"value":"edge","label":"Edge","hint":"Appears on that edge."},{"value":"random","label":"Random","hint":"Appears anywhere on screen."},{"value":"cannon","label":"Cannon","hint":"Appears at the point set by Spawn X and Spawn Y."}]
- `c:spawn_x` - For the cannon: how far across the screen a bubble appears, 0 at the left edge to 100 at the right.
  - min=0, max=100, step=1, reset_value=50, random=false, random_interval=null, enabled_if=["c:spawn = cannon"]
- `c:spawn_y` - For the cannon: how far down the screen a bubble appears, 0 at the top edge to 100 at the bottom.
  - min=0, max=100, step=1, reset_value=85, random=false, random_interval=null, enabled_if=["c:spawn = cannon"]

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

Installed by the Chat Emote Bubbles product at <https://overlabels.com/products/chat-emote-bubbles>. The install creates your own editable copy of the source above, along with the sixteen controls listed under Controls.
