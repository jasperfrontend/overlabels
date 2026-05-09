current vs. target update :: fires on every donation, budget for spam.

### Campaign
- `[[[event.charity_name]]]` :: Charity name
- `[[[event.charity_logo]]]` :: Charity logo URL

### Progress
- `[[[event.current_amount.formatted]]]` :: Raised so far (formatted) :: USE THIS
- `[[[event.current_amount.value]]]` :: Raised in minor units
- `[[[event.target_amount.formatted]]]` :: Target (formatted)
- `[[[event.target_amount.value]]]` :: Target in minor units
- `[[[event.target_amount.currency]]]` :: Currency code

example:
```
<div class="charity-progress">
  [[[event.current_amount.formatted]]] raised of [[[event.target_amount.formatted]]]
</div>
```

note: maps to the Twitch EventSub event `channel.charity_campaign.progress`.
