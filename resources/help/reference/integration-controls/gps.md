# Overlabels GPS controls

Overlabels GPS provisions 13 controls when you connect it. They are filled in automatically and stay up to date; you read them, Overlabels writes them.

Reference them anywhere a tag works, using the `c:gps:` prefix.

## Specific to Overlabels GPS

| Tag | Type | Default | Holds |
|---|---|---|---|
| `[[[c:gps:speed]]]` | number | `0` | GPS Speed (m/s) |
| `[[[c:gps:lat]]]` | text | empty | GPS Latitude |
| `[[[c:gps:lng]]]` | text | empty | GPS Longitude |
| `[[[c:gps:distance]]]` | number | `0` | GPS Distance (km, cumulative) |
| `[[[c:gps:bearing]]]` | number | `0` | GPS Bearing (degrees) |
| `[[[c:gps:accuracy]]]` | number | `0` | GPS Accuracy (meters) |
| `[[[c:gps:battery]]]` | number | `0` | Phone Battery (%) |
| `[[[c:gps:charging]]]` | boolean | `0` | Phone Charging |
| `[[[c:gps:tracking]]]` | boolean | `0` | GPS Tracking Active |
| `[[[c:gps:session_distance]]]` | number | `0` | GPS Session Distance (km) |
| `[[[c:gps:session_max_speed]]]` | number | `0` | GPS Session Max Speed (m/s) |
| `[[[c:gps:session_avg_speed]]]` | number | `0` | GPS Session Avg Speed (m/s) |
| `[[[c:gps:session_duration]]]` | number | `0` | GPS Session Duration (seconds) |

## Events that update them

`location_update`, `session_start`, `session_end`, `settings_sync`

## Notes

- These are **service-managed** controls. Setting one by hand through the dashboard or the API returns a 403 - the integration owns the value.
- Referencing one in a template declares that dependency. Someone who copies the template without Overlabels GPS connected is warned, not blocked, and it starts working the moment they connect.

---

*Generated from the Overlabels GPS driver by `php artisan help:build-integration-controls`. Do not edit by hand - your changes will be overwritten.*
