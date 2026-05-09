a poll opens with up to 5 choices.

### Poll
- `[[[event.title]]]` :: Poll question
- `[[[event.started_at]]]` :: When the poll opened
- `[[[event.ends_at]]]` :: When the poll closes

### Choices & Voting
- `[[[event.choices.count]]]` :: How many choices (max 5)
- `[[[event.choices.0.title]]]` :: First choice title (also .1 to .4)
- `[[[event.channel_points_voting.is_enabled]]]` :: true if channel points can vote
- `[[[event.channel_points_voting.amount_per_vote]]]` :: Points per channel-points vote
- `[[[event.bits_voting.is_enabled]]]` :: true if bits can vote (legacy)

### Winners
- `[[[event.winners.count]]]` - How many choices are currently winning (1, or N for ties)
- `[[[event.winners.0.title]]]` - First winner's title
- `[[[event.winners.0.votes]]]` - First winner's vote count
- Loop with `[[[foreach:event.winners as winner]]]...[[[endforeach]]]`

On `channel.poll.begin` everyone is tied at 0 votes, so all choices come through as winners. This is intentional - it lets the same template handle begin, progress, and end interchangeably.

note: maps to the Twitch EventSub event `channel.poll.begin`.
