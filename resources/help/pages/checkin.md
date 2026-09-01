---
title: Chat Checkin - pin your viewers on a 3D globe - Overlabels
description: Let viewers pin themselves on your overlay with !checkin. A 3D globe, counters, distances, alerts and a raw pin feed, all from one chat command.
heading: Chat Checkin
lead: Viewers type !checkin Rotterdam, NL and land on your globe. One command, one pin per viewer, city-level only.
keywords: checkin, check in, globe, world map, pins, map, viewers map, checkin globe, where is chat
---

Connect Chat Checkin at [Settings - Integrations - Chat Checkin](/settings/integrations/checkin)
(the Overlabels bot must be enabled in your channel) and viewers get one chat command:

```
!checkin Rotterdam, NL
!checkin Barcelona
!checkin Tokyo
```

The place resolves against a local city index - about 235,000 cities worldwide, matched by name
with typo tolerance ("amsterdamm" still lands) and ranked by population when a name is ambiguous
(bare "Paris" is Paris, France; "Paris, US" is Paris, Texas). A place that will not resolve gets a
friendly reply and nothing else. Each viewer has ONE pin: checking in again moves it.

> [!NOTE]
> Everything is city-level by design. The index contains nothing finer than a city, so nobody can
> pin a street address onto your stream - not even on purpose. Coordinates on a pin are the
> coordinates of the resolved city.

## The globe

Drop the globe into any static overlay with one tag:

```
[[[checkin_globe]]]
```

It renders a slowly rotating dotted globe - the continents are drawn from the city index itself -
with a pin and a name label for every checkin. Size it like any element and style it with CSS
custom properties on `.ol-checkin-globe`:

```css
.ol-checkin-globe {
  width: 720px;
  height: 720px;
  --globe-dot-color: #c9a227; /* land dots */
  --globe-dot-size: 2.4; /* px */
  --globe-pin-color: #bfe3e0; /* pin heads and stalks */
  --globe-rotation-seconds: 45; /* one revolution; 0 = still */
  --globe-tilt-degrees: 18;
}
.ol-globe-label {
  color: #dfe8e8;
  font-size: 13px;
}
```

The labels are ordinary HTML with the class `ol-globe-label` (plus `is-hidden` while a pin is on
the far side), so your template's CSS owns them completely. The 3D library only downloads on
overlays that actually contain the tag.

## Pin lifetime

On the settings page you choose what a pin's life looks like:

- **Per stream** - the globe starts fresh at go-live and chat checks in again. That ritual is half
  the fun of the command.
- **Persistent** - pins accumulate across streams into a world map of your community.

## Controls

Connecting provisions these, usable anywhere as `[[[c:checkin:...]]]` and in Expression Controls:

- `checkins_this_stream`, `unique_countries_this_stream`, `farthest_checkin_km_this_stream` -
  per-stream, reset at go-live
- `checkins_total` - all time
- `latest_checkin_name`, `latest_checkin_place`, `latest_checkin_country`, `latest_checkin_lat`,
  `latest_checkin_lng`, `latest_checkin_distance_km` - the most recent pin, persists across streams

Distances need a home location (your own city, set on the settings page). Set it and every checkin
gets its haversine distance from home - `farthest_checkin_km_this_stream` makes a great
"who came furthest" callout.

## The raw pin feed

Prefer to build your own visualization? The pins are a foreach iterable, newest first:

```
[[[foreach:checkins as pin]]]
  <div>[[[pin.name]]] - [[[pin.place]]] ([[[pin.distance_km]]] km)</div>
[[[endforeach]]]
```

Fields: `name`, `login`, `place`, `country`, `country_code`, `lat`, `lng`, `at` (Unix seconds),
`distance_km` (empty without a home location). `[[[checkins.count]]]` is the total in the current
window. The number of pins kept is a foreach cap on [Settings - Account](/settings/account),
default 50.

## Alerts

"Chat Checkin" is a trigger like any other: bind an alert template to it and every checkin can
fire an alert, TTS or chat message using the [[all-chat-checkin-events]] event tags -
"[[[event.user_name]]] joined from [[[event.place]]]!".

Place data by [GeoNames](https://www.geonames.org/) (CC BY 4.0).
