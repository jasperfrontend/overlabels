hype train contributor.

Fields available on each iteration item

Inside the loop body, reference any of these fields as `[[[alias.field]]]`, where alias is the name you picked after `as`. Missing fields render as an empty string.

- `user_id`, `user_login`, `user_name` — the contributor
- `type` — "bits", "subscription", or "other"
- `total` — amount contributed in the unit implied by `type`

Capped at 3 items (fixed). For just the single latest contributor use `event.last_contribution.user_name`, `event.last_contribution.type`, `event.last_contribution.total`.
