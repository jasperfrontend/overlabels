---
name: "Chat Checkin globe"
type: static
author: Overlabels
---

# Chat Checkin globe

A spinning globe that grows a pin every time a viewer types !checkin with their city. Thanks @TriodeOfficial for the inspiration. Two toggles turn on a HUD with the counts and a list of who checked in this stream.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Empty.

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
      farthest from home: [[[c:checkin:farthest_checkin_this_stream|distance:km]]]km ([[[c:checkin:farthest_checkin_this_stream|distance:mi]]]mi)
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
body { background: transparent; margin: 0; }
.globe-stage { position: relative; width: 100vw; height: 100vh; font-family: monospace; }
.ol-checkin-globe {
  position: absolute; inset: 0;
  --globe-shell-color: #000;
  --globe-shell-opacity: 0.5;
  --globe-dot-color: #2dd4bf;
  --globe-pin-color: #bfe3e0;
  --globe-rotation-seconds: 30;
  --globe-dot-size: 5;
}
.ol-globe-label { color: #dfe8e8; font-size: 13px; letter-spacing: 0.08em; text-shadow: 0 0 6px #000; transition: opacity 0.3s; }
.globe-hud { position: absolute; top: 24px; left: 24px; color: #1daaff; }
.hud-latest { color: #8fb6b3; margin-top: 4px; }
.pin-list { position: absolute; right: 24px; top: 24px; color: #6f8f8c; font-size: 12px; }
.pin-row span { color: #1daaff; }
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 2 are defined by the overlay itself and are recreated, with the default values shown, for anyone who installs it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:toggle_hud]]]` | boolean | Toggle HUD | `0` | yes |
| `[[[c:toggle_pinlist]]]` | boolean | Toggle pin list | `0` | yes |

### Control detail

- `c:toggle_hud` - Shows the HUD: total checkins, countries this stream, the latest checkin and the farthest one.
- `c:toggle_pinlist` - Shows the list of viewers who used !checkin this stream.

Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in seconds of when it last changed.

## Requirements

This overlay reads live data from: **Chat Checkin**. The product install connects it for you.
