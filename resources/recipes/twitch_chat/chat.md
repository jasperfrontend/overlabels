---
name: Twitch Chat
type: static
author: Overlabels
---

# Twitch Chat

Your chat on stream, read straight from Twitch: names in their Twitch colours, badges, Twitch and third-party emotes, a chip on a viewer's first ever message. Twelve controls set the font, the colours, the layout and how long a message stays, and every change lands in OBS the moment it is made.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Fonts and `<style>` blocks. No scripts - they are stripped on save.

```html
<meta name="generator" content="Overlabels twitch_chat">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@400;700&family=Inter:wght@400;700&family=Space+Grotesk:wght@400;700&family=Fredoka:wght@400;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
```

### `html`

The markup.

```html
<div class="ol-chat layout-[[[c:layout]]] bg-[[[c:background]]][[[if:c:lifetime > 0]]] fading[[[endif]]]">
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
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 12 are defined by the overlay itself and are recreated, with the default values shown, for anyone who copies it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:layout]]]` | text | Layout | `bottom` | yes |
| `[[[c:font]]]` | text | Font | `Albert Sans` | yes |
| `[[[c:font_size]]]` | number | Font size | `22` | yes |
| `[[[c:twitch_colors]]]` | boolean | Names in their Twitch colours | `1` | yes |
| `[[[c:name_color]]]` | color | Name colour | `#ffffff` | yes |
| `[[[c:text_color]]]` | color | Text colour | `#ffffff` | yes |
| `[[[c:accent]]]` | color | Accent | `#9146ff` | yes |
| `[[[c:background]]]` | text | Background | `glass` | yes |
| `[[[c:background_color]]]` | color | Background colour | `#0f0f14` | yes |
| `[[[c:lifetime]]]` | number | Seconds a message stays | `0` | yes |
| `[[[c:show_badges]]]` | boolean | Show badges | `1` | yes |
| `[[[c:emote_size]]]` | number | Emote size | `28` | yes |

### Control detail

- `c:layout` - Where new messages go. `bottom` stacks upward with the newest at the bottom, `top` stacks downward with the newest at the top, `ticker` is one horizontal line with the newest at the right.
- `c:font` - The font family. One of Albert Sans, Inter, Space Grotesk, Fredoka or JetBrains Mono; all five are loaded by the overlay.
- `c:font_size` - Text size in pixels. Badges scale with it; emotes have their own size.
  - min=10, max=72, step=1, reset_value=22, random=false, random_interval=null
- `c:twitch_colors` - Colour each name the way the chatter chose it on Twitch. Off, every name uses the Name colour control.
  - min=null, max=null, step=1, reset_value=1, random=false, random_interval=null
- `c:name_color` - The name colour when Twitch colours are off, and the fallback for a chatter who never picked one.
- `c:text_color` - The message text colour.
- `c:accent` - The chip on a viewer's first ever message, and the stripe on your own messages.
- `c:background` - What sits behind each message. `none` for text over the game with a shadow, `solid` for the Background colour, `glass` for a translucent, blurred version of it.
- `c:background_color` - The colour behind each message for the solid and glass backgrounds.
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

Installed by the Twitch Chat product at <https://overlabels.com/products/twitch_chat>. The install creates your own editable copy of the source above, along with the twelve controls listed under Controls.
