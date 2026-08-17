---
title: Overlays vs Alerts - how alerts render inside your static overlay
description: The difference between a static overlay and an alert in Overlabels, why alerts are most powerful rendered inside a static overlay's DOM, and how Targeting and Triggers fit together.
heading: Overlays vs Alerts
lead: "Overlabels has two kinds of overlay: the always-on static overlay, and the one-shot alert. They look similar in the editor, but they're meant to work together - and understanding how is the difference between a uniform, polished overlay and a bunch of disconnected boxes."
canonical: https://overlabels.com/help/overlays-vs-alerts
context: templates.index, templates.show?type=alert
---

## The two kinds: static and alert

### Static overlay

The always-on layer you add to OBS as a browser source. Follower counters, donation goals, current game,
your webcam frame, a subathon timer. It sits on screen for hours, and live values mutate inside it. This
is also where your shared styling lives - fonts, colors, CSS variables, the scaffolding everything else
hangs off.

### Alert

The one-shot layer. It fires when an event arrives (a follow, a sub, a raid, a Ko-fi donation), shows the
event data for a few seconds, and disappears. An alert is not a standalone scene - it's designed to
render *inside* a static overlay you've already built.

## Alerts render inside your static overlay

Here's the key idea. When an alert is targeted at a static overlay, it doesn't open its own page - it's
appended into the static overlay's DOM, right before the closing `</body>` tag. Same document, same
stylesheet, same everything.

Say your static overlay defines a brand color and a styled avatar:

```html
<style>
  :root { --brand: #6d28ff; }
  .avatar { border: 3px solid var(--brand); }
</style>

<body class="overlay">
  <div class="hud">
    <img class="avatar" src="logo.png" />
    <span class="followers">[[[follower_count]]]</span>
  </div>
</body>
```

When a sub alert fires inside that static overlay, the document looks like this:

```html
<body class="overlay">
  <!-- your static overlay's own HTML -->
  <div class="hud">
    <img class="avatar" src="logo.png" />
    <span class="followers">1,337</span>
  </div>

  <!-- your alert is appended here, just before </body> -->
  <div class="alert">
    <img class="avatar" src="newsub.png" />
    <strong>NightboticaLIVE</strong> just subscribed!
  </div>
</body>
```

> [!NOTE]
> Because the alert lives in the **same document**, its `.avatar` picks up the exact same
> `border: 3px solid var(--brand)` you defined on the static overlay. Every CSS variable, every class,
> every font you set up once is instantly available to your alert. That's an awesome amount of power:
> define your structure and styling in one place, and your alerts inherit it for free. The result is a
> beautifully uniform overlay and alert system that all rely on the same definitions.

## Adding an alert straight to OBS

You *can* add an alert directly to OBS as its own browser source, and it'll render perfectly fine on its
own whenever it fires. Who are we to judge - sometimes that's exactly what you want. But be aware of what
you're giving up. A standalone alert is alone in its own document:

```html
<body>
  <!-- nothing else: the alert is alone in its own browser source -->
  <div class="alert">
    <img class="avatar" src="newsub.png" />
    <strong>NightboticaLIVE</strong> just subscribed!
  </div>
</body>
```

No `--brand` variable, no `.avatar` rule, none of your static overlay's scaffolding. The alert still
works, but you style it from scratch and it won't automatically match the rest of your overlay. If you
want that uniform look, render it inside a static overlay instead (see above).

## Targeting vs Triggers

To render an alert inside a static overlay, open the alert and visit the **Targeting** tab, then choose
one or more static overlays where this alert should render.

> [!WARNING]
> **Heads up:** if you don't set a target overlay, your alert renders in **ALL** of your static overlays.
> That's usually not what you want - pick your targets deliberately.

It's easy to mix up the two alert tabs, so to be clear:

- **Triggers** decide *on which event* this alert fires (a follow, a sub, a Ko-fi donation, and so on).
- **Targeting** decides *in which static overlay* this alert renders.

> [!IMPORTANT]
> **Bottom line.** Build your structure and styling once in a static overlay, then target your alerts at
> it so they render in the same DOM and inherit everything. Adding an alert straight to OBS is valid too
> - it just renders on its own, without your shared scaffolding. Either path works. This is indeed a
> mouthful, but hey: Overlabels is a mouthful. Enjoy, whichever path you choose. When you're ready, the
> [For Designers](/help/for-designers) and [Conditional and Event Tags](/help/conditionals) pages go
> deeper on building each surface.
