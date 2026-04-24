predictions close — waiting for the streamer to resolve.

### Prediction
- `[[[event.title]]]` — Prediction question
- `[[[event.locked_at]]]` — When it locked

### Final Outcomes
- `[[[event.outcomes.count]]]` — How many outcomes
- `[[[event.outcomes.total_users]]]` — Total predictors across all outcomes
- `[[[event.outcomes.total_channel_points]]]` — Total channel points wagered across all outcomes
- `[[[event.outcomes.0.title]]]` — First outcome title
- `[[[event.outcomes.0.users]]]` — Final predictor count on #0
- `[[[event.outcomes.0.channel_points]]]` — Final channel points on #0
- `[[[event.outcomes.1.title]]]` — Second outcome title (also .2 to .9)

note: maps to the Twitch EventSub event `channel.prediction.lock`.
