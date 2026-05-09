when someone subscribes to your channel.

### User Information
- `[[[event.user_id]]]` :: Subscriber's Twitch ID
- `[[[event.user_login]]]` :: Subscriber's username
- `[[[event.user_name]]]` :: Subscriber's display name

### Subscription Data
- `[[[event.tier]]]` :: Sub tier (1000, 2000, 3000) :: DON'T USE
- `[[[event.tier_display]]]` :: Sub display (1, 2, 3) :: USE THIS
- `[[[event.is_gift]]]` :: true/false if gifted

note: maps to the Twitch EventSub event `channel.subscribe`.
