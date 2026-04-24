a charity campaign begins on your channel.

### Campaign
- `[[[event.charity_name]]]` — Charity being raised for
- `[[[event.charity_description]]]` — Charity description
- `[[[event.charity_logo]]]` — Charity logo URL
- `[[[event.charity_website]]]` — Charity website URL
- `[[[event.started_at]]]` — When the campaign began

### Goal
- `[[[event.target_amount.formatted]]]` — Fundraising target (formatted) — USE THIS
- `[[[event.target_amount.value]]]` — Target in minor units
- `[[[event.target_amount.currency]]]` — Currency code
- `[[[event.current_amount.formatted]]]` — Raised so far (formatted)

note: maps to the Twitch EventSub event `channel.charity_campaign.start`.
