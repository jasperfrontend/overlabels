a channel you follow.

Fields available on each iteration item

Inside the loop body, reference any of these fields as `[[[alias.field]]]`, where alias is the name you picked after `as`. Missing fields render as an empty string.

- `broadcaster_id`, `broadcaster_login`, `broadcaster_name` :: the channel
- `followed_at` :: ISO-8601 timestamp of when you followed
- `broadcaster_profile_image_url` :: the channel's avatar (enriched from Helix)
