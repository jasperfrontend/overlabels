available on every Buy Me a Coffee event type (donation, commission, extra, membership, recurring, wishlist).

### Common Tags
- `[[[event.from_name]]]` :: Name of the supporter
- `[[[event.source]]]` :: Name of the platform ("Buy Me a Coffee") :: useful for reusing templates across donation services
- `[[[event.type]]]` :: Normalized type: donation, commission, extra, membership, recurring, or wishlist
- `[[[event.support_type]]]` :: Human-facing label for the type: Supporter, Commission, Extra, Membership, Subscription, or Wishlist
- `[[[event.transaction_id]]]` :: Unique Buy Me a Coffee transaction ID (falls back to the payment-provider ID)
- `[[[event.amount]]]` :: Amount as a string (e.g. "5")
- `[[[event.currency]]]` :: Currency code (e.g. "USD")
- `[[[event.coffee_count]]]` :: Number of coffees bought (e.g. "1")
- `[[[event.live_mode]]]` :: "1" for a real event, "0" for a test fired from the Buy Me a Coffee dashboard
- `[[[event.url]]]` :: Always empty :: Buy Me a Coffee does not send a supporter page URL, unlike Ko-fi

### Message Tags
Buy Me a Coffee sends two different pieces of text and they are easy to mix up.

- `[[[event.message]]]` :: Buy Me a Coffee's own generated description, e.g. "John bought you a coffee" :: this is boilerplate, **not** what the supporter wrote
- `[[[event.support_note]]]` :: The note the supporter actually typed :: this is what fills `[[[c:bmac:latest_donation_message]]]`

Reach for `event.support_note` when you want to show what someone said. It is empty when the supporter left no note, and also when they marked the note private - Buy Me a Coffee sends `note_hidden` for that, and Overlabels honours it by returning nothing.

### Flags
- `[[[event.is_recurring]]]` :: "1" on recurring and membership events, otherwise "0"
- `[[[event.is_membership]]]` :: "1" on membership events, otherwise "0"

Both are strings, and `"0"` is falsy under the conditional rules, so they work directly in a truthiness check: `[[[if:event.is_membership]]]`.

### Type-specific Tags
Each of these is filled only on its own event type and is an empty string on every other one.

- `[[[event.commission_name]]]` :: Name of the commission (commission events)
- `[[[event.wishlist_title]]]` :: Title of the wishlist item (wishlist events)
- `[[[event.extras_title]]]` :: Title of the first extra purchased (extra events)

example:
```
<div class="bmac-alert">
  [[[event.from_name]]] sent [[[event.amount]]] [[[event.currency]]]

  [[[if:event.is_membership]]]
    <span class="badge">New member</span>
  [[[elseif:event.type = commission]]]
    <span class="badge">Commission: [[[event.commission_name]]]</span>
  [[[elseif:event.type = wishlist]]]
    <span class="badge">Wishlist: [[[event.wishlist_title]]]</span>
  [[[endif]]]

  [[[if:event.support_note]]]
    <p class="note">[[[event.support_note]]]</p>
  [[[endif]]]
</div>
```

note: Buy Me a Coffee is an Overlabels integration (not Twitch EventSub). Alerts fire through Buy Me a Coffee's webhook and are routed by `event.type`. Supporter email and shipping address are stripped before the event is stored, so there are no tags for them.
