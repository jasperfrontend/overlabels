goal completed or expired — `is_achieved` tells you which.

### Goal
- `[[[event.type]]]` — Goal type
- `[[[event.description]]]` — Goal description
- `[[[event.is_achieved]]]` — true if goal was hit, false if it expired
- `[[[event.current_amount]]]` — Final value
- `[[[event.target_amount]]]` — Target value
- `[[[event.started_at]]]` — When the goal started
- `[[[event.ended_at]]]` — When the goal ended

example:
```
[[[if:event.is_achieved]]]
  <div class="goal-hit">[[[event.description]]] - HIT!</div>
[[[else]]]
  <div class="goal-miss">[[[event.description]]] ended at [[[event.current_amount]]] / [[[event.target_amount]]]</div>
[[[endif]]]
```

note: maps to the Twitch EventSub event `channel.goal.end`.
