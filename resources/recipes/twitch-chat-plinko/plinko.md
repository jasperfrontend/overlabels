---
name: Twitch Chat Plinko
type: static
author: Overlabels
---

# Twitch Chat Plinko

A Plinko board your Twitch chat plays. Every !drop in chat (or the word you pick in your Plinko settings) drops a ball with that chatter's name on it, in the order chat sends them, one ball each at a time. Real bouncing off every peg, 1000 points at the rare edges, a line of who is next and the top scores so far.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Fonts and `<style>` blocks. No scripts - they are stripped on save.

```html
<meta name="generator" content="Overlabels /engine">
<meta name="overlabels:engine" content="v1 2026-11-06 controls:0 sha256:407adc142447">
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link href="https://fonts.bunny.net/css2?family=Albert+Sans:wght@400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
```

### `html`

The markup.

```html
<div data-overlabels-engine="v1" class="stage">
  [[[plinko_board]]]
  <div class="intro">
    <p class="eyebrow">Plinko</p>
    <h1>Type <span class="cmd">![[[plinko_command]]]</span> in chat</h1>
    <p class="sub">Your name rides the ball. One ball each, played in the order chat sends them. The edges are rare and pay 1000.</p>
  </div>
</div>
```

### `css`

The stylesheet. Tags work in here too.

```css
:root {
  /* the palette, set from the designer */
  --ink: [[[c:text_color]]];
  --ink-soft: [[[c:soft_text_color]]];
  --ink-muted: [[[c:muted_color]]];
  --highlight: [[[c:highlight_color]]];
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
}

.stage { position: relative; width: 1920px; height: 1080px; }

/* The board fills the stage; the module centres the pyramid in it. */
.ol-plinko {
  position: absolute;
  inset: 0;
  --plinko-rows: 14;
  --plinko-max-balls: 150;
  --plinko-rate: 10;
  --plinko-peg-color: var(--ink-muted);
  --plinko-accent: var(--highlight);
  --plinko-ball-color: var(--highlight);
  --plinko-label-bg: var(--field-deep);
  --plinko-label-color: var(--ink);
  --plinko-slot-color: var(--ink-muted);
  --plinko-font: "Albert Sans", system-ui, sans-serif;
}

/* ---------- left: how to play, then who is next ---------- */
.intro {
  position: absolute;
  left: 64px;
  top: 64px;
  width: 380px;
}
.eyebrow {
  font-family: var(--mono);
  font-size: 16px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-muted);
}
h1 { margin-top: 12px; font-size: 56px; line-height: 1.05; font-weight: 800; }
.cmd { font-family: var(--mono); color: var(--highlight); white-space: nowrap; }
.sub { margin-top: 20px; font-size: 21px; line-height: 1.5; color: var(--ink-soft); }

/* ---------- the module's scoreboard ---------- */
.ol-plinko-hud { position: absolute; inset: 0; pointer-events: none; }

.ol-plinko-count {
  position: absolute;
  left: 64px;
  bottom: 64px;
  width: 360px;
  font-family: var(--mono);
  font-size: 18px;
  color: var(--ink-muted);
}
.ol-plinko-count b { color: var(--ink); font-weight: 600; }

/* Names are never cut or wrapped: a column grows to fit the longest one. */
.ol-plinko-hud ol { position: absolute; min-width: 360px; width: max-content; list-style: none; }
.ol-plinko-hud ol::before {
  display: block;
  margin-bottom: 10px;
  font-family: var(--mono);
  font-size: 15px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-muted);
}
.ol-plinko-hud li {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 8px 0;
  border-top: 1px solid var(--line);
  font-size: 19px;
  font-weight: 600;
  white-space: nowrap;
}
.ol-plinko-hud li:last-child { border-bottom: 1px solid var(--line); }
.ol-plinko-points { font-family: var(--mono); color: var(--highlight); }

.ol-plinko-next { left: 64px; top: 420px; }
.ol-plinko-next::before { content: "Next up"; }
.ol-plinko-next:empty { display: none; }

.ol-plinko-recent { right: 64px; top: 64px; }
.ol-plinko-recent::before { content: "Just landed"; }

.ol-plinko-top { right: 64px; top: 520px; }
.ol-plinko-top::before { content: "Top scores"; }
```

## Controls

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 5 are defined by the overlay itself and are recreated, with the default values shown, for anyone who copies it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:background_color]]]` | color | Background | `#120720` | yes |
| `[[[c:text_color]]]` | color | Text | `#fafafa` | yes |
| `[[[c:soft_text_color]]]` | color | Soft text | `#e9d5ff` | yes |
| `[[[c:muted_color]]]` | color | Pegs and labels | `#a78bfa` | yes |
| `[[[c:highlight_color]]]` | color | Highlight | `#f472b6` | yes |

### Control detail

- `c:background_color` - Behind everything, and the tag that carries a chatter's name on their ball.
- `c:text_color` - The heading, the names in the lists, and the name on each ball.
- `c:soft_text_color` - The how-to-play sentence under the heading.
- `c:muted_color` - The pegs, the slot numbers and dividers, the list titles and the lines between rows.
- `c:highlight_color` - The command, the points, a peg lighting up when a ball hits it, the landing flash, and the ball of a chatter who never picked a name color.

Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in seconds of when it last changed.

## Live data tags used

- `[[[plinko_board]]]` - the board itself. The overlay draws it, reading every `!drop` straight from your Twitch chat, and the CSS custom properties on `.ol-plinko` set the rows, the pace and the colours.
- `[[[plinko_command]]]` - the word the board listens for, without its `!`. `drop` unless you chose another on the Plinko settings page.
