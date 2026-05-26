when someone cheers bits.

### User Information
- `[[[event.user_id]]]` :: Cheerer's Twitch ID
- `[[[event.user_login]]]` :: Cheerer's username
- `[[[event.user_name]]]` :: Cheerer's display name
- `[[[event.user_avatar]]]` :: Cheerer's profile image URL (blank when anonymous)

### Cheer Data
- `[[[event.bits]]]` :: Number of bits cheered
- `[[[event.message]]]` :: Cheer message
- `[[[event.is_anonymous]]]` :: true/false if anonymous

example:
`[[[if:event.bits >= 1000]]]HUGE CHEER![[[endif]]] [[[event.user_name]]] cheered [[[event.bits]]] bits!`

note: maps to the Twitch EventSub event `channel.cheer`.
