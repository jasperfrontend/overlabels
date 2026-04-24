available in alert templates triggered by StreamElements tips.

### Event Tags
- `[[[event.from_name]]]` — Name of the tipper
- `[[[event.message]]]` — Tipper's message
- `[[[event.amount]]]` — Tip amount (e.g. "5.00")
- `[[[event.currency]]]` — Currency code (e.g. "USD")
- `[[[event.formatted_amount]]]` — Formatted amount (e.g. "$5.00")
- `[[[event.type]]]` — Always "donation" (SE "tip" is normalized to "donation")
- `[[[event.source]]]` — Always "StreamElements" — useful for reusing alert templates across donation services
- `[[[event.transaction_id]]]` — Unique event identifier

example:
```
<div class="donation">
  [[[event.from_name]]] tipped [[[event.formatted_amount]]]!
  [[[if:event.message]]]
    <p class="message">[[[event.message]]]</p>
  [[[endif]]]
</div>
```

note: StreamElements is an Overlabels integration (not Twitch EventSub). Tips are delivered via the StreamElements realtime socket using a JWT.
