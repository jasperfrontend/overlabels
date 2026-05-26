when another streamer raids your channel.

### Raider Information
- `[[[event.from_broadcaster_user_id]]]` :: Raider's ID
- `[[[event.from_broadcaster_user_login]]]` :: Raider's username
- `[[[event.from_broadcaster_user_name]]]` :: Raider's name
- `[[[event.from_broadcaster_user_avatar]]]` :: Raider's profile image URL

### Raid Data
- `[[[event.viewers]]]` :: Number of viewers in raid
- `[[[event.to_broadcaster_user_name]]]` :: Your name
- `[[[event.to_broadcaster_user_avatar]]]` :: Your profile image URL

note: maps to the Twitch EventSub event `channel.raid` (filtered to incoming raids).
