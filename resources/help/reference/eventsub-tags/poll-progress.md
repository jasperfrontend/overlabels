mid-poll vote count update — fires frequently as votes come in.

### Poll
- `[[[event.title]]]` — Poll question
- `[[[event.ends_at]]]` — When the poll closes

### Choices
- `[[[event.choices.count]]]` — How many choices
- `[[[event.choices.total_votes]]]` — Total votes across all choices (use as denominator for progress bars)
- `[[[event.choices.total_channel_points_votes]]]` — Total channel-points votes across all choices
- `[[[event.choices.0.title]]]` — First choice title
- `[[[event.choices.0.votes]]]` — First choice total votes
- `[[[event.choices.0.channel_points_votes]]]` — Channel-points votes for #0
- `[[[event.choices.1.title]]]` — Second choice title (also .2 to .4)
- `[[[event.choices.1.votes]]]` — Second choice votes

### Currently Leading
- `[[[event.winners.count]]]` - How many choices are tied for the lead
- `[[[event.winners.0.title]]]` - Leading choice title
- `[[[event.winners.0.votes]]]` - Leading vote count
- Loop with `[[[foreach:event.winners as winner]]]...[[[endforeach]]]`

`event.winners` is computed from `votes` only (bits and channel-points votes are ignored). On a fresh poll where everyone is tied at 0, all choices come through as winners.

note: maps to the Twitch EventSub event `channel.poll.progress`.
