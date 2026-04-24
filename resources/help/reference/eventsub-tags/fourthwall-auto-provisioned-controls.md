six controls are created on connect and kept up to date with every donation.

### Use in any template with the `[[[c:fourthwall:key]]]` syntax
- `[[[c:fourthwall:donations_received]]]` — Total number of donations received (counter)
- `[[[c:fourthwall:latest_donor_name]]]` — Name of the most recent donor
- `[[[c:fourthwall:latest_donation_amount]]]` — Amount of the most recent donation
- `[[[c:fourthwall:latest_donation_message]]]` — Message from the most recent donor
- `[[[c:fourthwall:latest_donation_currency]]]` — Currency of the most recent donation (e.g. USD)
- `[[[c:fourthwall:total_received]]]` — Running total of all donation amounts (session)

note: Fourthwall, Ko-fi, StreamLabs, and StreamElements share a unified control schema — the six keys are identical across all four integrations, so you can swap the prefix (`c:fourthwall:`, `c:kofi:`, `c:streamlabs:`, `c:streamelements:`) and the template keeps working.
