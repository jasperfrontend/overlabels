when your stream goes live.

### Stream Information
- `[[[event.id]]]` — Stream ID
- `[[[event.type]]]` — Stream type (usually "live")
- `[[[event.started_at]]]` — Stream start timestamp

note: maps to the Twitch EventSub event `stream.online`. Useful for logging, but viewers probably won't see live alerts since the stream just started.
