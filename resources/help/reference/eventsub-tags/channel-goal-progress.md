current amount updated :: fires on every contribution, budget for spam.

### Goal
- `[[[event.type]]]` :: Goal type ("follower", "subscription", etc.)
- `[[[event.description]]]` :: Goal description
- `[[[event.current_amount]]]` :: Current value
- `[[[event.target_amount]]]` :: Target value

example:
```
<div class="goal-bar">
  [[[event.description]]]: [[[event.current_amount]]] / [[[event.target_amount]]]
</div>
```

note: maps to the Twitch EventSub event `channel.goal.progress`.
