when someone resubscribes with a message.

### User Information
- `[[[event.user_name]]]` :: Subscriber's display name
- `[[[event.user_avatar]]]` :: Subscriber's profile image URL
- `[[[event.tier]]]` :: Sub tier (1000, 2000, 3000) :: DON'T USE
- `[[[event.tier_display]]]` :: Sub display (1, 2, 3) :: USE THIS

### Subscription Data
- `[[[event.cumulative_months]]]` :: Total months subbed
- `[[[event.streak_months]]]` :: Current streak
- `[[[event.duration_months]]]` :: Months in this sub
- `[[[event.message.text]]]` :: The resub message

note: maps to the Twitch EventSub event `channel.subscription.message`.
