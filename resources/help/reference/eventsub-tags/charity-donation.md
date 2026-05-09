a viewer donated to the active charity campaign.

### Donor & Campaign
- `[[[event.user_name]]]` :: Donor's display name
- `[[[event.user_login]]]` :: Donor's username
- `[[[event.charity_name]]]` :: Charity being donated to
- `[[[event.charity_description]]]` :: Charity description
- `[[[event.charity_logo]]]` :: Charity logo URL
- `[[[event.charity_website]]]` :: Charity website URL

### Amount
- `[[[event.amount.formatted]]]` :: Ready-to-display string (e.g. "$15.23") :: USE THIS
- `[[[event.amount.value]]]` :: Raw minor units (1523 = $15.23)
- `[[[event.amount.decimal_places]]]` :: Decimal places (usually 2)
- `[[[event.amount.currency]]]` :: Currency code ("USD", "EUR", etc.)

example:
```
<div class="charity-donation">
  [[[event.user_name]]] donated [[[event.amount.formatted]]] to [[[event.charity_name]]]!
</div>
```

note: maps to the Twitch EventSub event `channel.charity_campaign.donate`.
