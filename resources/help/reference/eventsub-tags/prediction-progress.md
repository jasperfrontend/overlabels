update with current predictor counts :: fires frequently.

### Prediction
- `[[[event.title]]]` :: Prediction question
- `[[[event.locks_at]]]` :: When predictions close

### Outcomes
- `[[[event.outcomes.count]]]` :: How many outcomes
- `[[[event.outcomes.total_users]]]` :: Total predictors across all outcomes
- `[[[event.outcomes.total_channel_points]]]` :: Total channel points wagered across all outcomes
- `[[[event.outcomes.0.title]]]` :: First outcome title
- `[[[event.outcomes.0.color]]]` :: "blue" or "pink"
- `[[[event.outcomes.0.users]]]` :: Number of predictors on #0
- `[[[event.outcomes.0.channel_points]]]` :: Total channel points on #0
- `[[[event.outcomes.1.title]]]` :: Second outcome title (also .2 to .9)
- `[[[event.outcomes.1.users]]]` :: Predictors on #1

note: maps to the Twitch EventSub event `channel.prediction.progress`.
