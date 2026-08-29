---
title: Overlabels for Creators - what this thing actually is
description: "A long-form description of Overlabels: a reactive value graph that happens to render to a Twitch overlay. The expensive part is the live math layer. Here's what that means, what's possible, and what's still missing."
heading: Overlabels for Creators
lead: "If you've heard of Overlabels, you probably heard 'Twitch overlays you build with HTML and CSS'. That's true and it's also selling the cheap part. The expensive part - the part that makes Overlabels different from every other overlay tool - is the live math layer underneath."
canonical: https://overlabels.com/help/for-creators
---

This page is the long-form description of what that actually means. It's written for creators in the
broad sense: streamers who want to know what they're holding, and creative coders who might want to help
map the space.

## The thesis

Overlabels is a **reactive value graph** that happens to render to a browser source. Every overlay is a
tree of named values. Every value can be a constant, a live data feed, a formula derived from other
values, or a chain of formulas derived from formulas. When any value changes, every value that depends on
it recomputes. When a formula's result lands in your overlay's HTML or CSS, the pixels react.

That's it. The rest is just primitives.

## The primitives

### Values (Controls)

Every named value is a Control. Controls have a key, a type, and a current value. You reference a Control
in your overlay HTML or CSS with `[[[c:my_control]]]`. You also reference it from other Controls through
expressions with `c.my_control`.

Types: *text, number, counter, timer, boolean, datetime, expression*. Most of these are static unless you
change them. The interesting two are **timer** (ticks in real time) and **expression** (recomputes
whenever its dependencies change).

A Control isn't just a variable. It's the unit of reactivity. The whole system watches Controls and
re-renders the right things when one changes.

### Sources (live data)

A Source produces values automatically. The streamer connects a Source once; from then on it emits
Controls in the `c.<service>.<key>` namespace.

Sources today:

- **Twitch** - per-stream counters, latest cheer / latest donor, follower / sub / raid / redemption
  tallies
- **Ko-fi**, **Streamlabs**, **Fourthwall**, **Buy Me a Coffee** - donations and tips
- **Overlabels GPS** - live phone GPS: lat, lng, speed, distance, battery, accuracy, plus session
  aggregates
- **Time itself** - `now()` and `now_ms()` are bare functions in expressions that always return the
  current timestamp

Every value any Source emits is *just a Control*. Which means anything you can do with a Control you can
do with a live data feed. Which means a CSS rule that bends a sprite based on `c.gps.speed` works exactly
the same as a CSS rule that bends a sprite based on `c.my_manual_slider`.

### The expression engine

Expression Controls let you write formulas that compute their value live. The engine is sandboxed (no
`eval`, no prototype walking, no network). It supports:

- Arithmetic: `+ - * / %`
- Comparisons and conditionals: `== != > < >= <= && || ? :`
- Trigonometry: `sin cos tan asin acos atan atan2`
- Math utilities: `sqrt abs round floor ceil max min clamp sum avg`
- Label selectors: `argmax argmin latest oldest` - return the *label* paired with the winning value,
  useful for "which service had the most recent donation"
- GLSL-style helpers: `fract mod`
- Time: `now() now_ms()`
- Constants: `PI`

Expressions can reference other expressions. Cycles are blocked. The full reference, including the
Haversine great-circle distance walkthrough, is at [/help/expressions](/help/expressions).

### Output (the overlay itself)

An overlay is an HTML and CSS template. Anywhere you can write text, you can drop `[[[c:something]]]` and
it gets replaced with the live value of that Control. CSS isn't special - you can put
`calc([[[c:progress_pct]]] * 1%)` in a `left:` rule and the engine keeps it in sync.

**No JavaScript, no `<script>`, no `<iframe>`, no embeds** - all dynamism comes from Controls and CSS.
See "The constraint is the feature" below for why.

Templates can react two ways:

- **Pull** - the value is interpolated into your HTML/CSS, and CSS transitions or keyframes do the
  smoothing.
- **Push** - an Alert template fires once when an EventSub event arrives (a follow, a sub, a raid, a
  donation), animates, and disappears.

## What this means for creators

A streamer wants a thing on their overlay to react to data. The conventional overlay tool gives them a
knob to turn or a widget to drop in. Overlabels gives them a number, a formula, and a CSS rule. The
expressivity gap is enormous and undersold. Each of the following is a few-line expression and a few-line
CSS rule:

- A **donation goal** that physically opens a treasure chest as
  `c.kofi.total_received / c.goal_amount` crosses thresholds.
- A **subathon timer** that tints the overlay redder as remaining seconds drop, using `clamp` and HSL
  `calc()`.
- A **GPS-driven cyclist sprite** that bends forward proportional to current speed.
- A **chat-vote split bar** that wobbles harder when the vote is close (read: when
  `abs(c.option_a - c.option_b)` is small).
- A **latest-donor name** that pulls from whichever service tipped most recently:
  `latest(c.kofi.donations_received_at, c.kofi.latest_donor_name, c.streamlabs.donations_received_at, c.streamlabs.latest_donor_name)`.
- A **Lissajous curve, a wave, a breathing UI element, a pseudo-random shader effect** - all expressible
  as a formula on top of `now()`.

None of those require deploying code, restarting OBS, or shipping a plugin update. The streamer types
into a textbox, hits save, and the change is live within a second.

## Why this is different from other overlay tools

| Tool                                | What you get                                          | Composability                                               |
|-------------------------------------|-------------------------------------------------------|-------------------------------------------------------------|
| Streamlabs / StreamElements widgets | Pre-built widgets with config knobs                   | You configure. You can't really compose.                    |
| OBS source plugins                  | Anything, in code                                     | Per-streamer engineering. Compile, deploy, restart.         |
| Browser-source HTML overlays        | A static page that polls or listens                   | You write the JS. You host the page. You handle reconnects. |
| **Overlabels**                      | **Any value, any source, any formula, any consumer.** | **Composable end-to-end. Live in <1s after save.**          |

The Overlabels overlay is the inexpensive part. The expensive part is the reactive value graph underneath, and
the integrations that pump real data into it.

## 5. The constraint is the feature

> [!TIP]
> The "no JavaScript" rule is **NOT** an oversight: it's the load-bearing security and shareability decision
> the whole system rests on.

Templates flow between users. Streamers copy each other's overlays, paste tags from screenshots, and
remix designs in the wild. If templates could run JS, every copied overlay would be a potential
supply-chain attack: a hidden fetch loop that exfils session tokens, an iframe pointing at a phishing
page, an event listener that ships streaming patterns to an unknown server. Overlabels would last about a
week before someone shipped a popular template that quietly turned every overlay into a data-collection
node.

So templates get sanitized server-side before they ever reach a browser source. No `<script>`, no
`<iframe>`, no `on*=` handlers, no inline `javascript:` URLs. Tags are parsed exactly once per render, so
even Control values can't smuggle markup through a template-injection trick. Rendered overlays don't
phone home either: the URL-fragment auth-token model means the page literally can't report telemetry
back, by design.

What you do instead: **the expression engine and CSS animation are your runtime.** Anything you would
have reached for JS to do - state machines, conditional renders, animation timing, easing curves,
periodic effects - you do as a chained Expression Control feeding into a `calc()`, a CSS variable, or a
`transition`. The Haversine walkthrough on [/help/expressions](/help/expressions) is exactly this: five
chained formulas plus a CSS rule, no JavaScript anywhere, animating a sprite across a 1080p browser
source in response to live phone GPS.

For a creative coder this is the headline: **your recipe is safe to ship**. You don't have to convince a
stranger to trust your code. The streamer who copies it doesn't need a security review. The overlay just
runs, in any browser source, with no escape hatch and therefore no exploit path. Recipes published in the
Overlabels recipe book carry the same guarantee every other template does: nothing reaches outside the
sandbox, ever. The constraint is what makes the whole "copy someone else's clever overlay" loop work at
all.

## Honest gaps

Things creators ask for that aren't possible yet. If you're a creative coder evaluating the surface, you
probably know where the walls are:

- **Audio analysis**: no mic level, no music BPM detection. Open question.
- **MIDI / hardware controllers**: no mapping today.
- **Direct EventSub data in expressions**: EventSub triggers update preset Controls (which you can
  reference) and fire Alerts (one-shot animations), but there's no direct `e.<event>.<field>` namespace
  inside expressions.
- **Persistent state across stream sessions for arbitrary expressions**: counters and sliders persist;
  computed values are re-derived from inputs each time.
- **Multi-overlay synchronization**: each overlay is independent.

## 7. Looking for collaborators

If you're a creative coder, shader artist, or generative-art person who reads `sin(t * PI / 2)` like a
sentence, here's the gig:

I'm looking to co-author a recipe book of Expression Control examples. Each recipe is a screenshot or
gif, the formulas, and the HTML/CSS that consumes them. Think Shadertoy entries, but each one is a
self-contained overlay effect a streamer can copy in 30 seconds. Donation pulses, GPS-driven sprites,
subathon-timer tints, follow-count auras, raid wormhole transitions, vote-bar wobbles&hellip; Any combination
of value source + expression + CSS that's worth its own gif.

Paid. Open call. Attribution included. The bar is "would another creative coder find this clever", not
"is it useful to the median streamer". The median streamer copies what other people built.

Mail: [jasper@emailjasper.com](mailto:jasper@emailjasper.com). Include a portfolio link, a paragraph on
what kind of effect you'd want to start with, and a rate.

## 8. Deep-dives

The rest of the help section is the developer-style reference for each primitive:

- [Controls](/help/controls): the seven control types and how they behave on an overlay.
- [Expression Controls](/help/expressions): the math layer in full, with the Haversine walkthrough.
- [Integration Presets](/help/integration-presets): the catalog of every auto-managed Control across
  Twitch, Ko-fi, Streamlabs, Fourthwall, BMAC, and Overlabels GPS.
- [Math Engine](/help/math): waves, modulo wheels, pseudo-random one-liners, timestamp racing.
- [Conditional and Event Tags](/help/conditionals): if/else logic in templates.
- [Formatting Pipes](/help/formatting): locale-aware number, currency, duration, distance, and speed
  formatting.
- [Manifesto](/help/manifesto): principles and philosophy, if that's your thing.
