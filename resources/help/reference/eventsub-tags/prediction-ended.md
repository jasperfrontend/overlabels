winning outcome + payouts :: or canceled if refunded.

### Prediction
- `[[[event.title]]]` :: Prediction question
- `[[[event.status]]]` :: "resolved" or "canceled"
- `[[[event.winning_outcome_id]]]` :: ID of the winning outcome (resolved only)
- `[[[event.started_at]]]` :: When it opened
- `[[[event.ended_at]]]` :: When it ended

### Final Outcomes
- `[[[event.outcomes.count]]]` :: How many outcomes
- `[[[event.outcomes.total_users]]]` :: Final total predictors across all outcomes
- `[[[event.outcomes.total_channel_points]]]` :: Final total channel points wagered
- `[[[event.outcomes.0.title]]]` :: First outcome title
- `[[[event.outcomes.0.users]]]` :: Final predictor count on #0
- `[[[event.outcomes.0.channel_points]]]` :: Final channel points on #0
- `[[[event.outcomes.1.title]]]` :: Second outcome title (also .2 to .9)

example:
```
[[[if:event.status = resolved]]]
  <div class="prediction-resolved">Winner: [[[event.outcomes.0.title]]]</div>
[[[elseif:event.status = canceled]]]
  <div class="prediction-canceled">Prediction canceled - refunded</div>
[[[endif]]]
```

note: maps to the Twitch EventSub event `channel.prediction.end`.
