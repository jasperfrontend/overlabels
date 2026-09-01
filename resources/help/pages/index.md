---
title: Help - Overlabels
description: Learn how to build Twitch overlays with Overlabels - tutorials, conditional tags, controls, formatting, and a searchable reference.
heading: How can we help?
lead: Tutorials, guides and deep dives for building Twitch overlays in plain HTML and CSS. Your overlay is a webpage. Start here.
canonical: https://overlabels.com/help
---

> [!TIP]
> **Reading this as a machine?** Every page below is also available as plain markdown - append `.md` to
> any URL (`/help/conditionals.md`). This index is at `/help.md`. The bare URLs are real server-rendered
> HTML and carry the full prose, so either form works; the `.md` is just the same content without the
> navigation around it. For a single self-contained primer on writing overlays, start with
> [`/llms.txt`](https://overlabels.com/llms.txt) - what it contains and how to hand it to an assistant
> is explained at [llms.txt](/help/llms-txt).

## Tutorials

Short, copy-and-paste tutorials. Each one ends with something on screen.

- [**Show chat on screen**](/help/tutorials/show-chat-on-screen) - live Twitch chat in your overlay with
  one loop. Badges, emotes, colours, and the settings that decide what gets drawn.
- [**Show my latest follower**](/help/tutorials/latest-follower) - one tag, plus what to draw when you
  have no followers yet and why that matters.
- [**Show my last 5 subscribers**](/help/tutorials/last-five-subs) - a list on screen, with gifted subs
  credited to the person who paid.
- [**Show my latest donator, from any source**](/help/tutorials/latest-donator) - one name across all
  five donation services, using `latest()` and the automatic `_at` timestamps.

## Guides

How each part of the engine works, grouped the same way the help site groups them. Skim the group you
need, ignore the rest.

### Getting started

- [**Why Overlabels**](/help/why-overlabels) - the pitch, for coders. Third-party data normalization,
  math on any value, Expression Controls, and why this thing is a bit special.
- [**For Creators**](/help/for-creators) - what Overlabels actually is, beneath the HTML/CSS surface. The
  reactive value graph, the live data sources, the math layer, and an open call for collaborators.
- [**For Designers**](/help/for-designers) - a handoff guide for designers working on overlays: the two
  surfaces, the unknown-background problem, variable-length content, fluid layout, CSS animation, and a
  deliverables checklist.
- [**Manifesto**](/help/manifesto) - what Overlabels is, why it exists, and the principles behind it.
- [**Overlays vs Alerts**](/help/overlays-vs-alerts) - the two kinds of overlay and how they fit
  together: why alerts are most powerful rendered inside a static overlay's DOM, plus Targeting vs
  Triggers.
- [**How an overlay renders**](/help/rendering) - the pipeline end to end: boot, tag replacement,
  conditionals, live updates, alert render flow, and why scripts are stripped.

### Tags & syntax

- [**Conditional and Event Tags**](/help/conditionals) - if/else logic, comparisons, event data tags, and
  integration tags for Ko-fi and StreamLabs.
- [**Formatting Pipes**](/help/formatting) - format numbers, durations, currencies, and dates with pipe
  syntax. Locale-aware, zero dependencies.
- [**Math Engine**](/help/math) - waves, modulo wheels, pseudo-random one-liners, timestamp racing - the
  math behind live overlays.
- [**Why tags are parsed exactly once**](/help/tags-parse-once) - substituted values are never
  re-scanned for tags. The security rule behind it, and what it means while writing templates.

### Building overlays

- [**The Builder**](/help/builder) - compose an overlay without writing code: set up a grid, click a
  cell, pick a block, save. Compiles to a plain static overlay that works with everything else.
- [**Blocks**](/help/blocks) - reusable building pieces for the Builder: how to author one, how CSS
  scoping and snapshots keep everyone safe, and how controls travel with your block.
- [**The code editor**](/help/editor) - autocomplete for every tag, your controls and your Lists, and
  bang snippets like `!chat` that write a whole block for you.
- [**Styling with Tailwind**](/help/tailwind) - utility classes compile at save time, not in the
  browser: which Tailwind v3 syntax works, why borders need one extra line, and when to just write
  CSS.
- [**Free Resources**](/help/resources) - colors, fonts, animations, images, and other tools for building
  overlays.

### Live data

- [**Controls**](/help/controls) - mutable values you can change live - text, numbers, toggles, timers,
  and more.
- [**Expression Controls**](/help/expressions) - math-powered live data - no code, no server. Build
  chained formulas like the Haversine distance, donation progress bars, and more.
- [**Integration Presets**](/help/integration-presets) - every auto-managed control across Twitch, Ko-fi,
  Streamlabs, Fourthwall, BMAC, and Overlabels GPS - all in one searchable reference.
- [**Lists**](/help/lists) - user-owned arrays of values. Raffles, queues, quote walls, leaderboards,
  donation goals - all driven from the dashboard or chat with one primitive and ten verbs.
- [**Lists in realtime**](/help/lists-realtime) - the builder guide: read a List as JSON, subscribe to
  live updates over WebSocket, and add your own custom page (a wheel, a leaderboard) to OBS. Step by
  step.

### Bot & chat

- [**Twitch Chat Bot**](/help/bot) - the @overlabels bot joins your channel so viewers and mods can
  change controls from chat.
- [**Bot Commands**](/help/bot/commands) - every chat command the bot understands: controls, the `!ol`
  meta-command, list operations, and the built-ins.
- [**Bot Aliases**](/help/bot/aliases) - short chat commands that rewrite to longer ones.
- [**Random Rolls and Counters**](/help/bot/random-and-counters) - roll a random number or keep a
  running count, set up from chat in one line.
- [**Twitch Chat in an Overlay**](/help/chat) - render live chat with one foreach loop. Every
  per-message tag, badges and emotes, Shared Chat, the display filters, and the four chat controls.
- [**Chat Checkin**](/help/checkin) - viewers pin themselves on a 3D globe with !checkin. The globe
  tag, the pin feed, counters, distances and alerts.
- [**Chat Castle**](/help/gamejam) - chat-driven dungeon raid. Commands, rounds, energy blocks, chest
  contents, and how to not lose the HP pool.

### Integrations & testing

- [**Why Ko-fi**](/help/why-kofi) - why we chose Ko-fi as our first External Integration over Patreon,
  Stripe, or built-in payments.
- [**Test mode on donation integrations**](/help/integration-test-mode) - send the same test donation
  as often as you like while you build, and what gets reset when you switch it off.
- [**Testing your alerts**](/help/testing) - fire any of 28 real Twitch events at your own account from a
  terminal, instead of waiting for a real follower.
- [**Overlay Access Tokens**](/help/tokens) - what the 64-character token in your overlay URL is, why it
  lives after the `#`, and what to do the moment one leaks.

### For machines

- [**llms.txt**](/help/llms-txt) - the complete overlay-authoring guide as one plain text file, written
  for language models. What is in it, and how to hand it to an assistant.
- [**Markdown endpoints**](/help/markdown-endpoints) - append `.md` to any help page, public overlay or
  public kit URL and get plain markdown back.
- [**help-reference-index.json**](/help/help-reference-index-json) - the entire reference as one JSON
  array. Build your own frontend over it or feed it to an autocomplete.

## Deep dives

Full teardowns of real overlays, one at a time: every control, every trick, and why each piece is
shaped the way it is. Long reads for when you want to see how far the template language stretches.

- [**Inside the Follower bowling lane**](/help/deep-dives/follower-bowling-lane) - a chat-driven
  bowling minigame: a List as the queue, one timestamp as both the clock and the dice roll, and a
  full animation sequence without a line of JavaScript.
- [**Inside the Math Engine Showcase**](/help/deep-dives/math-engine-showcase) - a BRB scene of six
  live demos: epicycles, a spirograph, a sweeping clock, dice, six waveforms and a bouncing
  counter, built from 28 expression controls and the wall clock.
- [**Inside Rarotonga**](/help/deep-dives/rarotonga) - a real OpenStreetMap coastline doing five
  jobs at once, and live great-circle distance and bearing from a chain of haversine expression
  controls. The compass points the "wrong" way, and is right.

## Reference

- [**Reference**](/help/reference) - every template tag, EventSub event, and foreach field. Press
  `Alt+R` from anywhere, or use the search box at the top of every help page - it covers tutorials,
  guides, deep dives and reference entries at once.
