a hype train kicks off on your channel.

### Train State
- `[[[event.level]]]` :: Starting level (usually 1)
- `[[[event.total]]]` :: Total points contributed so far
- `[[[event.progress]]]` :: Progress toward next level
- `[[[event.goal]]]` :: Points needed for next level
- `[[[event.started_at]]]` :: When the train started
- `[[[event.expires_at]]]` :: When the train expires unless contributed to

### Top & Last Contributor
- `[[[event.last_contribution.user_name]]]` :: Most recent contributor
- `[[[event.last_contribution.user_avatar]]]` :: Most recent contributor's profile image URL
- `[[[event.last_contribution.type]]]` :: "bits", "subscription", or "other"
- `[[[event.last_contribution.total]]]` :: Their contribution amount
- `[[[event.top_contributions.count]]]` :: How many top contributors are listed
- `[[[event.top_contributions.0.user_name]]]` :: #1 contributor name
- `[[[event.top_contributions.0.user_avatar]]]` :: #1 contributor's profile image URL
- `[[[event.top_contributions.0.type]]]` :: #1 contribution type
- `[[[event.top_contributions.0.total]]]` :: #1 contribution total

note: maps to the Twitch EventSub event `channel.hype_train.begin`.
