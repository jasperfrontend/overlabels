six controls are created on connect and kept up to date with every tip.

### Use in any template with the `[[[c:streamelements:key]]]` syntax
- `[[[c:streamelements:donations_received]]]` :: Total number of tips received (counter)
- `[[[c:streamelements:latest_donor_name]]]` :: Name of the most recent tipper
- `[[[c:streamelements:latest_donation_amount]]]` :: Amount of the most recent tip
- `[[[c:streamelements:latest_donation_message]]]` :: Message from the most recent tipper
- `[[[c:streamelements:latest_donation_currency]]]` :: Currency of the most recent tip (e.g. USD)
- `[[[c:streamelements:total_received]]]` :: Running total of all tip amounts (session)

note: StreamElements, Ko-fi, StreamLabs, and Fourthwall share a unified control schema :: the six keys are identical across all four integrations, so you can swap the prefix (`c:streamelements:`, `c:kofi:`, `c:streamlabs:`, `c:fourthwall:`) and the template keeps working.
