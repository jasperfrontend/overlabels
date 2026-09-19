---
name: Twitch Chat
type: static
author: Overlabels
---

# Twitch Chat

Your chat on stream, read straight from Twitch: names in their Twitch colors, badges, Twitch and third-party emotes, a chip on a viewer's first ever message. Thirteen controls set the skin, the font, the colors, the layout and how long a message stays, and every change lands in OBS the moment it is made. Ten skins are built in; a preset picks one and a palette to go with it.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Fonts and `<style>` blocks. No scripts - they are stripped on save.

```html
<meta name="generator" content="Overlabels twitch-chat-overlay">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@400;700&family=Inter:wght@400;700&family=Space+Grotesk:wght@400;700&family=Fredoka:wght@400;700&family=JetBrains+Mono:wght@400;700&family=Silkscreen:wght@400;700&display=swap" rel="stylesheet">
```

### `html`

The markup.

```html
<div class="ol-chat skin-[[[c:skin]]] layout-[[[c:layout]]] bg-[[[c:background]]][[[if:c:lifetime > 0]]] fading[[[endif]]]">
  [[[foreach:chat as msg]]]
  <div class="msg[[[if:msg.broadcaster]]] is-broadcaster[[[endif]]][[[if:msg.first]]] is-first[[[endif]]]" data-key="[[[msg.id]]]">
    [[[if:c:show_badges]]]<span class="badges">[[[msg.badge_images]]]</span>[[[endif]]]
    <span class="name"[[[if:c:twitch_colors]]] style="color: [[[msg.color]]]"[[[endif]]]>[[[msg.author]]]</span>
    [[[if:msg.first]]]<span class="chip">first message</span>[[[endif]]]
    [[[if:msg.source_channel]]]<span class="chip">via [[[msg.source_channel]]]</span>[[[endif]]]
    <span class="body">[[[msg.html]]]</span>
  </div>
  [[[endforeach]]]
</div>
```

### `css`

The styles. Every control lands in one custom property on `.ol-chat`, so the rules below never read a tag themselves and this block keeps the compiled-bindings fast path: no `if` or `foreach` in here.

```css
html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: transparent; overflow: hidden; }

.ol-chat {
  --font: [[[c:font]]];
  --size: [[[c:font_size]]]px;
  --name: [[[c:name_color]]];
  --text: [[[c:text_color]]];
  --accent: [[[c:accent]]];
  --bg: [[[c:background_color]]];
  --life: [[[c:lifetime]]]s;
  --emote: [[[c:emote_size]]]px;

  box-sizing: border-box;
  width: 100%;
  height: 100%;
  padding: 16px;
  display: flex;
  gap: 6px;
  overflow: hidden;
  font-family: var(--font), "Albert Sans", system-ui, sans-serif;
  font-size: var(--size);
  line-height: 1.35;
  color: var(--text);
}

/* Layouts. The DOM order is always oldest first, which is what the feed hands over.
   Each one packs toward the edge the newest message should sit on, so the overflow
   of a full window spills off the OTHER edge: flex-end in a column packs at the
   bottom, flex-end in a column-reverse packs at the top, flex-end in a row packs
   at the right. Packing the other way puts the newest message off screen. */
.layout-bottom { flex-direction: column; justify-content: flex-end; }
.layout-top { flex-direction: column-reverse; justify-content: flex-end; }
.layout-ticker { flex-direction: row; align-items: center; justify-content: flex-end; }

.msg {
  flex: 0 0 auto;
  max-width: 100%;
  padding: 0.3em 0.6em;
  border-radius: 0.35em;
  overflow-wrap: anywhere;
  animation: ol-chat-in 0.28s ease-out both;
}
.layout-ticker .msg { white-space: nowrap; overflow-wrap: normal; }

/* Backgrounds. */
.bg-none .msg { background: transparent; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.85); }
.bg-solid .msg { background: var(--bg); }
.bg-glass .msg { background: color-mix(in srgb, var(--bg) 72%, transparent); backdrop-filter: blur(8px); }

/* Message parts. */
.badges { display: inline; margin-right: 0.25em; }
.badges img { height: 1em; width: auto; vertical-align: -0.15em; margin-right: 0.15em; }
.name { font-weight: 700; color: var(--name); }
.name::after { content: ":"; color: var(--text); font-weight: 400; margin-right: 0.35em; }
.body { color: var(--text); }
.body img { height: var(--emote); width: auto; vertical-align: middle; }

.chip {
  display: inline-block;
  vertical-align: 0.15em;
  margin-right: 0.35em;
  padding: 0.05em 0.5em;
  border-radius: 999px;
  background: var(--accent);
  color: #fff;
  font-size: 0.55em;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.is-broadcaster { box-shadow: inset 3px 0 0 var(--accent); }
.bg-none .is-broadcaster { box-shadow: none; }
.bg-none .is-broadcaster .name { text-decoration: underline; text-decoration-color: var(--accent); text-underline-offset: 0.15em; }

/* Skins. Shape, typography and behaviour per look. Every rule is at least
   .skin-x .msg (0,2,0), the same weight as the .bg-x .msg rules above, and
   comes later in the source, so a skin wins where it says something and the
   background rules stand where it does not. `clean` has no rules: it IS the
   base look. */

.skin-terminal .msg { border-radius: 0; padding: 0.15em 0.5em; }
.skin-terminal .name::before { content: "> "; color: var(--accent); font-weight: 400; }
.skin-terminal .name::after { content: " "; margin: 0; }
.skin-terminal .msg:last-child .body::after { content: "_"; margin-left: 0.15em; color: var(--accent); animation: ol-chat-blink 1s steps(2, start) infinite; }

.skin-bubbles { gap: 12px; }
.skin-bubbles .msg { align-self: flex-start; width: fit-content; max-width: 85%; padding: 0.5em 0.9em; border-radius: 1.1em; border-bottom-left-radius: 0.2em; }
.skin-bubbles.bg-solid .msg:nth-child(even), .skin-bubbles.bg-glass .msg:nth-child(even) { background: color-mix(in srgb, var(--bg) 78%, var(--accent)); }
.skin-bubbles .name { font-size: 0.72em; letter-spacing: 0.02em; }
.skin-bubbles .name::after { content: none; }
.skin-bubbles .chip { vertical-align: 0.1em; }
.skin-bubbles .body { display: block; margin-top: 0.1em; }
.skin-bubbles.layout-ticker .msg { align-self: center; }
.skin-bubbles.layout-ticker .body { display: inline; margin: 0; }

.skin-neon .msg { align-self: flex-start; width: fit-content; background: transparent; border: 1px solid color-mix(in srgb, var(--accent) 45%, transparent); border-radius: 0.5em; box-shadow: 0 0 12px color-mix(in srgb, var(--accent) 35%, transparent), inset 0 0 12px color-mix(in srgb, var(--accent) 12%, transparent); text-shadow: none; }
.skin-neon .name { text-shadow: 0 0 6px currentColor, 0 0 14px currentColor; }
.skin-neon .body { text-shadow: 0 0 8px color-mix(in srgb, var(--text) 55%, transparent); }
.skin-neon .chip { box-shadow: 0 0 10px var(--accent); }
.skin-neon.layout-ticker .msg { align-self: center; }

.skin-paper .msg { border-radius: 0.25em; box-shadow: 0 1px 0 rgba(0, 0, 0, 0.12), 0 6px 14px rgba(0, 0, 0, 0.16); text-shadow: none; }
.skin-paper .name { font-weight: 800; }
.skin-paper .is-broadcaster { box-shadow: inset 4px 0 0 var(--accent), 0 6px 14px rgba(0, 0, 0, 0.16); }

.skin-broadcast .msg { border-radius: 0; padding: 0.35em 1em; border-left: 3px solid var(--accent); }
.skin-broadcast .name { text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.8em; }
.skin-broadcast .name::after { content: ""; margin-right: 0.6em; }
.skin-broadcast .badges { display: none; }
.skin-broadcast.layout-ticker { gap: 0; padding: 0 16px; background: var(--bg); }
.skin-broadcast.layout-ticker .msg { border-left-width: 2px; }

.skin-caption { align-items: center; text-align: center; }
.skin-caption .msg { max-width: 82%; background: transparent; font-weight: 600; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.9), 0 0 14px rgba(0, 0, 0, 0.75); }
.skin-caption .name { font-weight: 800; }
.skin-caption .badges { display: none; }
.skin-caption .is-broadcaster { box-shadow: none; }

.skin-pixel .msg { align-self: flex-start; width: fit-content; border-radius: 0; border: 3px solid var(--accent); box-shadow: 4px 4px 0 var(--accent); margin: 0 6px 6px 0; }
.skin-pixel .badges img, .skin-pixel .body img { image-rendering: pixelated; }
.skin-pixel .chip { border-radius: 0; }
.skin-pixel.layout-ticker .msg { align-self: center; }
.skin-pixel .is-broadcaster { box-shadow: 4px 4px 0 var(--accent), inset 0 0 0 2px var(--name); }

.skin-cards { gap: 10px; }
.skin-cards .msg { display: flex; flex-wrap: wrap; align-items: center; padding: 0; overflow: hidden; border-radius: 0.6em; }
.skin-cards .name { order: 0; flex: 1 1 100%; padding: 0.3em 0.8em; background: color-mix(in srgb, currentColor 32%, var(--bg)); }
.skin-cards .name::after { content: none; }
.skin-cards .chip { order: 1; margin: 0.45em 0 0.45em 0.8em; }
.skin-cards .badges { order: 1; margin: 0; padding: 0.45em 0 0.45em 0.8em; }
.skin-cards .body { order: 2; flex: 1 1 0; min-width: 0; padding: 0.45em 0.8em; }
.skin-cards .is-broadcaster { box-shadow: inset 0 0 0 2px var(--accent); }

.skin-vapor .msg { border-radius: 1em; border: 1px solid color-mix(in srgb, var(--accent) 40%, transparent); }
.skin-vapor .name { text-transform: lowercase; letter-spacing: 0.04em; }
.skin-vapor .body img { filter: drop-shadow(0 0 6px color-mix(in srgb, var(--accent) 60%, transparent)); }
.skin-vapor .chip { background: color-mix(in srgb, var(--accent) 70%, var(--name)); }

/* Lifetime. The root only carries .fading when the lifetime control is above zero,
   and the out-animation fills forwards only, so during its delay it applies nothing
   and the in-animation is still what the message shows.
   so a lifetime of 0 keeps every message on screen with no out-animation at all. */
.fading .msg { animation: ol-chat-in 0.28s ease-out both, ol-chat-out 0.5s ease-in var(--life) forwards; }

@keyframes ol-chat-in {
  from { opacity: 0; transform: translateY(0.4em); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes ol-chat-out {
  from { opacity: 1; }
  to { opacity: 0; }
}
@keyframes ol-chat-blink {
  to { visibility: hidden; }
}
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 13 are defined by the overlay itself and are recreated, with the default values shown, for anyone who copies it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:skin]]]` | text | Skin | `clean` | yes |
| `[[[c:layout]]]` | text | Layout | `bottom` | yes |
| `[[[c:font]]]` | text | Font | `Albert Sans` | yes |
| `[[[c:font_size]]]` | number | Font size | `22` | yes |
| `[[[c:twitch_colors]]]` | boolean | Names in their Twitch colors | `1` | yes |
| `[[[c:name_color]]]` | color | Name color | `#ffffff` | yes |
| `[[[c:text_color]]]` | color | Text color | `#ffffff` | yes |
| `[[[c:accent]]]` | color | Accent | `#9146ff` | yes |
| `[[[c:background]]]` | text | Background | `glass` | yes |
| `[[[c:background_color]]]` | color | Background color | `#0f0f14` | yes |
| `[[[c:lifetime]]]` | number | Seconds a message stays | `0` | yes |
| `[[[c:show_badges]]]` | boolean | Show badges | `1` | yes |
| `[[[c:emote_size]]]` | number | Emote size | `28` | yes |

### Control detail

- `c:skin` - The shape and behaviour of a message. One of `clean`, `terminal`, `bubbles`, `neon`, `paper`, `broadcast`, `caption`, `pixel`, `cards` or `vapor`; each is a block of rules in the CSS, and a preset pairs one with a palette.
- `c:layout` - Where new messages go. `bottom` stacks upward with the newest at the bottom, `top` stacks downward with the newest at the top, `ticker` is one horizontal line with the newest at the right.
- `c:font` - The font family. One of Albert Sans, Inter, Space Grotesk, Fredoka, JetBrains Mono or Silkscreen; all six are loaded by the overlay.
- `c:font_size` - Text size in pixels. Badges scale with it; emotes have their own size.
  - min=10, max=72, step=1, reset_value=22, random=false, random_interval=null
- `c:twitch_colors` - Color each name the way the chatter chose it on Twitch. Off, every name uses the Name color control.
  - min=null, max=null, step=1, reset_value=1, random=false, random_interval=null
- `c:name_color` - The name color when Twitch colors are off, and the fallback for a chatter who never picked one.
- `c:text_color` - The message text color.
- `c:accent` - The chip on a viewer's first ever message, and the stripe on your own messages.
- `c:background` - What sits behind each message. `none` for text over the game with a shadow, `solid` for the Background color, `glass` for a translucent, blurred version of it.
- `c:background_color` - The color behind each message for the solid and glass backgrounds.
- `c:lifetime` - How many seconds a message stays before fading out. 0 keeps every message until it scrolls off.
  - min=0, max=600, step=1, reset_value=0, random=false, random_interval=null
- `c:show_badges` - Draw the Twitch badges in front of a name.
  - min=null, max=null, step=1, reset_value=1, random=false, random_interval=null
- `c:emote_size` - Emote height in pixels, for Twitch, 7TV, BTTV and FFZ emotes alike.
  - min=12, max=96, step=1, reset_value=28, random=false, random_interval=null

Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in seconds of when it last changed.

## Live data tags used

Beyond its controls, this overlay reads the chat loop. Every message comes from Twitch directly over the overlay's own anonymous connection; nothing passes through Overlabels. A tag with no data renders as nothing.

- `[[[foreach:chat as msg]]]`
- `[[[msg.author]]]`
- `[[[msg.badge_images]]]`
- `[[[msg.broadcaster]]]`
- `[[[msg.color]]]`
- `[[[msg.first]]]`
- `[[[msg.html]]]`
- `[[[msg.id]]]`
- `[[[msg.source_channel]]]`
- `[[[endforeach]]]`

## Copying this overlay

Installed by the Twitch Chat product at <https://overlabels.com/products/twitch-chat-overlay>. The install creates your own editable copy of the source above, along with the thirteen controls listed under Controls.
