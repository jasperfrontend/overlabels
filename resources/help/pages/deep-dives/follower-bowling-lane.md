---
title: Inside the Follower bowling lane - an Overlabels deep dive
description: A line-by-line teardown of the Follower bowling lane overlay - how one List removal timestamp becomes a chat queue, a weighted dice roll and a full bowling animation with no JavaScript anywhere.
heading: Inside the Follower bowling lane
lead: A chatter types !bowl, a mod draws a name, and a bowling ball knocks over your ten latest followers. This is a teardown of how one timestamp powers the whole thing - the queue, the dice roll and the animation - without a single line of JavaScript.
canonical: https://overlabels.com/help/deep-dives/follower-bowling-lane
keywords: deep dive, bowling, minigame, chat game, queue, raffle, pop, draw, seed, pseudo-random, deterministic, shader noise, now_ms, timeline, choreography
---

Deep dives take one real overlay apart and explain every control and every trick in it, so you can
steal the techniques for your own. This is the first one. The overlay it dissects is live and
copyable: [Follower bowling lane](https://overlabels.com/overlay/tiber-rakahanga-bologna-apennine/public),
and like every public overlay it has a
[machine-readable `.md` twin](https://overlabels.com/overlay/tiber-rakahanga-bologna-apennine/public.md)
holding the complete source and all twenty controls. Keep that open in a tab - everything below
quotes from it.

You will want to know [Expression Controls](/help/expressions) and [Lists](/help/lists) to follow
along, and the [Math Engine guide](/help/math) covers the arithmetic tricks in a more general way.

## The one-sentence version

There is no JavaScript and no random number generator anywhere. The Unix timestamp of the moment a
mod pops or draws someone off the `lane` List is simultaneously the **stopwatch** (everything
animates as a function of "seconds since that stamp") and the **dice roll** (the stamp is hashed
into a pseudo-random outcome). Twenty expression controls turn that one number into CSS custom
properties, and plain CSS does the rest.

## The trigger chain

1. `!bowl` is a bot command of type *append to a List*, with value `[[[bot:from_user]]]` - it
   pushes the chatter's own name onto the `lane` List. The queue panel on the left just reads
   `[[[c:list:lane:count]]]` and loops the first three names with a `foreach`.
2. `!list lane pop first` (take the front of the queue) or `!list lane draw` (raffle) removes one
   entry. A List remembers what pop and draw took out: the removal broadcasts two companion
   controls, `[[[c:list:lane:last_removed]]]` (the name) and `[[[c:list:lane:last_removed_at]]]`
   (a Unix timestamp in seconds).
3. The first expression control, `bowl_t`, is just an alias for that timestamp:

   ```
   c.list["lane:last_removed_at"]
   ```

   Every other control derives from it. That timestamp is the only *event* the overlay ever
   receives - the rest is arithmetic.

## The clock: animation without JavaScript

`bowl_age` is where the overlay gets a pulse:

```
(now_ms() - c.bowl_t * 1000) / 1000
```

Because it uses `now_ms()`, the expression engine re-evaluates it continuously, so `--age` (and
everything derived from it) ticks every frame. That is a requestAnimationFrame loop, smuggled in
through a formula. Every visibility flag, the ball position, each pin's fall: all pure functions
of `bowl_age`, with no state stored anywhere.

The `3.5` you will see in the expressions below is a delivery buffer. The removal broadcast goes
through the queue on the server (the worker can sleep for a few seconds) and the stamp is whole
seconds, so the broadcast can land up to about 3.5 seconds "in the past". Nothing visible starts
until `age > 3.5`, which is why the ball sits at the foul line pulsing - it is stalling gracefully
while the pipeline catches up, disguised as a wind-up:

```
c.bowl_age < 3.5 ? 1 + 0.18 * abs(sin(c.bowl_age * 6)) : 1
```

That is `ball_pulse`, a scale factor that throbs while waiting and settles to exactly 1 the moment
the roll begins.

## The dice roll

`bowl_seed` turns the timestamp into a uniform-ish 0..1 number:

```
fract(sin(c.bowl_t * 0.001 + 78.233) * 43758.5453)
```

This is the classic shader-noise hash - the same one-liner graphics programmers use when a GPU has
no random function. Feed in a timestamp, get a number that is stable for this throw and unrelated
to the next one. Because it is deterministic, there is no server-side RNG and nothing to sync:
every render of the overlay computes the identical result from the same timestamp, and a replay of
the same throw always lands the same way.

`bowl_knocked` maps the seed onto an outcome:

```
c.bowl_seed < 0.12 ? 0 : (c.bowl_seed > 0.85 ? 10
  : 1 + floor(8.999 * acos(1 - 2 * (c.bowl_seed - 0.12) / 0.73) / PI))
```

The bottom 12% of seeds is a gutter ball, the top 15% a strike, and the middle 73% goes through
`acos(1 - 2u) / PI` - inverse-transform sampling of a sine-shaped distribution, so 4-6 pins are
common and exactly 1 or 9 pins are rare. A flat `floor(seed * 9)` would have felt wrong; this
feels like bowling.

One detail worth noticing in the export: `bowl_seed` is marked *"Referenced in source: no"*. It
never appears in the HTML or CSS - it is read only by `bowl_knocked`, as `c.bowl_seed`. Controls
referencing controls is how the whole overlay forms a little dependency graph:
`bowl_t -> age / seed -> knocked -> ball, pins, score`.

## The timeline

Every stage is a gate on `bowl_age`. Read `a && age > x && age < y ? 1 : 0` as "on between second
x and second y":

| age (s) | what happens | driven by |
|---|---|---|
| 0 - 3.5 | bowler name appears, ball pulses at the foul line | `bowler_show`, `ball_pulse` |
| 3.5 - 5.1 | the roll: `ball_x` goes 0 to 1 over 1.6 s | `clamp((c.bowl_age - 3.5) / 1.6, 0, 1)` |
| 5.1 - 5.55 | pins topple, one every 50 ms, front to back | `pin_N`: `knocked > N && age > 5.1 + 0.05 * N` |
| 5.2 | the score fades in (STRIKE! / GUTTER / N PINS) | `bowl_show` plus an if/elseif in the HTML |
| 12 | every flag returns 0: ball vanishes, pins stand up | the `&& c.bowl_age < 12` clause in everything |

That last row is the reset. There is no "reset" control and nothing to clean up - once the age
passes 12, every expression evaluates to 0 again and the lane is idle until the next pop or draw
writes a fresh timestamp.

## The CSS half

The overlay's `:root` block is where expressions become pixels. Tags resolve inside CSS too, so
each control lands as a custom property:

```css
:root {
  --age: [[[c:bowl_age|round:2]]];
  --bx: [[[c:ball_x|round:3]]];
  --pin0: [[[c:pin_0]]];
  /* ...and so on for all twenty */
}
```

The expressions output plain numbers; CSS turns them into geometry.

- **The ball.** `left: calc(60px + var(--bx) * 840px)` moves it down the lane.
  `rotate(calc(var(--bx) * 1080deg))` gives it three full revolutions of spin for free - spin
  proportional to distance travelled. And on a gutter throw, `ball_y` is `c.ball_x * c.ball_x`: a
  parabola, so the ball curves off increasingly hard, scaled by `translateY` straight into the
  gutter.
- **The pins.** Each `pin_N` control is a binary 0 or 1, but the `.pin` rule declares
  `transition: transform 0.35s cubic-bezier(0.3, 1.4, 0.6, 1)` - an overshooting curve - so the
  instant flip becomes an animated topple with a little bounce. The `--fall` value drives a 78
  degree rotation, a shove sideways and a fade, and the 50 ms stagger between `pin_0` and `pin_9`
  is what sells it as a physical chain reaction.
- **The pins are your followers.** The pin rack is a `foreach` over `channel_followers`, capped at
  ten, each rendering an avatar and a name. So your ten latest followers literally get knocked
  down. Each pin picks up its own fall flag by index: `--fall: var(--pin[[[loop.index]]])`.

## Reading the export

Two things in the `.md` export are easy to misread, and worth knowing about for any overlay you
inspect this way:

- The *"Live data tags used"* section lists `[[[else]]]`, `[[[endforeach]]]`, `[[[loop.index]]]`
  and friends. Those are template language keywords the exporter's tag extractor picked up, not
  data. The real data tags here are the three `f.*` follower fields.
- The root element carries `data-last-throw="[[[c:list:lane:last_removed_at]]]"`. That is a change
  stamp: it gives the markup something that mutates on every throw, and doubles as a debugging
  handle when you inspect the overlay inside OBS.

## Build it yourself

Open the [overlay's public page](https://overlabels.com/overlay/tiber-rakahanga-bologna-apennine/public)
while logged in and press **Copy**. The source and all twenty controls come with it. Three things
do not, because they live outside the overlay:

1. A List with the slug `lane`, created under your dashboard's Lists page.
2. A `!bowl` bot command that appends to that List, with `[[[bot:from_user]]]` as the value.
3. Your followers `foreach` cap set to 10, on the account settings page, so all ten pins render.

Then have a mod run `!list lane pop first` or `!list lane draw`, and watch the ball roll.

The genuinely reusable idea: a broadcast timestamp is the only event this overlay ever receives,
and it is recycled three ways - as the trigger, as the clock origin, and as the entropy source.
Everything else is stateless arithmetic, replayed every frame.
