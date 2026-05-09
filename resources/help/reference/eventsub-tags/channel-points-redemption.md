when someone redeems a channel points reward.

### User Information
- `[[[event.user_id]]]` :: Redeemer's Twitch ID
- `[[[event.user_login]]]` :: Redeemer's username
- `[[[event.user_name]]]` :: Redeemer's display name
- `[[[event.user_input]]]` :: User's input text

### Reward Data
- `[[[event.reward.title]]]` :: Reward name
- `[[[event.reward.cost]]]` :: Point cost
- `[[[event.reward.prompt]]]` :: Reward description
- `[[[event.status]]]` :: Fulfillment status
- `[[[event.redeemed_at]]]` :: Timestamp

note: maps to the Twitch EventSub event `channel.channel_points_custom_reward_redemption.add`.
