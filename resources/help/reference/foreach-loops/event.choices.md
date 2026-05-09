poll choice.

Fields available on each iteration item

Inside the loop body, reference any of these fields as `[[[alias.field]]]`, where alias is the name you picked after `as`. Missing fields render as an empty string.

- `id` :: stable choice id (good for `data-key`)
- `title` :: choice label shown to voters
- `votes` :: total votes on this choice
- `channel_points_votes` :: votes cast with channel points
- `bits_votes` :: votes cast with bits (deprecated by Twitch, still in payload)

Aggregates on the iterable itself: `event.choices.total_votes`, `event.choices.total_channel_points_votes`, `event.choices.total_bits_votes`.
