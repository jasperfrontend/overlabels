available in alert templates triggered by StreamLabs donations.

### Event Tags
- `[[[event.from_name]]]` :: Name of the donor
- `[[[event.message]]]` :: Donor's message
- `[[[event.amount]]]` :: Donation amount (e.g. "5.00")
- `[[[event.currency]]]` :: Currency code (e.g. "USD")
- `[[[event.formatted_amount]]]` :: Amount and currency written in your locale (e.g. "$5.00" in en-US, "€ 5,00" in nl-NL) :: derived by Overlabels from the amount and currency above, not supplied by the service
- `[[[event.type]]]` :: Always "donation"
- `[[[event.source]]]` :: Always "StreamLabs" :: useful for reusing alert templates across donation services
- `[[[event.transaction_id]]]` :: Unique event identifier

example:
```
<div class="donation">
  [[[event.from_name]]] donated [[[event.formatted_amount]]]!
  [[[if:event.message]]]
    <p class="message">[[[event.message]]]</p>
  [[[endif]]]
</div>
```

note: StreamLabs is an Overlabels integration (not Twitch EventSub). Donation events are delivered via StreamLabs OAuth.
