prediction outcome.

Fields available on each iteration item

Inside the loop body, reference any of these fields as `[[[alias.field]]]`, where alias is the name you picked after `as`. Missing fields render as an empty string.

- `id` :: stable outcome id
- `title` :: outcome label
- `color` :: "blue" or "pink" (Twitch's own colouring)
- `users` :: number of predictors on this outcome
- `channel_points` :: total channel points wagered on this outcome

Aggregates: `event.outcomes.total_users`, `event.outcomes.total_channel_points`. The winning outcome id is `event.winning_outcome_id` on lock/end events.
