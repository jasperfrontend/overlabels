---
title: Math Engine
description: "The math-side of Overlabels: waves, modulo wheels, pseudo-random shaders, timestamp racing, and the expression tricks that make overlays feel alive."
heading: Math Engine
lead: Waves, modulo wheels, pseudo-random shaders, timestamp racing, and the expression tricks that make overlays feel alive - built entirely in a text field.
canonical: https://overlabels.com/help/math
---

This page is for the math-heads. If you came here for gentle examples, try
[Conditionals](/help/conditionals) or [Formatting Pipes](/help/formatting) first. Still here? Good. Put
on the goggles.

## 1. The toolbox

Everything the engine understands. Anything not on this list is intentionally absent - no `eval`, no
`new Function`, no prototype walking. The sandbox is the point.

### Operators

```
+  -  *  /  %    ==  !=  >  <  >=  <=    &&  ||  !    ? :
```

No exponentiation operator (`**`, `^`). For squares, write `x * x`. The ternary `a ? b : c` is available,
including nested form.

### Constants and namespaces

- `PI` - \(\pi \approx 3.14159265\). Identifier only, not `PI()`.
- `c.*` - your controls (`c.wins`, `c.kofi.donations_received`, etc.).
- `t.*` - live Twitch event tags fed by EventSub: `t.followers_total`, `t.subscribers_total`,
  `t.last_cheer_bits`, `t.last_raid_from`, and so on. Every tag that a `channel.*` rule mutates is
  readable here.

Rule of thumb: use `[[[tag]]]` in HTML (parsed once at render), `t.tag` in expression controls (live and
reactive). The two substrates never parse each other's syntax.

### Scalar math

| Call | Meaning |
|---|---|
| `max(a, b, ...)` | Largest of the args |
| `min(a, b, ...)` | Smallest of the args |
| `clamp(lo, x, hi)` | x pinned to [lo, hi] |
| `sum(a, b, ...)` | Arithmetic sum |
| `avg(a, b, ...)` | Arithmetic mean |
| `abs(x)` | \|x\| |
| `round(x)` / `round(x, n)` | Nearest integer, or n decimals |
| `floor(x)` / `ceil(x)` | Round toward −∞ / +∞ |
| `sin(x)` / `cos(x)` / `tan(x)` | Trig, x in *radians* |
| `asin(x)` / `acos(x)` / `atan(x)` | Inverse trig, returns radians |
| `atan2(y, x)` | Angle from coordinates, all four quadrants. Pair with `sqrt` for haversine |
| `sqrt(x)` | Square root. Returns 0 for negative x |
| `fract(x)` | x − floor(x). Always ∈ [0, 1) |
| `mod(a, b)` | Floor-modulo (GLSL-style, not JS `%`) |
| `now()` | Unix timestamp in seconds (integer) |
| `now_ms()` | Unix timestamp in milliseconds - for sub-second animation |

### Argument-pair family

Take value/label pairs and return the *label* paired with the winning value. Ties go to the first pair.

```
argmax(v1, l1, v2, l2, ...)
argmin(v1, l1, v2, l2, ...)
latest(v1, l1, v2, l2, ...)    // alias of argmax, but intent: timestamps
oldest(v1, l1, v2, l2, ...)    // alias of argmin
```

Values are coerced to numbers. Strings that parse as numbers work. Strings that look like ISO dates are
parsed as milliseconds since epoch.

## 2. The heartbeat: `now()`

Every time-based trick in this page reduces to one identity. Let \(t = \text{now}()\) be the current Unix
time in seconds. Time only matters once you *take its fractional part*, *feed it through trig*, or
*compare it to another timestamp*. Three tricks cover most of the design space.

Overlabels also stamps every control with an automatic companion: `c:key_at` is the Unix timestamp of the
last change to `c:key`. This is what turns `latest()` into a cross-service race.

### Elapsed seconds since an event

Any control's `_at` companion is a Unix timestamp you can subtract `now()` from. Pick any event-driven
control - for example, the one that stores the latest follower - and you can show how long ago it fired.

```
now() - t.followers_latest_date_at
```

Pipe the result through `|duration:mm:ss` and you have a "last follow was N minutes ago" display built
from subtraction alone.

## 3. Waves from trigonometry

The canonical animation primitive. A sine wave with amplitude \(A\), period \(T\), and baseline \(C\):

$$y(t) = A \sin\!\left(\frac{2\pi t}{T}\right) + C$$

Map that one formula to controls and you have a breathing badge, a pulsing circle, a lighthouse sweep, or
a subtle bob. The pattern:

```
// 1 Hz pulse, mapped to 0..1 (use as opacity / scale normaliser)
0.5 + 0.5 * sin(2 * PI * now())

// Slow breathe, ±5% around 1.0, period 6 s
1 + 0.05 * sin(2 * PI * now() / 6)

// Lighthouse sweep, 0..1 once every 8 s (always positive)
abs(sin(PI * now() / 8))
```

The generalised *remap* from \([-1, 1]\) into any range \([\text{lo}, \text{hi}]\) is a template worth
memorising:

$$\text{remap}(s) = \text{lo} + (\text{hi} - \text{lo}) \cdot \tfrac{1}{2}\!\left(s + 1\right)$$

### Lissajous figures on two controls

Drive an X offset with `sin` and a Y offset with `cos` at different frequencies. Two control expressions,
one orbit:

```
// c:orbit_x
40 * sin(2 * PI * now() / 5)

// c:orbit_y (3:2 frequency ratio -> a classic Lissajous)
40 * cos(2 * PI * now() / 7.5)
```

## 4. Sawtooth, ramps, and `fract()`

`fract(x) = x - floor(x)`. It discards the integer part and keeps the fraction. Feed it a rising quantity
and you get a *sawtooth*: a 0 → 1 ramp that snaps back to zero forever.

$$\text{fract}(x) = x - \lfloor x \rfloor \quad\in [0,\,1)$$

```
// 10-second loop, ramps 0 -> 1
fract(now() / 10)

// Same loop, reversed: 1 -> 0
1 - fract(now() / 10)

// Triangle wave via abs of a shifted sawtooth: 0 -> 1 -> 0 every 4 s
abs(2 * fract(now() / 4) - 1)
```

The triangle trick deserves its own line. Start with a sawtooth, scale it to \([0, 2]\), subtract 1 to
centre on zero, then take the absolute value. You just built a piecewise-linear tent function from two
primitives.

## 5. Decoded: the pseudo-random one-liner

This expression returns a seemingly random integer from 1 to 9, changing twice per second:

```
floor(fract(sin(now() / 2) * 1000) * 9) + 1
```

It is a variant of the classic shader-language pseudo-random trick \(\text{fract}(\sin(x) \cdot k)\). It
is not cryptographic - do not roll dice in a contract with it - but for visual sparkle it is beautiful.
Let us take it apart.

1. `now() / 2` - time, but ticking in half-second units. Any monotonically-rising value works here.
   Dividing slows the churn.
2. `sin(...)` - maps the growing input into \([-1, 1]\). On its own, too smooth to be random.
3. `... * 1000` - scales that smooth wave up. The *integer part* of the result is now big and varied; the
   *fractional part* is where the chaos lives. Multiplying by a large number amplifies how fast the
   fraction tumbles as \(x\) changes.
4. `fract(...)` - throws away the integer part and keeps only the chaotic tail. The output is now in
   \([0, 1)\) and, from the user's perspective, indistinguishable from noise.
5. `... * 9` - stretches that unit-interval noise into \([0, 9)\).
6. `floor(...) + 1` - snaps to an integer in \(\{0, 1, \ldots, 8\}\), then shifts to
   \(\{1, 2, \ldots, 9\}\).

Equivalent formulation, in case you prefer to read it in math:

$$r = \left\lfloor 9 \cdot \text{fract}\!\Big(1000 \cdot \sin\!\big(\tfrac{t}{2}\big)\Big) \right\rfloor + 1, \quad r \in \{1, \ldots, 9\}$$

### Variants

```
// Uniform-ish [0, 1) noise (no integer snap)
fract(sin(now()) * 43758.5453123)

// Roll a 20-sided die every 3 seconds
floor(fract(sin(floor(now() / 3)) * 9999) * 20) + 1

// "Pick one of three overlays" every 10 s, using mod
mod(floor(fract(sin(floor(now() / 10)) * 9999) * 3), 3)
```

Note the `floor(now() / N)` trick: quantising time before you sin it turns a continuously-changing value
into a step function. The "random" output then stays stable for *N* seconds before jumping, which is what
you actually want for most UI.

## 6. The modulo wheel

`mod(a, b)` in Overlabels is *floor*-modulo, the one mathematicians wrote on the chalkboard:

$$\text{mod}(a, b) = a - b \cdot \left\lfloor \tfrac{a}{b} \right\rfloor$$

Always non-negative when \(b > 0\), even for negative \(a\). Contrast with the JS `%` operator, which
preserves sign. Use `mod` when you are indexing something cyclic.

```
// Cycle 0 -> 1 -> 2 -> 0 every 5 s
mod(floor(now() / 5), 3)

// Cycle through the days of the year (day-of-year)
mod(floor(now() / 86400), 365)

// Ping-pong 0 -> 1 -> 0 smoothly: triangle then normalise
abs(2 * fract(now() / 6) - 1)
```

Pair `mod` with a conditional to rotate overlay text:

```
// c:banner_index =>
mod(floor(now() / 8), 3)

// In HTML:
[[[if:c:banner_index = 0]]]Welcome, [[[channel_name]]]![[[endif]]]
[[[if:c:banner_index = 1]]]Follow to join [[[followers_total]]]+ friends.[[[endif]]]
[[[if:c:banner_index = 2]]]!commands for the full list.[[[endif]]]
```

## 7. Clamp, round, abs: the cleanup crew

The engine's cleanup functions exist so you can pipe raw inputs into CSS without worrying about extremes,
floats, or negative values.

### Clamp as a saturation limiter

```
// Hype meter: 0..100, never overshoots, never negative
clamp(0, c.cheer_bits / 100, 100)
```

### Round for display, keep precision internally

Trig output has 15 decimal places you never want to show. Round at the edge of the UI.

```
// Win rate as a clean percentage
round(c.wins / (c.wins + c.losses) * 100, 1)
```

### abs(sin) as a one-sided wave

Taking the absolute value of a sine folds the negative half up. You get twice the frequency visually and
a lighthouse-style pulse that never dips below zero. Great for "intensity".

## 8. Winners and timestamp racing

This is the trick the rest of the streaming ecosystem does not have. Every control in Overlabels has an
automatic `_at` companion that stores *the Unix timestamp of its last change*. That means you can race
signals:

$$\text{most\_recent\_donor} = \underset{s \in \text{sources}}{\operatorname{argmax}}\ t_{s}$$

```
// Who tipped most recently - Ko-fi, Streamlabs, or Fourthwall?
latest(
  c.kofi.latest_donor_name_at, c.kofi.latest_donor_name,
  c.streamlabs.latest_donor_name_at, c.streamlabs.latest_donor_name,
  c.fourthwall.latest_donor_name_at, c.fourthwall.latest_donor_name
)
```

The value at each odd position is a timestamp; the even position next to it is the label you want
returned. `latest()` picks the biggest timestamp and returns its paired label. `oldest()` / `argmin()` do
the opposite - perfect for "slowest response", "first to arrive", "longest since".

### Sum across services

```
// Unified donation counter
c.kofi.donations_received + c.streamlabs.donations_received + c.fourthwall.donations_received

// Unified total received amount
c.kofi.total_received + c.streamlabs.total_received + c.fourthwall.total_received
```

### "Is this subscriber actually a gift?"

Because `t.subscribers_latest_is_gift` is a boolean stamped by the `channel.subscribe` EventSub rule, you
can build sentiment directly:

```
// Who to thank for the most recent sub
t.subscribers_latest_is_gift
  ? t.subscribers_latest_gifter_name + " gifted a sub to " + t.subscribers_latest_user_name
  : t.subscribers_latest_user_name + " just subscribed"
```

The ternary is your friend. Chain them for switch-like behaviour: `a ? x : b ? y : z`.

## 9. Live Twitch values: the `t.*` namespace

The `t.*` namespace exposes every tag that EventSub mutates - follower totals, the latest cheer user,
peak raid viewers, the latest sub's gift flag, and so on - directly in expressions. These are *live*:
when a follow fires, `t.followers_total` increments and any expression that reads it re-evaluates on the
next tick.

The relationship to static `[[[tag]]]` syntax is simple: the same values that appear under
`[[[followers_total]]]` in your HTML also appear under `t.followers_total` in expressions. HTML tags are
resolved once at render; expression values stay reactive forever.

### Progress to the next milestone

A horizontal progress bar that fills from 0 to 100 as your follower count approaches the next thousand,
then snaps back to zero and starts climbing again:

```
// c:milestone_pct ->
clamp(0, (t.followers_total - floor(t.followers_total / 1000) * 1000) / 10, 100)
```

That is \(\text{pct} = \frac{F \bmod 1000}{10}\) wearing a clamp guard. Wire it into CSS:
`style="width: [[[c:milestone_pct]]]%"`.

### Fade in the latest follower's name

Every tag has an automatic `_at` Unix timestamp companion. Combine it with `now()` and `clamp` to get a
two-second fade-in on every new follow:

```
// c:greet_opacity ->
clamp(0, (now() - t.followers_latest_user_name_at) / 2, 1)
```

### Greeting copy that switches on the event shape

```
// c:greet_text ->
t.subscribers_latest_is_gift
  ? "Thanks " + t.subscribers_latest_gifter_name + " for gifting a sub to " + t.subscribers_latest_user_name + "!"
  : "Welcome " + t.subscribers_latest_user_name + "!"
```

### Raid hype meter

```
// Scale from 0..1 based on peak raid size, saturating at 500 viewers
// c:raid_hype ->
clamp(0, t.last_raid_viewers_peak / 500, 1)

// "who raided me" label, or empty if no raid yet
// c:raid_label ->
t.last_raid_from ? t.last_raid_from + " raided with " + t.last_raid_viewers_peak + " viewers" : ""
```

> [!NOTE]
> `[[[tag]]]` syntax does not work inside expression strings - expressions never reparse template-tag
> syntax, and template tags never evaluate expressions. Use `t.tag` in expressions, `[[[tag]]]` in HTML.
> That separation is how the engine stays secure.

## 10. Pitfalls and things that will not work

### Time resolution: `now()` vs `now_ms()`

The engine runs a shared 250 ms ticker that re-evaluates any expression containing `now()` or `now_ms()`,
so time-based formulas update on their own - no heartbeat control needed. But the *source value* decides
the resolution:

- `now()` returns *integer* seconds. Even though the ticker fires 4x a second, `now()` returns the same
  number for four ticks in a row, so anything derived from it only visibly changes every 1 s. Perfect for
  clocks, uptimes, banner rotations.
- `now_ms()` returns milliseconds. Use it when you want sub-second motion, e.g.
  `mod(floor(now_ms() / 250), 3)` to cycle 4x a second, or `sin(now_ms() / 500)` for a smooth ~3 Hz wave.

For continuous visual motion (opacity pulses, CSS transforms) prefer CSS animations - they run at the
browser's frame rate. Use expressions for *discrete* time-driven state that other parts of the overlay
react to.

### Radians, not degrees

`sin(90)` is not 1. It is \(\sin(90 \text{ rad}) \approx 0.894\). Use
\(\theta_{\text{rad}} = \theta_{\text{deg}} \cdot \pi / 180\) or just work in multiples of `PI` directly.

### No exponentiation operator

`x ** 2` and `x ^ 2` do nothing useful. For \(x^2\) write `x * x`. For \(x^3\) write `x * x * x`. There
is also no `log` or `exp` - the function whitelist is deliberately small for security and bundle size.
`sqrt` and the full trig surface (`tan`, `asin`, `acos`, `atan`, `atan2`) are available for spatial math.

### Division by zero returns zero

The engine swallows \(x / 0\) and returns `0` instead of `Infinity`. This is deliberate: an overlay
should never crash on a zero denominator. Write defensively anyway - `c.wins / (c.wins + c.losses)`
returns `0` on the fresh account, not the \(\text{NaN}\) you might expect.

### Odd argument count in arg-family

`latest(a, b, c)` with three arguments returns the literal error string
`"⚠ Odd argument count - needs value, label pairs"`. The engine is telling you to pair every value with a
label.

### Floating-point sins

\(0.1 + 0.2 = 0.30000000000000004\). When rendering, round at the edge: `round(expr, 2)` or the
`|round:2` pipe. Never compare floats with `==` - use `abs(a - b) < 0.001`.

## Now go build something weird

The entire engine fits in one file - `resources/js/composables/useExpressionEngine.ts` - and the whole
whitelist is readable in about ten seconds. Every function above is a primitive you can combine. The real
power is in what you chain together.

Want the companion pages? [Controls](/help/controls), [Conditionals](/help/conditionals),
[Formatting Pipes](/help/formatting).
