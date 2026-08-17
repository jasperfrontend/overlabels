---
title: Overlabels for Designers - what to deliver, what to avoid
description: "A handoff guide for designers working on Twitch overlays in Overlabels. The two surfaces (static + alert), the unknown-background problem, variable-length content, fluid layout, CSS animation constraints, and a concrete deliverables checklist."
heading: Overlabels for Designers
lead: This page is for the designer working on a Twitch overlay that will be implemented in Overlabels, and for the streamer who hired them and is wondering what to actually ask for.
canonical: https://overlabels.com/help/for-designers
---

Overlay design has constraints that web and product design don't usually train for: the background is
literally a video game, an IRL camera walking through a sunlit park, a cooking stream, or a moving
cycling shot. Every text field is variable-length, and every animation has to be expressible in CSS.
Mockups that look pristine in Figma can fall apart the second a username goes from "Jasper" to
"xX_LongUsername2024_Xx", or the streamer switches from a dark dungeon to a blown-out outdoor scene where
the horizon is pure white. This page is the pre-flight checklist.

## The two surfaces: static and alert

An Overlabels overlay is two distinct surfaces, with different constraints. Designing them as one thing
is the most common mistake.

### Static overlay

The always-on layer. Camera frames, follower counters, donation goals, current game or location, recent
supporter, GPS speed, subathon timer. It sits on the screen for hours. Live values mutate inside it.

**Design constraint:** nothing in the static overlay should ever distract from what the streamer is
actually doing - whether that's playing a game, walking through a city on an IRL stream, cooking, or just
chatting on a webcam. No looping animations that pull the eye. No flashing. No high-contrast motion at
the edges of the safe area. Subtle drift, breathing, or pulse-on-event is fine. A 4-second loop that
draws attention every 4 seconds for 6 hours is not.

### Alert overlay

The one-shot layer. Fires when an event arrives (a follow, a sub, a raid, a Ko-fi donation). Animates in,
holds for a few seconds with the event data on screen, animates out, vanishes.

**Design constraint:** alerts are *supposed* to draw attention. They have a lifecycle (enter, hold, exit)
and a duration (typically 4-8 seconds total). The hold phase needs to be readable in a second or two by a
viewer who looks up at it after the streamer reacts. Animation can be loud; copy cannot be wordy.

## The background is unknown

The overlay sits on top of whatever the streamer is showing - dark dungeon, sunlit IRL street, white
starting-soon screen, a cycling horizon that swings between pavement and overblown sky. A design that
pops against gameplay can fall apart mid-broadcast when the camera steps into noon sun.

Standard strategies for "readable on any background":

- **Contrast plates** - semi-transparent dark or blurred panel behind text. Most common professional
  move.
- **Text stroke** - 2-3px outline. Cheap, slightly ugly at small sizes.
- **Drop shadow** - soft, 8-16px blur, low opacity. Lifts text off anything.
- **Backdrop blur** - `backdrop-filter: blur(8px)` behind a tinted panel. Modern, expensive on low-end
  GPUs.

**Designer deliverable:** mock against four backgrounds - dark game, sunlit-overblown IRL, night-time
IRL, and pure white. If the design holds in all four, ship.

## Every text field is variable-length

Live data fields are not fixed-width. A username can be 3 characters or 25. A donation amount can be $1
or $1,000. A donation message can be empty or 200 characters of emoji and exclamation marks. A follower
count can be 12 or 12,000,000. The same overlay slot has to accommodate all of these without breaking.

| Field                 | Realistic short | Realistic medium               | Realistic worst-case                                              |
|-----------------------|-----------------|--------------------------------|-------------------------------------------------------------------|
| Twitch username       | an              | JasperDiscovers                | xX_DragonSlayer2024_Xx (25 chars max)                             |
| Donation amount       | $1              | $25                            | $1,234,567                                                        |
| Donation message      | (empty)         | "Love the stream!"             | 200 chars of mixed text and emoji                                 |
| Follower count        | 12              | 8,432                          | 12,847,392                                                        |
| Game / category title | Doom            | Elden Ring, or "Just Chatting" | Tom Clancy's Rainbow Six Siege Extraction, or "Travel & Outdoors" |
| GPS speed             | 0 km/h          | 42 km/h                        | 217 km/h (or m/s with three decimals)                             |

**Strategies:**

- **Truncate with ellipsis** for fields that have a hard layout slot (donor message in a 1-line alert).
- **Allow vertical growth** for fields that should never be cut (donation message, raid greeting). Design
  the panel to expand downward.
- **Right-align numbers** so the digit count visually grows leftward into space you reserved.
- **Auto-shrink font size** for hero text fields (subathon timer, big counter) where the value can grow
  by orders of magnitude.
- **Test against the worst case.** Mock up the alert with the longest realistic donor name and message.
  If it survives, ship.

## Fluid layout, not pixel-perfect

The reference resolution is 1920x1080. But OBS scales browser sources, streamers run different DPI, and a
"fits perfectly at exactly 1920" design tends to look fragile at 1280 or wrong at 2560. Design in a way
that survives:

- **Think in flex and grid, not absolute pixels.** Mock at 1920x1080 but specify spacing as ratios or
  rems where it matters ("16px gap between item and label" rather than "label at x=842").
- **Anchor to corners, not coordinates.** "Top-right, 32px from the edges" implements cleanly. "x=1856,
  y=32" implies pixel-positioning that doesn't survive scaling.
- **Use SVGs and vector decoration.** A raster decoration at 1920 looks fuzzy when OBS scales the source
  to 2560. SVG stays crisp.
- **Set explicit safe areas.** Twitch overlays the chat sidebar on theater mode (and Twitch streamers'
  webcams often live in known zones). Identify safe areas in the design and avoid putting critical info
  in them.

## Animation lives in CSS

Overlabels overlays don't run JavaScript (this is a deliberate security and shareability decision - see
["The constraint is the feature"](/help/for-creators#the-constraint-is-the-feature) on the For Creators
page). All animation runs through CSS keyframes, transitions, and transforms. That has consequences for
what a designer can spec.

**Things CSS does well:**

- Transforms (translate, scale, rotate, skew) - GPU-accelerated, butter-smooth
- Opacity fades, color transitions, blur
- Keyframed loops with custom easing curves (cubic-bezier)
- Stagger via animation-delay, mid-anim pauses via easing tricks
- Reactive animation: a transition that fires whenever a Control changes value (which means a donation
  can drive a pulse without anyone writing code)

**Things CSS can't do, that a video tool can:**

- Per-particle physics (sand, water, smoke - too expensive in CSS)
- Procedural shape morphing beyond what SVG path-morphing allows
- True 3D scenes with lighting (CSS 3D is fake-3D plane stacking)
- Frame-perfect synchronization with audio

**Lottie is supported.** If a designer wants a complex vector animation (a celebration burst, a coin
shower, a custom logo reveal), exporting to Lottie via After Effects + Bodymovin and dropping the JSON in
is fine - Overlabels includes the lottie-web player. Note: lottie.host's upload UI was absorbed into
lottiefiles.com, so new uploads go through there or tiiny.host. Existing lottie.host URLs still work.

**Designer deliverable for animation:** a video reference of the desired motion (Lottie export preferred,
or a quick screen-recording from After Effects / Figma's prototyping mode), *plus* timing and easing
notes ("400ms ease-out, then a 2s hold, then 600ms ease-in"). The implementer translates those notes into
CSS keyframes. Without the timing notes, the implementer is guessing.

## 6. What to deliver

A handoff that lets an implementer translate the design into Overlabels HTML/CSS without follow-up
questions. In rough priority:

### A Figma file (or equivalent)

Frames at 1920x1080 for each surface (static overlay, each alert variant). Layers named meaningfully -
"donor-name", "amount-pill", "icon-coin" - not "Rectangle 47". Components used for repeated elements. If
the file is messy, the implementer prices in cleanup time.

### Multiple states per surface

Show the static overlay at minimum once with realistic short content and once with worst-case long
content. Show each alert at minimum its short and long state, plus the entry / hold / exit moments
annotated. Empty states matter too - what does the "latest donor" panel look like before anyone has
donated this stream?

### Color tokens

A small palette of named colors (primary, accent, success, warning, surface, surface-elevated, text,
text-muted) with hex values. The implementer puts these into CSS custom properties so every component
pulls from the same source. Don't sprinkle 47 hand-picked hex values across the design.

### Typography spec

Font family, weight, size (rems preferred), line-height, letter-spacing, and the actual font file or
Google Fonts URL. Be explicit about fallbacks for users who block third-party fonts. If the font doesn't
have a free web license, flag it now - that's a license-check moment, not an implementation decision.

### Asset exports

SVG for icons and decorative shapes, exported with optimized paths and sane viewBox. PNG (transparent)
for raster art that genuinely needs to be raster (a textured logo, a painted illustration). WebP is fine
for photographic content. *Not* JPGs with hard backgrounds. *Not* flattened final renders of the whole UI
- those are mockups, not assets.

### Animation references

One short video per animated element showing the desired motion at the desired timing. Lottie JSON if the
animation is complex. Annotated timing notes ("400ms ease-out enter, 2.5s hold, 500ms ease-in exit") for
everything. Without these, the implementer estimates - which is fine, but the streamer ends up with
motion the designer didn't intend.

### A list of which fields are live data

Mark every text element as either "static copy" or "live data". For live data, name the source ("Twitch
follower count", "latest Ko-fi donor", "GPS speed"). The implementer maps these to the right Overlabels
Controls and template tags. The [Integration Presets](/help/integration-presets) page is the catalog of
every available live data field.

## What not to deliver

- **A single flattened PNG of the overlay.** Looks great, useless for implementation.
- **Mockups with placeholder Lorem Ipsum.** Use realistic strings: real-length usernames, real donation
  amounts, real game titles or IRL category labels.
- **Pixel-fixed coordinates.** "Position label at x=842, y=560" is not implementable in a fluid layout.
  Specify in spacing relationships ("16px below the avatar, left-aligned with the donor name").
- **Animation specs without timing.** "It pulses" is ambiguous. "200ms scale 1.0 to 1.05 ease-out, 200ms
  back to 1.0 ease-in, on every donation event" is implementable.
- **Fonts that aren't web-licensed.** If the designer specifies a $300/seat foundry font, the streamer
  either pays the license or the implementer substitutes a Google Font and the design drifts. Catch this
  at design-review.
- **Designs that only work on one background.** If the mockup is on a dark game and the streamer's next
  stream is a sunlit IRL walk - or even just a different scene in the same broadcast, like an IRL
  streamer stepping out of a shaded alley into noon sun - the overlay falls apart in real time.
- **Adobe After Effects projects with no Lottie export and no video reference.** The implementer can't
  open the .aep, and CSS animation is not video animation. Lottie or video, not project files.

## Working with the implementer

The implementer (the streamer themselves, or someone they hired to translate the design into Overlabels)
needs three things to start: the Figma file, the asset exports, and the live-data field list. Everything
else can be questions during the build.

**What goes well:**

- The designer is available for a 30-minute review when the implementer has a working draft.
- Naming in the Figma file matches naming in the implementer's CSS (the donor-name layer becomes the
  .donor-name class).
- The designer accepts that some pixel-perfect details are going to flex when the design meets real data
  and real backgrounds, and is willing to iterate on those moments rather than fight them.

**What goes badly:**

- The designer disappears after handoff and the implementer has to make every micro-decision alone.
- "That's not what I designed" is the only feedback after a draft, with no specifics.
- Animation that wasn't specced in the design becomes scope creep mid-implementation ("oh, can it also do
  this").

> [!IMPORTANT]
> **Bottom line.** Your static and alert overlays need to be ready for **59 presets across 8
> integrations** (see [/help/integration-presets](/help/integration-presets) for the full catalog). The
> **static overlay** holds persistent state that mutates in real time - follower counts, donation totals,
> latest donor name, GPS speed - and has to keep showing those values readably whether they're short or
> long, small or huge. **Alerts** fire one-shot animations when events arrive, with the same flex
> requirement: the next chatter who subscribes might be named `XWXWXWXWXWXWXWXWXWXWXW`, the next Ko-fi
> donation might be $0.50 or $5,000, and your beautifully balanced "thanks for the sub" panel has to
> absorb both without tanking. Design once, survive everything the audience throws at it.

## Deep dives

The technical pages a designer might want to skim, to see what their design will be implemented against:

- [For Creators](/help/for-creators) - the system overview. What Overlabels is beneath the HTML/CSS
  surface, including the no-JS rule and why it exists.
- [Integration Presets](/help/integration-presets) - the catalog of every live data field across Twitch,
  Ko-fi, Streamlabs, Fourthwall, BMAC, and Overlabels GPS. Useful for marking "this is
  live data" in a design handoff.
- [Controls](/help/controls) - the seven mutable value types the streamer can adjust live during a
  stream.
- [Expression Controls](/help/expressions) - how live data turns into derived values that drive design
  states (a goal-progress percentage that drives a fill-bar width, for example).
- [Formatting Pipes](/help/formatting) - how raw values become locale-aware display strings. A designer
  specifying "currency, two decimals, EUR" in a mockup maps to a one-line pipe in the implementation.
