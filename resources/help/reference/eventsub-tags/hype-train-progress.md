a new contribution lands during an active train :: fires frequently, budget for spam.

### Train State
- `[[[event.level]]]` :: Current level
- `[[[event.total]]]` :: Total points contributed so far
- `[[[event.progress]]]` :: Progress toward next level
- `[[[event.goal]]]` :: Points needed for next level
- `[[[event.expires_at]]]` :: When the train expires

### Top & Last Contributor
- `[[[event.last_contribution.user_name]]]` :: Who just contributed
- `[[[event.last_contribution.type]]]` :: "bits", "subscription", or "other"
- `[[[event.last_contribution.total]]]` :: Their contribution amount
- `[[[event.top_contributions.0.user_name]]]` :: #1 contributor (also .1 and .2)
- `[[[event.top_contributions.0.total]]]` :: #1 contribution total

example:
```
<div class="hype-progress">
  Level [[[event.level]]] - [[[event.progress]]] / [[[event.goal]]]
  [[[if:event.last_contribution.user_name]]]
    <small>Last: [[[event.last_contribution.user_name]]]</small>
  [[[endif]]]
</div>
```

note: maps to the Twitch EventSub event `channel.hype_train.progress`.
