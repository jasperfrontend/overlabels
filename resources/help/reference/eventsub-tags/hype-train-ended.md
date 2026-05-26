the train finished :: use the final level + top contributors for the "thanks" beat.

### Final State
- `[[[event.level]]]` :: Final level reached
- `[[[event.total]]]` :: Final total contributed
- `[[[event.started_at]]]` :: When the train started
- `[[[event.ended_at]]]` :: When it ended
- `[[[event.cooldown_ends_at]]]` :: When the next train can start

### Top Contributors
- `[[[event.top_contributions.count]]]` :: How many contributors are listed
- `[[[event.top_contributions.0.user_name]]]` :: #1 contributor name
- `[[[event.top_contributions.0.user_avatar]]]` :: #1 contributor's profile image URL
- `[[[event.top_contributions.0.type]]]` :: #1 contribution type
- `[[[event.top_contributions.0.total]]]` :: #1 contribution total
- `[[[event.top_contributions.1.user_name]]]` :: #2 contributor
- `[[[event.top_contributions.1.user_avatar]]]` :: #2 contributor's profile image URL
- `[[[event.top_contributions.2.user_name]]]` :: #3 contributor
- `[[[event.top_contributions.2.user_avatar]]]` :: #3 contributor's profile image URL

note: maps to the Twitch EventSub event `channel.hype_train.end`.
