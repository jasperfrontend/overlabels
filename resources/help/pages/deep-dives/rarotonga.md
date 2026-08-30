---
title: Inside Rarotonga - an Overlabels deep dive
description: A teardown of the Rarotonga overlay - a real OpenStreetMap coastline driven five ways by one path, and live great-circle distance and bearing computed by a chain of haversine Expression Controls.
heading: Inside Rarotonga
lead: A starting-soon scene built around the real coastline of a South Pacific island - a light lapping the coast road, the outline rippling outward, and a compass that knows your home is 16,342 km away. This teardown covers both halves - the one-path map and the haversine chain - with no JavaScript anywhere.
canonical: https://overlabels.com/help/deep-dives/rarotonga
keywords: rarotonga, haversine, great circle, bearing, atan2, distance, latitude, longitude, coordinates, offset-path, motion path, stroke-dasharray, coastline, map, openstreetmap, compass, irl
---

Deep dives take one real overlay apart and explain every control and every trick in it. The
overlay this one dissects is live and copyable:
[Rarotonga](https://overlabels.com/overlay/elbrus-tagus-patmos-ohrid/public), with a
[machine-readable `.md` twin](https://overlabels.com/overlay/elbrus-tagus-patmos-ohrid/public.md)
holding the complete source and all 14 controls.

The [Math Engine Showcase deep dive](/help/deep-dives/math-engine-showcase) taught the arithmetic
vocabulary; this overlay uses almost none of it. It has one `fract()` sawtooth and four
phase-shifted copies, and spends the rest of its budget on two things the other deep dives never
touch: real geographic data as CSS geometry, and genuine spherical trigonometry running live in
[Expression Controls](/help/expressions).

## The one-sentence version

One pre-baked SVG path of Rarotonga's actual coastline is rendered five different ways - fill,
stroke, ripple, comet tail and motion track - while a chain of five expression controls turns
four editable coordinates into a great-circle distance and compass bearing, recomputed every
frame and correct on a sphere.

## The roots

| root | kind | feeds |
|---|---|---|
| `now_ms()` | the frame clock | the coast lap and the four ripple echoes |
| `c.home_lat`, `c.home_lng` | number controls (`51.9225`, `4.4792` = Rotterdam) | the whole geometry chain |
| `c.raro_lat`, `c.raro_lng` | number controls (`-21.2333`, `-159.7727`) | the whole geometry chain |
| `[[[channel_name]]]` | live Twitch data | the starting-soon line |

That is the entire outside world: a clock, four numbers and a name. The four coordinates are the
point of the design - they are plain number controls, so anyone who copies the overlay opens the
dashboard, types their own home coordinates, and the distance and compass re-aim instantly. No
formula editing required.

## The coastline is data

The island is not drawn - it is surveyed. The path data comes from OpenStreetMap's coastline for
Rarotonga, converted once, offline, into a ~6.8 KB SVG path (plus four small `motu` paths for the
islets in the lagoon). That one geometry then works five jobs:

1. `.land` - the same path with a faint fill, the island's body.
2. `.coast` - the same path stroked, the shoreline.
3. `.echo` x4 - the same path scaled and faded, the ripple (below).
4. `.comet` - the same path with a dash trick, the light's tail (below).
5. `.rider` - the same path again, this time as a *track* for a moving element.

One dataset, five renderers. Nothing in the overlay draws the island twice differently, so the
ripple is exactly island-shaped and the light never leaves the road.

## The rider and its tail

The motion half is one control:

```
lap = fract(now_ms() / 40000)
```

A 0-to-1 sawtooth, one lap every 40 seconds. It drives two completely different CSS subsystems
that stay in sync because they read the same number:

The **rider** uses CSS Motion Path. The coastline is its `offset-path`, and

```css
offset-distance: calc(var(--lap) * 100%);
```

walks the dot along the real coast road - the browser does the arc-length math along all the
path's twists for free. `offset-rotate: 0deg` keeps the glow upright instead of banking into
corners.

The **tail** is the same coastline drawn once more as a stroked path, with the oldest trick in
SVG line animation:

```css
stroke-dasharray: 90 2565.9;
stroke-dashoffset: calc(90px - var(--lap) * 2655.9px);
```

One 90 px dash followed by a gap covering the rest of the path (the coastline measures 2655.9 px;
2565.9 is that minus the dash). As `--lap` runs 0 to 1, the dash offset slides exactly one
perimeter, so the lit segment chases around the island - positioned in absolute pixels along the
very path the rider is walking in percent. Two units, one clock, zero drift.

This is the third distinct motion-trail technique in three deep dives, for the collection: the
bowling lane never needed one, the Showcase used transition lag (the ghosts) and phase-shifted
clones (the spirograph arms), and this one uses a sliding dash.

## The ripple

Four controls, one formula, four phases:

```
ring_0 = fract(now_ms() / 7000)
ring_1 = fract(now_ms() / 7000 + 0.25)
ring_2 = fract(now_ms() / 7000 + 0.5)
ring_3 = fract(now_ms() / 7000 + 0.75)
```

Each echo copy of the coastline scales outward and fades as its ring value grows:

```css
transform: scale(calc(1 + var(--r) * 0.6));
opacity: calc((1 - var(--r)) * 0.3);
```

A sonar ping, except the wavefront is shaped like the island instead of a circle, because it IS
the island's outline. The sawtooth wrap (1 snapping back to 0) would be a visible jump, but the
opacity formula reaches zero exactly at the wrap - the echo is invisible at the only moment it
teleports. Quarter-phase spacing keeps a wave always mid-flight.

## From coordinates to kilometres

Now the geometry half, and the reason this overlay exists. The distance is not typed in - it is
computed, live, from the four coordinate controls, by the haversine formula split across a chain
of expression controls:

```
dlat  = (c.raro_lat - c.home_lat) * PI / 180
dlng  = (c.raro_lng - c.home_lng) * PI / 180
hav_a = sin(c.dlat / 2) * sin(c.dlat / 2)
      + cos(c.home_lat * PI / 180) * cos(c.raro_lat * PI / 180)
      * sin(c.dlng / 2) * sin(c.dlng / 2)
dist_km = 6371 * 2 * atan2(sqrt(c.hav_a), sqrt(1 - c.hav_a))
```

Reading it from the top:

- `dlat` and `dlng` are just the coordinate differences converted to radians, because the trig
  functions eat radians.
- `hav_a` is the heart. The "haversine" of an angle is `sin²(angle / 2)`, and the formula says:
  the haversine of the angle between two points on a sphere equals the haversine of the latitude
  difference, plus the haversine of the longitude difference scaled down by both cosines of
  latitude. That last part is the intuition worth keeping: a degree of longitude shrinks as you
  leave the equator (the meridians converge), and `cos(lat)` for each endpoint is exactly that
  shrink factor. The half-angle `sin²` form is used instead of plain cosines because it stays
  numerically sharp for nearby points, where cosine-based formulas dissolve into rounding error.
- `dist_km` converts `hav_a` back into an angle with `2 * atan2(sqrt(a), sqrt(1 - a))` - think
  of `sqrt(a)` and `sqrt(1 - a)` as the opposite and adjacent sides of the half-angle - and
  multiplies by Earth's mean radius, 6371 km. The result: the angle between Rotterdam and
  Rarotonga through the Earth's centre is 147 degrees, or **16,342 km** along the surface. Not
  quite the maximum possible - the antipode would be 20,015 km - but a decent effort.

Notice what the chain is doing structurally. A jsep expression is a single expression - no
statements, no local variables - so `dlat`, `dlng` and `hav_a` are the local variables, hoisted
into named controls. Chained controls are the math engine's `let` bindings, and as a bonus every
intermediate value is inspectable on the dashboard while you debug.

## The bearing

The compass needle is one expression, and it is a unit worth framing:

```
bearing = mod(atan2(sin(c.dlng) * cos(c.raro_lat * PI / 180),
               cos(c.home_lat * PI / 180) * sin(c.raro_lat * PI / 180)
             - sin(c.home_lat * PI / 180) * cos(c.raro_lat * PI / 180) * cos(c.dlng))
          * 180 / PI, 360)
```

(One line in the overlay - the longest single expression in any Overlabels overlay to date.)

It is less scary than it looks. `atan2(east, north)` is "which way is that?": the first argument
is the eastward component of the direction you would set off in, the second is the northward
component, both evaluated at the departure point. `atan2` turns the pair into an angle, `* 180 /
PI` makes it degrees, and the outer `mod(..., 360)` folds `atan2`'s -180..180 range into a
0..360 compass rose where 0 is north and clockwise is positive. The needle then just rotates:

```css
.needle { transform: rotate(var(--bearing)); }
```

And here the sphere shows off. Rarotonga sits far to the south-west on a flat map - naive
arithmetic on the raw coordinate differences points the needle at roughly 246°, west-south-west.
The great circle disagrees: the shortest path out of Rotterdam leaves at **332°,
north-north-west**, up past Iceland and over the top of the planet, because on a globe that is
genuinely shorter than ploughing across Africa and the Pacific. A compass that points
"the wrong way" and is right is the whole reason to do the trigonometry properly.

One more thing hiding in plain sight: nothing in the geometry chain uses the clock. The engine
re-evaluates these expressions every frame and gets the same answer every frame - until the
moment someone edits `home_lat` on the dashboard, and the distance, the needle and the text all
move at once. Constant output, live inputs. Recomputing a constant costs nothing, and the payoff
is that the overlay has no idea its numbers are "static".

## Reading the export

- All four coordinate controls plus `dlat`, `dlng` and `hav_a` are marked *"Referenced in
  source: no"*. All seven are alive - they are the interior of the chain, read only by other
  controls' expressions. Only the two ends (`dist_km`, `bearing`) and the five motion controls
  appear in the markup and CSS.
- The coastline path text appears seven times in the source (land, coast, four echoes, comet)
  plus once more inside `offset-path` - roughly 48 KB of coordinates all told, which is why this
  overlay takes a beat longer to load than the others. A fair price for real geography; it is
  paid once, at load, and costs nothing per frame.
- The path length `2655.9` is hardcoded in two places in the CSS (`stroke-dashoffset` and inside
  `stroke-dasharray` as `2565.9`, minus the 90 px dash). If you ever swap in a different
  coastline, those two numbers must be re-measured or the tail will lap at the wrong speed.
- The facts line (67.39 km², Te Manga 658 m, Ara Tapu 32 km, UTC-10) is hand-written text, not
  data. Only the distance, the bearing and `[[[channel_name]]]` are live.

## Build it yourself

Open the [overlay's public page](https://overlabels.com/overlay/elbrus-tagus-patmos-ohrid/public)
while logged in and press **Copy**. Everything comes with it - no Lists, no integrations, no
foreach caps. Then change `home_lat` and `home_lng` to your own coordinates on the dashboard and
watch the compass swing. The general haversine recipe, ready to adapt to any pair of points, is
also written up in the [Expression Controls guide](/help/expressions).

The reusable idea this one teaches: real-world data can BE the stylesheet - one surveyed path
serving as body, outline, ripple, trail and track - and a chain of controls is how you write a
formula too big for one line, with every intermediate step named, editable and inspectable.
