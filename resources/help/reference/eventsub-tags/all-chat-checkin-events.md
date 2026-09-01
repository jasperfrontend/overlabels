available on the `checkin` event, fired when a viewer runs `!checkin <place>` in chat and the place resolves.

- `[[[event.user_name]]]` :: Display name of the viewer who checked in
- `[[[event.user_login]]]` :: Login of the viewer who checked in
- `[[[event.place]]]` :: The resolved place label, e.g. "Rotterdam, NL"
- `[[[event.country]]]` :: Country name in English, e.g. "Netherlands"
- `[[[event.country_code]]]` :: Two-letter country code, e.g. "NL"
- `[[[event.lat]]]` :: City latitude in decimal degrees
- `[[[event.lng]]]` :: City longitude in decimal degrees
- `[[[event.distance_km]]]` :: Distance in km from your home location :: empty when no home location is set

Every value arrives as a string. `event.distance_km` is empty unless you set a home location on the Chat Checkin settings page - use `??` for a fallback: `[[[event.distance_km ?? 0]]]`.

### Coordinates are city-level
`event.lat` and `event.lng` are the coordinates of the resolved CITY, never of the viewer. Checkins resolve against a city gazetteer, so nothing finer than a city can appear here - a viewer cannot pin an address onto your stream even on purpose.

### Tags and controls answer different questions
The event tags are the single checkin that just happened; the `c:checkin:` controls are the running state: `c:checkin:checkins_this_stream`, `c:checkin:unique_countries_this_stream`, `c:checkin:farthest_checkin_km_this_stream`, `c:checkin:checkins_total`, plus the `latest_checkin_*` set that mirrors these tags.

example:
```
<div class="checkin-alert">
  [[[event.user_name]]] joined from [[[event.place]]]
  [[[if:event.distance_km]]]<span>[[[event.distance_km]]] km away</span>[[[endif]]]
</div>
```

note: Chat Checkin is an Overlabels integration (not Twitch EventSub). Events arrive from the Overlabels bot relaying the `!checkin` chat command; there is no public webhook for this service. Place data by GeoNames (CC BY 4.0).
