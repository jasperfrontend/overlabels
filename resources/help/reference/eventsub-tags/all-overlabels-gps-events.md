available on events sent by the Overlabels GPS mobile app (location_update, session_start, session_end, settings_sync).

> [!WARNING]
> The Overlabels GPS Android app is still in active development and is **not available in the Play Store**. These tags are documented so the overlay side is ready, but you cannot install the app yet.

> [!CAUTION]
> `event.latitude` and `event.longitude` are precise device coordinates. Anything you render from them goes on stream, and a viewer reading them off your overlay knows where you physically are. If you stream from home, that is your home address. Decide deliberately what reaches the screen - rounding to a city, showing speed without position, or gating the whole block behind a boolean control you can flip.

### Location Tags
Sent on `location_update`, which is the event the app fires while tracking.

- `[[[event.latitude]]]` :: Latitude in decimal degrees
- `[[[event.longitude]]]` :: Longitude in decimal degrees
- `[[[event.speed]]]` :: Speed in metres per second :: divide by 1000 and multiply by 3600 for km/h
- `[[[event.bearing]]]` :: Heading in degrees, 0-360
- `[[[event.altitude]]]` :: Altitude as reported by the device
- `[[[event.accuracy]]]` :: Position accuracy in metres :: lower is better
- `[[[event.battery]]]` :: Phone battery percentage
- `[[[event.charging]]]` :: "1" when the phone is charging, otherwise "0"
- `[[[event.session_id]]]` :: ID of the tracking session this reading belongs to
- `[[[event.source]]]` :: Name of the platform ("Overlabels GPS")

Every value arrives as a string and is empty when the device did not report it, which is normal for `altitude` and `bearing` indoors or when stationary. Use `??` to supply a fallback: `[[[event.speed ?? 0]]]`.

### Session Tags
Sent on `session_start` and `session_end`. These carry no position at all - only the session being opened or closed.

- `[[[event.session_id]]]` :: ID of the session that started or ended
- `[[[event.source]]]` :: Name of the platform ("Overlabels GPS")

`settings_sync` is currently normalised through the same path as `location_update`, so it arrives with the location tag set rather than a shape of its own. Expect most fields on it to be empty.

### There is no `event.type` for GPS
Every other integration sets `[[[event.type]]]`; the GPS driver does not. Route on the mapping you assigned in the Alerts Builder rather than branching on the tag, because `[[[if:event.type = location_update]]]` will never match.

### Tags and controls use different names
The event tags above are not the same vocabulary as the `c:gps:` controls, and the two disagree on the position keys specifically:

| Event tag | Control |
|---|---|
| `[[[event.latitude]]]` | `[[[c:gps:lat]]]` |
| `[[[event.longitude]]]` | `[[[c:gps:lng]]]` |

The controls also carry values that never appear as event tags, because Overlabels derives them across readings rather than receiving them: `c:gps:distance`, `c:gps:session_distance`, `c:gps:session_duration`, `c:gps:session_max_speed`, `c:gps:session_avg_speed` and `c:gps:tracking`. Reach for the controls when you want running totals, and for the event tags when you want the single reading that just arrived.

example:
```
<div class="gps">
  [[[if:c:show_location]]]
    <span class="speed">[[[event.speed ?? 0]]] m/s</span>
    <span class="battery">[[[event.battery ?? 0]]]%</span>
    [[[if:event.charging]]]<span class="charging">charging</span>[[[endif]]]
  [[[endif]]]
</div>
```

note: Overlabels GPS is an Overlabels integration (not Twitch EventSub). Events arrive from the mobile app over the GPS webhook and are authenticated with a per-user token in the `X-GPSLogger-Token` header. The example above deliberately gates the whole block behind a boolean control so you can cut the feed from your dashboard mid-stream without editing the overlay.
