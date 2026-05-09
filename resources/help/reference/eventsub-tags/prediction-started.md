a prediction opens with up to 10 outcomes.

### Prediction
- `[[[event.title]]]` :: Prediction question
- `[[[event.started_at]]]` :: When it opened
- `[[[event.locks_at]]]` :: When predictions close

### Outcomes
- `[[[event.outcomes.count]]]` :: How many outcomes (max 10)
- `[[[event.outcomes.0.title]]]` :: First outcome title (also .1 to .9)
- `[[[event.outcomes.0.color]]]` :: "blue" or "pink"

note: maps to the Twitch EventSub event `channel.prediction.begin`.
