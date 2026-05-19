The template tags available in your alerts when you get a new follower.

### User Information
- `[[[event.user_id]]]` :: Follower's Twitch ID
- `[[[event.user_login]]]` :: Follower's username
- `[[[event.user_name]]]` :: Follower's display name

### Event Data
- `[[[event.followed_at]]]` :: Timestamp when followed
- `[[[event.broadcaster_user_name]]]` :: Your display name

note: maps to the Twitch EventSub event `channel.follow`.
### tags
#channel #follower #follow #template-tags 