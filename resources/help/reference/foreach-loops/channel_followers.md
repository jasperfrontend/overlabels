someone who follows you.

Fields available on each iteration item

Inside the loop body, reference any of these fields as `[[[alias.field]]]`, where alias is the name you picked after `as`. Missing fields render as an empty string.

- `user_id`, `user_login`, `user_name` :: the follower
- `followed_at` :: ISO-8601 timestamp of when they followed
- `user_profile_image_url` :: the follower's avatar (enriched from Helix)
