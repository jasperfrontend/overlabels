---
title: Inside the Math Engine Showcase - an Overlabels deep dive
description: A panel-by-panel teardown of the Math Engine Showcase overlay - epicycles, a spirograph, a sweeping clock, dice, six waveforms and a bouncing counter, all built from 28 expression controls and zero JavaScript.
heading: Inside the Math Engine Showcase
lead: A BRB scene where every moving part is an Expression Control with its formula printed under it. This teardown walks all six panels and names the five arithmetic moves they are built from - no JavaScript anywhere.
canonical: https://overlabels.com/help/deep-dives/math-engine-showcase
keywords: math engine, showcase, brb scene, epicycles, spirograph, hypotrochoid, waveforms, dice, mod, fract, floor, sine, noise, damped bounce, motion trail, comet tail, wall clock, oklch
---

Deep dives take one real overlay apart and explain every control and every trick in it. The
overlay this one dissects is live and copyable:
[Math Engine Showcase](https://overlabels.com/overlay/elba-elbe-bergen-rockies/public), with a
[machine-readable `.md` twin](https://overlabels.com/overlay/elba-elbe-bergen-rockies/public.md)
holding the complete source and all 28 controls. Keep it open in a tab - everything below quotes
from it.

It pairs well with the [Follower bowling lane deep dive](/help/deep-dives/follower-bowling-lane):
that one shows a single event driving a choreographed sequence, this one is the vocabulary lesson
behind it. The [Math Engine guide](/help/math) covers the same functions in general form, and
[Expression Controls](/help/expressions) explains the mechanism that evaluates them.

## The one-sentence version

This is a BRB scene with six live demos, and 24 of its 28 controls are pure functions of the wall
clock - the overlay receives almost nothing from outside. The complete list of inputs: `now_ms()`
and `now()` (the frame clock), one dashboard counter (`hype`) plus its automatic `_at` timestamp,
one hand-set number (`utc_offset`), and one live Twitch value (`t.followers_total`). Everything
else is arithmetic replayed every frame, and each panel prints its own formulas on screen - the
overlay is its own documentation.

## The roots

Where the bowling lane had a single trigger (a List removal), this overlay has four independent
roots feeding six panels:

| root | kind | feeds |
|---|---|---|
| `now_ms()` / `now()` | the frame clock | epicycles, spirograph, clock, dice, all six waves, the colour wheel |
| `c.hype` + `c.hype_at` | counter, bumped from the dashboard | the whole Live counter panel |
| `c.utc_offset` | number control (`2` = Amsterdam summer time) | the hour hand only |
| `t.followers_total` | live Twitch data inside an expression | the footer milestone bar |

That last one is worth pausing on. The footer's control is:

```
milestone_pct = mod(t.followers_total, 100)
```

A Twitch channel stat consumed *inside* an expression control, so the bar creeps toward the next
hundred followers on every follow - no event handling anywhere, just a value that moves.

## Epicycles

One master angle, everything chained off it:

```
orbit_t  = 2 * PI * now_ms() / 12000
planet_x = 105 * cos(c.orbit_t)
planet_y = 105 * sin(c.orbit_t)
moon_x   = c.planet_x + 26 * cos(8 * c.orbit_t)
moon_y   = c.planet_y + 26 * sin(8 * c.orbit_t)
```

`orbit_t` is radians, one lap every 12 seconds. The planet rides a 105 px circle around the sun,
and the moon is the planet's position plus a smaller circle spinning eight times faster -
textbook epicycles in two additions.

The comet tail behind the moon is the best CSS trick in the file. Six ghost dots all target the
*same* `(--mx, --my)` every frame, but each carries its own lag:

```css
.ghost {
  transform: translate(var(--mx), var(--my));
  transition: transform calc(var(--k) * 110ms) linear;
}
```

The longer-transition ghosts perpetually chase a target they never catch. A motion trail with
zero extra controls and no position history - the browser's transition interpolator IS the
memory.

## Spirograph

Two counter-rotating angles:

```
spiro_a = mod(now_ms() / 40, 360)
spiro_b = -c.spiro_a * 5 / 3
```

The arm turns at 25 degrees per second and the pen counter-rotates at 5/3 that speed, making the
traced curve a hypotrochoid with R=5, r=3 - the ratio is why the flower closes on itself. The
fourteen arm-and-pen pairs are not history, either: each copy offsets both angles by its index
(`--sa - i * 6deg`, `--sb + i * 10deg`), so the trail is phase-shifted clones of the same two
controls. And the faint full curve underneath is a pre-baked static SVG path - the pens trace
over a drawing that was computed once, offline.

## Clock

`mod()` as a wheel, three times:

```
clk_sec  = mod(now_ms() / 1000, 60) * 6
clk_min  = mod(now() / 60, 60) * 6
clk_hour = mod(now() / 3600 + c.utc_offset, 12) * 30
```

The one deliberate asymmetry: the second hand uses `now_ms()` so it sweeps smoothly, while the
minute and hour hands use whole-second `now()` - nobody can see a minute hand tick. `now()` is
UTC, so the hour hand borrows `utc_offset`, the only hand-set number in the overlay.

## Dice

The freeze-frame variant of the shader-noise hash:

```
dice_noise = fract(sin(floor(now() / 2)) * 9999)
dice       = floor(c.dice_noise * 6) + 1
dice_odd   = mod(c.dice, 2)
```

`floor(now() / 2)` quantizes time into 2-second steps *before* it enters `sin()`, so the
"random" value holds still for two seconds and then jumps - the same trick the bowling lane uses
for its throw seed, but driven by the clock instead of an event.

The pips are pure arithmetic on one variable:

```css
.pip.tl, .pip.br { opacity: clamp(0, calc(var(--dice) - 1), 1); }
.pip.tr, .pip.bl { opacity: clamp(0, calc(var(--dice) - 3), 1); }
.pip.ml, .pip.mr { opacity: clamp(0, calc(var(--dice) - 5), 1); }
.pip.c  { opacity: var(--odd); }
```

One diagonal pair lights from 2 up, the other from 4, the middle pair from 6, and the centre pip
is simply `dice_odd`. That is a correct die face for all six values out of four opacity rules.
`dice_fuse = fract(now_ms() / 2000)` rides the same 2-second wheel as a countdown bar, and the
die squashes on each roll via `max(0, 1 - var(--fuse) * 6)` - a pulse that lives only in the
first sixth of every period.

## Waveforms

The thesis panel: one clock, `t = now_ms() / 4000`, six shapes.

| shape | expression |
|---|---|
| sine | `0.5 + 0.5 * sin(2 * PI * t)` |
| lighthouse | `abs(sin(PI * t))` - the negative half folded up, never dips below zero |
| triangle | `abs(2 * fract(t) - 1)` |
| sawtooth | `fract(t)` - a ramp that snaps back |
| square | `fract(t) < 0.5 ? 1 : 0` - a ternary on the sawtooth |
| noise | `fract(sin(floor(now_ms() / 250)) * 43758.5453)` - the hash, stepping 4x per second |

Every bar is just `height: calc(var(--v) * 100%)`. This panel is the vocabulary the other five
are written in.

## Live counter

The bowling lane's entire engine, isolated as a demo. `hype` is a plain counter; every control's
free `_at` companion gives you the Unix second it was last written, and then:

```
hype_age   = (now_ms() - c.hype_at * 1000) / 1000
hype_pop   = 1 + 0.5 * max(0, 1 - c.hype_age / 1.5) * abs(cos(3 * PI * c.hype_age))
hype_flash = max(0, 1 - c.hype_age / 0.6)
hype_ring  = mod(c.hype, 10) / 10
```

`hype_pop` is a damped bounce: a linear envelope decaying over 1.5 seconds multiplied by a
rectified cosine, so the number springs and settles. The flash is a 0.6-second linear fade. The
ring is `mod` mapped onto `stroke-dashoffset`, with a CSS transition smoothing each tenth. As the
panel's own copy puts it: `_at` timestamps are physics.

One honest difference from the bowling lane: there is no delivery buffer here. A bump plays
whatever remains of the bounce envelope when the broadcast lands, which is fine for a spring and
would not be fine for a choreographed sequence - that is exactly why the bowling lane waits 3.5
seconds before rolling and this panel does not wait at all.

## The colour wheel

The quietest control in the overlay:

```
hue = mod(now_ms() / 120, 360)
```

One lap around the colour wheel every 43 seconds, feeding `oklch(... var(--hue))` everywhere -
borders, dots, the second hand, and both accents (the second offset by 120 degrees). The entire
scene slowly shifts palette without any element animating its own colour.

## Reading the export

- `orbit_t`, `dice_noise` and `utc_offset` are marked *"Referenced in source: no"* in the
  controls table. All three are alive - they are read by other controls' expressions
  (`c.orbit_t`, `c.dice_noise`, and `c.utc_offset` inside `clk_hour`), just never by the HTML or
  CSS directly.
- The Default column shows values like `340.73` for `clk_hour`. Those are snapshots of the
  expression's value at export time, not meaningful defaults - an expression control recomputes
  from its formula the moment it renders.

## The five moves

The reusable idea this overlay teaches, compressed: `mod` is a wheel, `fract` is a ramp, `floor`
is a hold, `sin`-of-a-`floor` is a dice cup, and a CSS `transition` pointed at a moving target is
free memory. Every panel here - and most overlay motion you will ever want - is some composition
of those five moves.
