available in alert templates triggered by Fourthwall donations.

### Event Tags
- `[[[event.from_name]]]` — Name of the donor
- `[[[event.message]]]` — Donor's message
- `[[[event.amount]]]` — Donation amount (e.g. "10")
- `[[[event.currency]]]` — Currency code (e.g. "USD")
- `[[[event.type]]]` — Always "donation"
- `[[[event.source]]]` — Always "Fourthwall" — useful for reusing alert templates across donation services
- `[[[event.status]]]` — Donation lifecycle state (e.g. "OPEN") — Fourthwall-specific
- `[[[event.transaction_id]]]` — Unique donation identifier (e.g. `don_...`)

example:
```
<div class="donation">
  [[[event.from_name]]] donated [[[event.amount]]] [[[event.currency]]]!
  [[[if:event.message]]]
    <p class="message">[[[event.message]]]</p>
  [[[endif]]]
</div>
```

note: Fourthwall is an Overlabels integration (not Twitch EventSub). Overlabels auto-registers the webhook on your Fourthwall shop on connect.
