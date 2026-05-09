final results :: `status` tells you if it completed naturally or was cut short.

### Poll
- `[[[event.title]]]` :: Poll question
- `[[[event.status]]]` :: "completed", "terminated", or "archived"
- `[[[event.started_at]]]` :: When the poll opened
- `[[[event.ended_at]]]` :: When the poll ended

### Final Choices
- `[[[event.choices.count]]]` :: How many choices
- `[[[event.choices.total_votes]]]` :: Final total votes across all choices
- `[[[event.choices.0.title]]]` :: First choice title
- `[[[event.choices.0.votes]]]` :: First choice final vote count
- `[[[event.choices.1.title]]]` :: Second choice title (also .2 to .4)
- `[[[event.choices.1.votes]]]` :: Second choice final votes

### Winner(s)
- `[[[event.winners.count]]]` - How many winners (1, or N for ties)
- `[[[event.winners.0.title]]]` - Winning choice title
- `[[[event.winners.0.votes]]]` - Winning vote count
- `[[[event.winners.total_votes]]]` - Sum of votes across all winners (useful for tie copy)
- Loop with `[[[foreach:event.winners as winner]]]...[[[endforeach]]]`

`event.winners` is computed from `votes` only - bits and channel-points votes are ignored. Ties are always represented in full: every choice tied at the max vote count appears in the array.

example:
```
[[[if:event.status = completed]]]
  <div class="poll-done">Poll ended: [[[event.title]]]</div>
[[[elseif:event.status = terminated]]]
  <div class="poll-cut">Poll cut short: [[[event.title]]]</div>
[[[endif]]]
```

note: maps to the Twitch EventSub event `channel.poll.end`.
