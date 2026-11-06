---
name: Twitch Chat Plinko
type: static
author: Overlabels
---

# Twitch Chat Plinko

A Plinko board your Twitch chat plays. Every !drop in chat drops a ball with that chatter's name on it, in the order chat sends them, one ball each at a time. Real bouncing off every peg, 1000 points at the rare edges, a line of who is next and the top scores so far.

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
    <h1>Type <span class="cmd">!drop</span> in chat</h1>
    <p class="sub">Your name rides the ball. One ball each, played in the order chat sends them. The edges are rare and pay 1000.</p>
  </div>
</div>
```

### `css`

The stylesheet. Tags work in here too.

```css
:root {
  --ink: #fafafa;
  --ink-soft: #e9d5ff;
  --ink-muted: #a78bfa;
  --pink: #f472b6;
  --field: #1d0b30;
  --field-deep: #120720;
  --line: rgb(167 139 250 / 0.35);
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
  --plinko-command: drop;
  --plinko-rows: 14;
  --plinko-max-balls: 150;
  --plinko-rate: 10;
  --plinko-peg-color: #a78bfa;
  --plinko-accent: #f472b6;
  --plinko-ball-color: #f472b6;
  --plinko-label-bg: #120720;
  --plinko-label-color: #fafafa;
  --plinko-slot-color: #a78bfa;
  --plinko-font: "Albert Sans", system-ui, sans-serif;
}

/* ---------- left: how to play, then who is next ---------- */
.intro {
  position: absolute;
  left: 64px;
  top: 64px;
  width: 360px;
}
.eyebrow {
  font-family: var(--mono);
  font-size: 16px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-muted);
}
h1 { margin-top: 12px; font-size: 48px; line-height: 1.05; font-weight: 800; }
.cmd { font-family: var(--mono); color: var(--pink); }
.sub { margin-top: 16px; font-size: 19px; line-height: 1.45; color: var(--ink-soft); }

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
.ol-plinko-points { font-family: var(--mono); color: var(--pink); }

.ol-plinko-next { left: 64px; top: 420px; }
.ol-plinko-next::before { content: "Next up"; }
.ol-plinko-next:empty { display: none; }

.ol-plinko-recent { right: 64px; top: 64px; }
.ol-plinko-recent::before { content: "Just landed"; }

.ol-plinko-top { right: 64px; top: 520px; }
.ol-plinko-top::before { content: "Top scores"; }
```

## Controls

This overlay defines no controls of its own.

## Live data tags used

- `[[[plinko_board]]]` - the board itself. The overlay draws it, reading every `!drop` straight from your Twitch chat, and the CSS custom properties on `.ol-plinko` set the command word, the rows, the pace and the colours.
