---
title: Help - Overlabels
description: Learn how to build Twitch overlays with Overlabels - conditional tags, controls, formatting, and more.
heading: Welcome to Overlabels Help
lead: "Everything you need to build dynamic Twitch overlays - from template syntax to live controls. This page is a bit of a clusterf*ck, but I'll get around to cleaning this up eventually."
canonical: https://overlabels.com/help
---

> [!TIP]
> **Reading this as a machine?** Every page below is also available as plain markdown - append `.md` to
> any URL (`/help/conditionals.md`). This index is at `/help.md`. The bare URLs render a Vue app, so the
> `.md` form is the one you want. For a single self-contained primer on writing overlays, start with
> [`/llms.txt`](https://overlabels.com/llms.txt) - what it contains and how to hand it to an assistant
> is explained at [llms.txt](/help/reference/for-machines/llms-txt).

## Start here

- [**Why Overlabels**](/help/why-overlabels) - the pitch, for coders. Third-party data normalization,
  math on any value, Expression Controls, and why this thing is a bit special.
- [**For Creators**](/help/for-creators) - what Overlabels actually is, beneath the HTML/CSS surface. The
  reactive value graph, the live data sources, the math layer, and an open call for collaborators.
- [**For Designers**](/help/for-designers) - a handoff guide for designers working on overlays: the two
  surfaces, the unknown-background problem, variable-length content, fluid layout, CSS animation, and a
  deliverables checklist.
- [**Manifesto**](/help/manifesto) - what Overlabels is, why it exists, and the principles behind it.

## Building overlays

- [**Overlays vs Alerts**](/help/overlays-vs-alerts) - the two kinds of overlay and how they fit
  together: why alerts are most powerful rendered inside a static overlay's DOM, plus Targeting vs
  Triggers.
- [**The Builder**](/help/builder) - compose an overlay without writing code: set up a grid, click a
  cell, pick a block, save. Compiles to a plain static overlay that works with everything else.
- [**Blocks**](/help/blocks) - reusable building pieces for the Builder: how to author one, how CSS
  scoping and snapshots keep everyone safe, and how controls travel with your block.
- [**How an overlay renders**](/help/rendering) - the pipeline end to end: boot, tag replacement,
  conditionals, live updates, alert render flow, and why scripts are stripped.
- [**Testing your alerts**](/help/testing) - fire any of 28 real Twitch events at your own account from a
  terminal, instead of waiting for a real follower.

## The template language

- [**Conditional and Event Tags**](/help/conditionals) - if/else logic, comparisons, event data tags, and
  integration tags for Ko-fi and StreamLabs.
- [**Formatting Pipes**](/help/formatting) - format numbers, durations, currencies, and dates with pipe
  syntax. Locale-aware, zero dependencies.
- [**Math Engine**](/help/math) - waves, modulo wheels, pseudo-random one-liners, timestamp racing - the
  math behind live overlays.

## Live data

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

## Chat

- [**Twitch Chat Bot**](/help/bot) - the @overlabels bot joins your channel so viewers and mods can
  change controls from chat.
- [**Chat Castle**](/help/gamejam) - chat-driven dungeon raid. Commands, rounds, energy blocks, chest
  contents, and how to not lose the HP pool.

## Reference and extras

- [**Reference**](/help/reference) - fuzzy-searchable reference for every template tag, EventSub event,
  and foreach field. Press Alt+r from anywhere.
- [**Overlay Access Tokens**](/help/tokens) - what the 64-character token in your overlay URL is, why it
  lives after the `#`, and what to do the moment one leaks.
- [**Why Ko-fi**](/help/why-kofi) - why we chose Ko-fi as our first External Integration over Patreon,
  Stripe, or built-in payments.
- [**Free Resources**](/help/resources) - colors, fonts, animations, images, and other tools for building
  overlays.
