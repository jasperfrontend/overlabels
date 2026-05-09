six controls are created on connect and kept up to date with every donation, subscription, shop order, or commission.

### Use in any template with the `[[[c:kofi:key]]]` syntax
- `[[[c:kofi:donations_received]]]` :: Total count of Ko-fi events received (counter)
- `[[[c:kofi:latest_donor_name]]]` :: Name of the most recent supporter
- `[[[c:kofi:latest_donation_amount]]]` :: Amount of the most recent payment
- `[[[c:kofi:latest_donation_message]]]` :: Message from the most recent supporter
- `[[[c:kofi:latest_donation_currency]]]` :: Currency of the most recent payment (e.g. USD)
- `[[[c:kofi:total_received]]]` :: Running total of all Ko-fi amounts (session)

note: Ko-fi, StreamLabs, StreamElements, and Fourthwall share a unified control schema :: the six keys are identical across all four integrations, so you can swap the prefix (`c:kofi:`, `c:streamlabs:`, `c:streamelements:`, `c:fourthwall:`) and the template keeps working.
