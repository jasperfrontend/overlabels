---
name: Snow Overlay
type: static
author: Overlabels
---

# Snow Overlay

Snow falling over your stream. Every flake falls at its own speed and drifts a little on its own rhythm, near flakes big and quick, far ones small and slow. Nine controls set the look: round flakes, square pixel flakes or real six-armed snowflake crystals, a size and a two-color gradient, how many flakes, how fast they fall, and a transparent or colored background. Every change lands in OBS the moment it is made.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Fonts and `<style>` blocks. No scripts - they are stripped on save. Nothing to load here: the overlay draws snow, not text.

```html
<meta name="generator" content="Overlabels snow-overlay">
```

### `html`

The markup. `[[[snow_scene]]]` is the snow itself: the overlay draws it, and the CSS custom properties on `.ol-snow` set it. The class on the stage switches the background on or off.

```html
<div class="ol-snow-stage bg-[[[c:background]]]">
  [[[snow_scene]]]
</div>
```

### `css`

The styles. Every snow control lands in one custom property on `.ol-snow`, which the snow reads twice a second.

```css
html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: transparent; overflow: hidden; }

.ol-snow-stage {
  position: fixed;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
}

.ol-snow-stage.bg-color { background: [[[c:background_color]]]; }

.ol-snow {
  /* round, square or snowflake */
  --snow-shape: [[[c:shape]]];
  /* How many flakes are on screen at once. */
  --snow-count: [[[c:flake_count]]];
  /* Percent of each flake's own speed and size: every flake keeps its own,
     these scale them all. */
  --snow-speed: [[[c:speed]]];
  --snow-size: [[[c:flake_size]]];
  /* The gradient across every flake: the first color at the top at 0 degrees,
     turned clockwise by the angle, the same as Chat Emote Bubbles. */
  --snow-color: [[[c:flake_color]]];
  --snow-color-2: [[[c:flake_color_2]]];
  --snow-angle: [[[c:gradient_angle]]];

  position: absolute;
  inset: 0;
}
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 9 are defined by the overlay itself and are recreated, with the default values shown, for anyone who copies it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:shape]]]` | text | Flake shape | `round` | yes |
| `[[[c:flake_size]]]` | number | Flake size | `100` | yes |
| `[[[c:flake_color]]]` | color | Flake color | `#ffffff` | yes |
| `[[[c:flake_color_2]]]` | color | Second color | `#ffffff` | yes |
| `[[[c:gradient_angle]]]` | number | Gradient angle | `0` | yes |
| `[[[c:flake_count]]]` | number | Amount of snow | `250` | yes |
| `[[[c:speed]]]` | number | Fall speed | `100` | yes |
| `[[[c:background]]]` | text | Background | `transparent` | yes |
| `[[[c:background_color]]]` | color | Background color | `#0b1626` | yes |

### Control detail

- `c:shape` - What each flake looks like. `round` is a soft round flake, `square` a crisp square pixel, `snowflake` a real six-armed snowflake crystal that turns slowly as it falls, sixteen different ones.
  - choices=[{"value":"round","label":"Round","hint":"A soft round flake."},{"value":"square","label":"Square","hint":"A crisp square, like pixel art."},{"value":"snowflake","label":"Snowflake","hint":"A six-armed snowflake crystal that turns as it falls."}]
- `c:flake_size` - How big the flakes are, in percent. Every flake keeps its own size, near ones bigger than far ones; this scales them all. 100 is as designed.
  - min=25, max=400, step=5, reset_value=100, random=false, random_interval=null
- `c:flake_color` - The color of every flake, where the gradient starts. White reads as snow; try a pale blue.
- `c:flake_color_2` - Where the gradient across each flake ends. The same as Flake color gives one plain color.
- `c:gradient_angle` - Turns the gradient, in degrees. 0 runs from Flake color at the top to Second color at the bottom, 90 runs right to left, 180 bottom to top, 270 left to right.
  - min=0, max=360, step=1, reset_value=0, random=false, random_interval=null, angle_colors=["flake_color","flake_color_2"]
- `c:flake_count` - How many flakes are on screen at once, from a few to a blizzard.
  - min=10, max=3000, step=10, reset_value=250, random=false, random_interval=null
- `c:speed` - How fast the snow falls, in percent. Every flake keeps its own speed, so slow snow still looks like snow; this scales them all. 100 crosses the screen in about eleven seconds.
  - min=10, max=300, step=5, reset_value=100, random=false, random_interval=null
- `c:background` - `transparent` lays the snow over whatever is below it in OBS, `color` paints Background color behind it.
  - choices=[{"value":"transparent","label":"Transparent","hint":"Snow over whatever is below it in OBS."},{"value":"color","label":"Color","hint":"Snow over Background color."}]
- `c:background_color` - Behind the snow, when Background is Color.
  - enabled_if=["c:background = color"]

Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in seconds of when it last changed.

## Live data tags used

- `[[[snow_scene]]]` - the snow itself. The overlay draws it, and the CSS custom properties on `.ol-snow` set the shape, the amount, the speed, the size and the colors. It reads nothing from your stream or chat.

## Copying this overlay

Installed by the Snow Overlay for OBS product at <https://overlabels.com/products/snow-overlay>. The install creates your own editable copy of the source above, along with the nine controls listed under Controls.
