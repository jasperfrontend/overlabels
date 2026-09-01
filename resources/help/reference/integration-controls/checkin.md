# Chat Checkin controls

Chat Checkin provisions 10 controls when you connect it. They are filled in automatically and stay up to date; you read them, Overlabels writes them.

Reference them anywhere a tag works, using the `c:checkin:` prefix.

## Specific to Chat Checkin

| Tag | Type | Default | Holds |
|---|---|---|---|
| `[[[c:checkin:checkins_this_stream]]]` | counter | `0` | Checkins This Stream |
| `[[[c:checkin:unique_countries_this_stream]]]` | number | `0` | Unique Countries This Stream |
| `[[[c:checkin:farthest_checkin_this_stream]]]` | number | `0` | Farthest Checkin This Stream |
| `[[[c:checkin:checkins_total]]]` | number | `0` | Checkins Total (all time) |
| `[[[c:checkin:latest_checkin_name]]]` | text | empty | Latest Checkin Name |
| `[[[c:checkin:latest_checkin_place]]]` | text | empty | Latest Checkin Place |
| `[[[c:checkin:latest_checkin_country]]]` | text | empty | Latest Checkin Country |
| `[[[c:checkin:latest_checkin_lat]]]` | text | empty | Latest Checkin Latitude |
| `[[[c:checkin:latest_checkin_lng]]]` | text | empty | Latest Checkin Longitude |
| `[[[c:checkin:latest_checkin_distance]]]` | number | `0` | Latest Checkin Distance |

## Events that update them

`checkin`

## Notes

- These are **service-managed** controls. Setting one by hand through the dashboard or the API returns a 403 - the integration owns the value.
- Referencing one in a template declares that dependency. Someone who copies the template without Chat Checkin connected is warned, not blocked, and it starts working the moment they connect.

---

*Generated from the Chat Checkin driver by `php artisan help:build-integration-controls`. Do not edit by hand - your changes will be overwritten.*
