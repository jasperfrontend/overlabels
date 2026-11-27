---
name: "Chat Checkin globe"
type: static
author: Overlabels
---

# Chat Checkin globe

A spinning globe that grows a pin every time a viewer types !checkin with their city. Thanks @TriodeOfficial for the inspiration. Two toggles turn on a HUD with the counts and a list of who checked in this stream. The designer sets the globe's colors, its spin and tilt, the pins, the font and your location.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Fonts and `<style>` blocks. No scripts - they are stripped on save. No font `<link>` here on purpose: `c:font` is declared `webfont=true`, so the overlay loads whichever family that control holds and swaps it live when the control changes.

```html
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
```

### `html`

The markup.

```html
<div class="globe-stage">

  [[[checkin_globe]]]

  [[[if:c:toggle_hud]]]
  <div class="globe-hud">
    <div class="hud-line">
      [[[c:checkin:checkins_total]]] checkin[[[if:c:checkin:checkins_total != 1]]]s[[[endif]]]
      from [[[c:checkin:unique_countries_this_stream]]] countr[[[if:c:checkin:unique_countries_this_stream != 1]]]ies[[[else]]]y[[[endif]]]
    </div>
    <div class="hud-latest">[[[c:checkin:latest_checkin_name]]] checked in last from [[[c:checkin:latest_checkin_place]]]</div>
    [[[if:c:checkin:farthest_checkin_this_stream]]]
    <div class="hud-latest hud-farthest">
      farthest from [[[if:c:hometown]]][[[c:hometown]]][[[else]]]home[[[endif]]]: [[[c:checkin:farthest_checkin_this_stream|distance:km]]]km ([[[c:checkin:farthest_checkin_this_stream|distance:mi]]]mi)
      by [[[c:checkin:farthest_checkin_name_this_stream ?? someone]]]
    </div>
    [[[endif]]]
  </div>
  [[[endif]]]

  [[[if:c:toggle_pinlist]]]
  <div class="pin-list">
    [[[foreach:checkins as pin]]]
    <div class="pin-row">[[[pin.name]]] <span>[[[pin.place]]]</span></div>
    [[[endforeach]]]
  </div>
  [[[endif]]]
</div>
```

### `css`

The stylesheet. Tags work in here too.

```css
:root {
  --land: [[[c:land_color]]];
  --land-size: [[[c:land_dot_size]]];
  --land-tones: [[[c:land_tones]]];
  --globe: [[[c:globe_color]]];
  --globe-alpha: [[[c:globe_opacity]]];
  --spin: [[[c:spin_seconds]]];
  --tilt: [[[c:tilt]]];
  --pin: [[[c:pin_color]]];
  --label: [[[c:label_color]]];
  --label-size: [[[c:label_size]]]px;
  --font: [[[c:font]]];
  --bold: [[[c:font_bold]]];
  --text: [[[c:text_color]]];
  --accent: [[[c:accent_color]]];
}
body { background: transparent; margin: 0; }
.globe-stage { position: relative; width: 100vw; height: 100vh; font-family: var(--font), ui-monospace, monospace; font-weight: calc(400 + 300 * var(--bold)); }
/* the globe reads these off its own element twice a second, so a designer
   change lands without reloading the browser source */
.ol-checkin-globe {
  position: absolute; inset: 0;
  --globe-shell-color: var(--globe);
  --globe-shell-opacity: var(--globe-alpha);
  --globe-dot-color: var(--land);
  --globe-dot-size: var(--land-size);
  --globe-dot-tones: var(--land-tones);
  --globe-pin-color: var(--pin);
  --globe-rotation-seconds: var(--spin);
  --globe-tilt-degrees: var(--tilt);
}
.ol-globe-label { color: var(--label); font-size: var(--label-size); letter-spacing: 0.08em; text-shadow: 1px 0 0 #000, -1px 0 0 #000, 0 1px 0 #000, 0 -1px 0 #000, 1px 1px 0 #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000; transition: opacity 0.3s; }
.globe-hud { position: absolute; top: 24px; left: 24px; color: var(--accent); }
.hud-latest { color: var(--text); margin-top: 4px; }
.pin-list { position: absolute; right: 24px; top: 24px; color: var(--text); font-size: 12px; }
.pin-row span { color: var(--accent); }
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 17 are defined by the overlay itself and are recreated, with the default values shown, for anyone who installs it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:land_color]]]` | color | Land color | `#4caf50` | yes |
| `[[[c:land_dot_size]]]` | number | Land dot size | `5` | yes |
| `[[[c:land_tones]]]` | boolean | Mixed land tones | `0` | yes |
| `[[[c:globe_color]]]` | color | Globe color | `#0b3d91` | yes |
| `[[[c:globe_opacity]]]` | number | Globe opacity | `0.92` | yes |
| `[[[c:spin_seconds]]]` | number | Seconds per turn | `60` | yes |
| `[[[c:tilt]]]` | number | Tilt | `18` | yes |
| `[[[c:pin_color]]]` | color | Pin color | `#ffffff` | yes |
| `[[[c:label_color]]]` | color | Name color | `#ffffff` | yes |
| `[[[c:label_size]]]` | number | Name size | `14` | yes |
| `[[[c:font]]]` | text | Font | `Albert Sans` | yes |
| `[[[c:font_bold]]]` | boolean | Bold text | `0` | yes |
| `[[[c:text_color]]]` | color | Text color | `#e8f1ff` | yes |
| `[[[c:accent_color]]]` | color | Accent color | `#7cc4ff` | yes |
| `[[[c:hometown]]]` | text | Your location |  | yes |
| `[[[c:toggle_hud]]]` | boolean | Toggle HUD | `0` | yes |
| `[[[c:toggle_pinlist]]]` | boolean | Toggle pin list | `0` | yes |

### Control detail

- `c:land_color` - The color of the dots that draw the continents.
- `c:land_dot_size` - How big each continent tile is. 10 and the tiles nearly touch, 5 and they are half that wide.
  - min=1, max=10, step=0.5, reset_value=5, random=false, random_interval=null
- `c:land_tones` - Gives every continent tile one of three tones of the land color: the color itself, a little lighter or a little darker.
  - min=null, max=null, step=1, reset_value=0, random=false, random_interval=null
- `c:globe_color` - The color of the globe itself, under the continents.
- `c:globe_opacity` - How solid the globe is. 1 is fully solid; lower lets the far side of the world show through faintly.
  - min=0, max=1, step=0.05, reset_value=0.92, random=false, random_interval=null
- `c:spin_seconds` - How many seconds one full turn takes. 0 stops the globe.
  - min=0, max=240, step=5, reset_value=60, random=false, random_interval=null
- `c:tilt` - How far the top of the globe leans toward you, in degrees.
  - min=-40, max=40, step=1, reset_value=18, random=false, random_interval=null
- `c:pin_color` - The pins that stick out where viewers checked in.
- `c:label_color` - The viewer names floating next to the pins.
- `c:label_size` - The size of the viewer names next to the pins, in pixels.
  - min=9, max=32, step=1, reset_value=14, random=false, random_interval=null
- `c:font` - The font for the names, the HUD and the pin list, any family Bunny Fonts serves. The overlay loads it itself.
  - webfont=true
- `c:font_bold` - Shows the names, the HUD and the pin list in bold.
  - min=null, max=null, step=1, reset_value=0, random=false, random_interval=null
- `c:text_color` - The HUD's detail lines and the pin list.
- `c:accent_color` - The HUD's headline and the places in the pin list.
- `c:hometown` - Where you stream from, as City, country code - like Rotterdam, NL. The farthest checkin is measured from here and the HUD names it. Leave it empty to measure nothing.
- `c:toggle_hud` - Shows the HUD: total checkins, countries this stream, the latest checkin and the farthest one.
  - min=null, max=null, step=1, reset_value=0, random=false, random_interval=null
- `c:toggle_pinlist` - Shows the list of viewers who used !checkin this stream.
  - min=null, max=null, step=1, reset_value=0, random=false, random_interval=null

Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in seconds of when it last changed.

## Requirements

This overlay reads live data from: **Chat Checkin**. The product install connects it for you.
