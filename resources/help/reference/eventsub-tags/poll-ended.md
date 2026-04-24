final results — `status` tells you if it completed naturally or was cut short.

### Poll
- `[[[event.title]]]` — Poll question
- `[[[event.status]]]` — "completed", "terminated", or "archived"
- `[[[event.started_at]]]` — When the poll opened
- `[[[event.ended_at]]]` — When the poll ended

### Final Choices
- `[[[event.choices.count]]]` — How many choices
- `[[[event.choices.total_votes]]]` — Final total votes across all choices
- `[[[event.choices.0.title]]]` — First choice title
- `[[[event.choices.0.votes]]]` — First choice final vote count
- `[[[event.choices.1.title]]]` — Second choice title (also .2 to .4)
- `[[[event.choices.1.votes]]]` — Second choice final votes

example:
```
[[[if:event.status = completed]]]
  <div class="poll-done">Poll ended: [[[event.title]]]</div>
[[[elseif:event.status = terminated]]]
  <div class="poll-cut">Poll cut short: [[[event.title]]]</div>
[[[endif]]]
```

note: maps to the Twitch EventSub event `channel.poll.end`.
