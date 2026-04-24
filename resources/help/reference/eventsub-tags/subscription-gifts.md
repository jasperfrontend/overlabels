when someone gifts subscriptions.

### User Information
- `[[[event.user_id]]]` — Gifter's Twitch ID
- `[[[event.user_login]]]` — Gifter's username
- `[[[event.user_name]]]` — Gifter's display name

### Gift Data
- `[[[event.total]]]` — Number of subs gifted
- `[[[event.tier]]]` — Sub tier (1000, 2000, 3000) — DON'T USE
- `[[[event.tier_display]]]` — Sub display (1, 2, 3) — USE THIS
- `[[[event.cumulative_total]]]` — Total gifts ever
- `[[[event.is_anonymous]]]` — true/false if anonymous

note: maps to the Twitch EventSub event `channel.subscription.gift`. Overlabels collapses gift bombs (multiple gifts from the same user within an 8-second window) into a single alert; use `[[[event.total]]]` for the combined count.
