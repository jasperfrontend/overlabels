a channel goal.

Fields available on each iteration item

Inside the loop body, reference any of these fields as `[[[alias.field]]]`, where alias is the name you picked after `as`. Missing fields render as an empty string.

- `id` — stable goal id
- `broadcaster_id`, `broadcaster_login`, `broadcaster_name` — your channel
- `type` — one of `follower`, `subscription`, `subscription_count`, `new_subscription`, `new_subscription_count`
- `description` — the free-text label you set on Twitch
- `current_amount` — progress toward the goal
- `target_amount` — goal target
- `created_at` — ISO-8601 timestamp of when the goal was created
