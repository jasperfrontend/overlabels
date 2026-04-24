the campaign wrapped up — use the final totals for a thank-you alert.

### Campaign
- `[[[event.charity_name]]]` — Charity name
- `[[[event.charity_description]]]` — Charity description
- `[[[event.charity_logo]]]` — Charity logo URL
- `[[[event.stopped_at]]]` — When the campaign ended

### Final Totals
- `[[[event.current_amount.formatted]]]` — Final amount raised (formatted) — USE THIS
- `[[[event.current_amount.value]]]` — Final amount in minor units
- `[[[event.target_amount.formatted]]]` — Target (formatted)

note: maps to the Twitch EventSub event `channel.charity_campaign.stop`.
