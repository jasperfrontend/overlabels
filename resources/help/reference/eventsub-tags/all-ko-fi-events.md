available on every Ko-fi event type (donation, subscription, shop_order, commission).

### Common Tags
- `[[[event.from_name]]]` :: Name of the supporter
- `[[[event.source]]]` :: Name of the platform ("Ko-fi") :: useful for reusing templates across donation services
- `[[[event.type]]]` :: Normalized type: donation, subscription, shop_order, or commission
- `[[[event.is_shop_order]]]` :: "1" on shop order events, otherwise "0"
- `[[[event.transaction_id]]]` :: Unique Ko-fi transaction ID
- `[[[event.url]]]` :: Supporter's Ko-fi page URL

note: Ko-fi is an Overlabels integration (not Twitch EventSub). Alerts fire through Ko-fi's own webhook and are routed by `event.type`.
