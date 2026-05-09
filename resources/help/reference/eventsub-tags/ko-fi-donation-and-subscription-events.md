additional tags available for Ko-fi donation and subscription events.

### Payment Tags
- `[[[event.message]]]` :: Supporter's message
- `[[[event.amount]]]` :: Amount as a string (e.g. "5.00")
- `[[[event.currency]]]` :: Currency code (e.g. "USD")

example:
```
<div class="donor">[[[event.from_name]]] donated [[[event.amount]]] [[[event.currency]]]!</div>
<div class="message">[[[if:event.message]]][[[event.message]]][[[endif]]]</div>
```

note: these tags stack on top of the [[All Ko-fi Events]] common tags.
