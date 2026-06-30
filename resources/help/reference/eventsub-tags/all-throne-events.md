available on every Throne event type (gift_purchased, contribution_purchased, gift_crowdfunded). All three normalize to event.type "donation".

### Common Tags
- `[[[event.from_name]]]` :: Name of the gifter (empty on crowdfunded gifts, which have no single gifter)
- `[[[event.source]]]` :: Name of the platform ("Throne") :: useful for reusing templates across donation services
- `[[[event.type]]]` :: Normalized type: always "donation" for Throne
- `[[[event.throne_event_type]]]` :: Raw Throne type: gift_purchased, contribution_purchased, or gift_crowdfunded
- `[[[event.transaction_id]]]` :: Unique Throne event ID (used for deduplication)
- `[[[event.amount]]]` :: Amount in whole currency units (Throne sends integer minor units; Overlabels divides by 100)
- `[[[event.currency]]]` :: Currency code (e.g. USD)
- `[[[event.message]]]` :: The gifter's message (empty on crowdfunded gifts)
- `[[[event.item_name]]]` :: Name of the gifted item (e.g. "AirPods Max")
- `[[[event.item_thumbnail_url]]]` :: Product image URL - drop straight into an `<img src>`
- `[[[event.is_surprise_gift]]]` :: "1" if sent as a surprise gift, otherwise "0"

note: Throne is an Overlabels integration (not Twitch EventSub). Alerts fire through Throne's signed webhook and are routed by `event.type`.
