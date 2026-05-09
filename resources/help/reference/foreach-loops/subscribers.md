channel subscriber.

Fields available on each iteration item

Inside the loop body, reference any of these fields as `[[[alias.field]]]`, where alias is the name you picked after `as`. Missing fields render as an empty string.

- `user_id`, `user_login`, `user_name` :: the subscriber
- `user_profile_image_url` :: the subscriber's avatar (enriched from Helix)
- `broadcaster_id`, `broadcaster_login`, `broadcaster_name` :: your channel
- `is_gift` :: true if the sub was gifted
- `gifter_id`, `gifter_login`, `gifter_name` :: empty string when `is_gift` is false
- `gifter_profile_image_url` :: the gifter's avatar (enriched)
- `tier` :: "1000", "2000", "3000", or "Prime"
- `plan_name` :: human-readable tier label (e.g. "Tier 1")
