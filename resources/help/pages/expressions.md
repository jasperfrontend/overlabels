---
title: Expression Controls
description: "Expression Controls in Overlabels: math-powered live data with no code and no server. Build chained formulas like the Haversine distance, progress bars, and more, evaluated live as your data changes."
heading: Expression Controls
lead: Math-powered live data, no code and no server. Build chained formulas like the Haversine distance and progress bars, evaluated live as your data changes.
canonical: https://overlabels.com/help/expressions
context: settings.controls, controls.index
---

## What is an Expression Control?

An Expression Control is a Control whose value is computed from a formula you write. Instead of typing a
static number or text, you write a math expression - and Overlabels evaluates it live, every time any of
the values it references change.

The result behaves like any other Control. You reference it in your overlay HTML/CSS with
`[[[c:your_control_name]]]`. You can reference it from *other* Expression Controls too. It's just a
number, computed automatically.

## Syntax basics

Inside an Expression Control, you write a math expression using:

- **Bare math functions** - `sin(x)`, `cos(x)`, `sqrt(x)`, `atan2(y, x)`, `abs(x)`, `round(x)`,
  `floor(x)`, `ceil(x)`, `tan(x)`, `asin(x)`, `acos(x)`, `atan(x)`
- **Constants** - `PI`
- **Standard operators** - `+`, `-`, `*`, `/`, `%`
- **Other Controls** - referenced with `c.control_name`
- **Service Controls** - referenced with `c.service.key`, e.g. `c.gps.lat`, `c.kofi.total_received`
- **Twitch Helix data** - referenced with `t.`, e.g. `t.followers_total`

You do **not** use `Math.sin` or `Math.PI` - just `sin` and `PI` directly.

## Referencing Controls in expressions

| What you want | In an expression | In overlay HTML/CSS |
|---|---|---|
| Your own control `goal_km` | `c.goal_km` | `[[[c:goal_km]]]` |
| GPS latitude | `c.gps.lat` | `[[[c:gps:lat]]]` |
| GPS session distance | `c.gps.session_distance` | `[[[c:gps:session_distance]]]` |
| Ko-fi total received | `c.kofi.total_received` | `[[[c:kofi:total_received]]]` |
| Twitch total followers | `t.followers_total` | `[[[followers_total]]]` |

Expression Controls can reference other Expression Controls. The only hard rule: **no circular
references**. A control cannot reference itself, directly or through a chain. Overlabels blocks this.

## Available functions

### Trig

| Function | What it does |
|---|---|
| `sin(x)` | Sine of x (x in radians) |
| `cos(x)` | Cosine of x (x in radians) |
| `tan(x)` | Tangent of x |
| `asin(x)` | Arcsine - inverse of sin |
| `acos(x)` | Arccosine - inverse of cos |
| `atan(x)` | Arctangent - inverse of tan |
| `atan2(y, x)` | Two-argument arctangent. Handles all quadrants correctly. Use this for angular calculations involving GPS coordinates. |
| `sqrt(x)` | Square root of x |

### Rounding and utility

| Function | What it does |
|---|---|
| `abs(x)` | Absolute value - strips the sign |
| `round(x)` | Round to nearest integer |
| `round(x, decimals)` | Round to N decimal places. Returns a **string** (e.g. `round(0.1 + 0.2, 2)` -> `"0.30"`). Because it returns a string, use it last in an expression or use the `\|round:2` pipe in your DSL token instead. |
| `floor(x)` | Round down |
| `ceil(x)` | Round up |

### Multi-argument math

| Function | What it does |
|---|---|
| `max(a, b, ...)` | Highest value among all arguments |
| `min(a, b, ...)` | Lowest value among all arguments |
| `sum(a, b, ...)` | Sum of all arguments |
| `avg(a, b, ...)` | Average of all arguments |
| `clamp(x, min, max)` | Clamps x between min and max - useful for keeping progress bars between 0 and 100 |

### Label selectors

These accept pairs of `value, label` arguments and return the **label** paired with the winning value.
Useful for picking a display name based on a numeric or timestamp comparison.

| Function | What it does |
|---|---|
| `latest(v1, l1, v2, l2, ...)` | Returns the label paired with the highest value. Use with timestamps to find the most recent event. |
| `oldest(v1, l1, v2, l2, ...)` | Returns the label paired with the lowest value. |
| `argmax(v1, l1, v2, l2, ...)` | Returns the label paired with the highest numeric value. |
| `argmin(v1, l1, v2, l2, ...)` | Returns the label paired with the lowest numeric value. |

**Example** - show which donation service sent the biggest single donation:

```
argmax(c.kofi.latest_donation_amount, "Ko-fi", c.bmac.latest_donation_amount, "BMAC", c.streamlabs.latest_donation_amount, "Streamlabs")
```

Returns `"Ko-fi"`, `"BMAC"`, or `"Streamlabs"` - whichever had the highest last donation.

### Constants

| Constant | Value |
|---|---|
| `PI` | 3.14159265... |

## Worked example: GPS distance to destination

This is the real-world scenario that motivated the trig functions being added. A streamer is cycling to a
destination. Their overlay needs to show how far they still have to go, and move a character across the
screen proportional to their progress.

The **Haversine formula** gives you the straight-line distance between two GPS coordinates on Earth.
Here's how to build it entirely in Expression Controls.

### Step 1 - Create your static number controls

Create three **Number Controls** manually. These are the destination coordinates and the length of your
trip in km. Set them once and leave them.

| Control key | Value | What it is |
|---|---|---|
| `dest_lat` | `51.5074` | Destination latitude |
| `dest_lng` | `4.3571` | Destination longitude |
| `goal_km` | `450` | Total distance goal in km |

### Step 2 - Build the Haversine as chained Expression Controls

Create each of these as an **Expression Control**, in order. Each one builds on the previous.

`dLat` - latitude delta in radians

```
(c.dest_lat - c.gps.lat) * PI / 180
```

`dLng` - longitude delta in radians

```
(c.dest_lng - c.gps.lng) * PI / 180
```

`haversine_a` - the intermediate value

```
sin(c.dlat / 2) * sin(c.dlat / 2) + cos(c.gps.lat * PI / 180) * cos(c.dest_lat * PI / 180) * sin(c.dlng / 2) * sin(c.dlng / 2)
```

`distance_to_dest` - distance remaining in km

```
6371 * 2 * atan2(sqrt(c.haversine_a), sqrt(1 - c.haversine_a))
```

`progress_pct` - how far through the journey, as a percentage

```
(c.goal_km - c.distance_to_dest) / c.goal_km * 100
```

### Step 3 - Use it in your overlay

```html
<!-- Show remaining distance -->
<p>[[[c:distance_to_dest]]] km to go</p>

<!-- Move a character across the screen -->
<style>
  .cyclist {
    position: absolute;
    left: calc([[[c:progress_pct]]] * 1%);
    transition: left 2s linear;
  }
</style>
```

That's it. Every time the GPS app sends a position update, `c.gps.lat` and `c.gps.lng` update, and the
entire chain recomputes automatically - distance, progress, character position. No server roundtrip. No
JS in the overlay. Pure Controls.

## Things to know

### Expressions are evaluated live

When any referenced Control changes value, all Expression Controls that depend on it recompute. This
cascades through chains - so `progress_pct` recomputes when `distance_to_dest` recomputes, which
recomputes when `c.gps.lat` updates.

### GPS controls update on every app ping

The Overlabels GPS Android app sends updates every 2-60 seconds (configurable). Each ping updates
`c.gps.lat`, `c.gps.lng`, etc., which triggers the whole expression chain.

### Angles are in radians

`sin`, `cos`, and all trig functions expect radians. To convert degrees to radians: `degrees * PI / 180`.
GPS coordinates are in degrees, so always convert before passing them to trig functions.

### `atan2` takes two arguments

`atan2(y, x)` - not one, two. It's the only function in the set that works this way.

### Expression Controls can reference service presets

Any of the GPS, Twitch, Ko-fi, Streamlabs, Fourthwall, or BMAC preset controls are
referenceable with `c.service.key`. A donation progress bar that moves toward a goal is just
`c.kofi.total_received / c.goal_amount * 100`. See [Integration Presets](/help/integration-presets) for
the full catalog.

### No EventSub data directly in expressions yet

EventSub triggers (follows, subs, raids etc.) update the preset Controls, which you *can* reference. But
there's no direct `e.` namespace for EventSub in expressions yet - use the presets.

### `?? defaults` don't touch the math

A `?? fallback` on a template tag (like `[[[c:hue_base ?? 100]]]`) is display-only - it fills in literal
text when a tag renders *empty*. It never changes the control's stored value, so an Expression Control
like `c.hue_base + 40` keeps computing on the real value (an empty control reads as 0 in math). The
model: compute first with Expression Controls, then catch any empties at display time with `??` in your
template. See [Formatting Pipes](/help/formatting) for the full syntax.

## Quick reference card

```
sin(x)           cos(x)           tan(x)
asin(x)          acos(x)          atan(x)
atan2(y, x)      sqrt(x)          abs(x)
round(x)         round(x, n)      floor(x)         ceil(x)
max(...)         min(...)         sum(...)         avg(...)
clamp(x, min, max)
latest(v, l, ...)   oldest(v, l, ...)
argmax(v, l, ...)   argmin(v, l, ...)
PI

c.my_control          -> your own control
c.gps.lat             -> service control (GPS latitude)
c.kofi.total_received -> service control (Ko-fi total)
t.followers_total     -> Twitch Helix data
```

Want more math tricks (waves, modulo wheels, pseudo-random)? See the [Math Engine](/help/math) page. Want
the catalog of preset controls you can reference? See [Integration Presets](/help/integration-presets).
