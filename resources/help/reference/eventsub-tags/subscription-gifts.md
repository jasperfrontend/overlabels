when someone gifts subscriptions.

### User Information
- `[[[event.user_id]]]` :: Gifter's Twitch ID
- `[[[event.user_login]]]` :: Gifter's username
- `[[[event.user_name]]]` :: Gifter's display name
- `[[[event.user_avatar]]]` :: Gifter's profile image URL

### Gift Data
- `[[[event.total]]]` :: Number of subs gifted
- `[[[event.tier]]]` :: Sub tier (1000, 2000, 3000) :: DON'T USE
- `[[[event.tier_display]]]` :: Sub display (1, 2, 3) :: USE THIS
- `[[[event.cumulative_total]]]` :: Total gifts ever
- `[[[event.is_anonymous]]]` :: true/false if anonymous

note: maps to the Twitch EventSub event `channel.subscription.gift`. A gift bomb is one alert: Twitch sends this event once with the number of subs gifted, and Overlabels skips the New Subscription alerts of that many gifted subs that follow it (for up to 120 seconds after the last one arrives). Use `[[[event.total]]]` for the count.
