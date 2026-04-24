six controls are created on connect and kept up to date with every donation.

### Use in any template with the `[[[c:streamlabs:key]]]` syntax
- `[[[c:streamlabs:donations_received]]]` — Total number of donations received (counter)
- `[[[c:streamlabs:latest_donor_name]]]` — Name of the most recent donor
- `[[[c:streamlabs:latest_donation_amount]]]` — Amount of the most recent donation
- `[[[c:streamlabs:latest_donation_message]]]` — Message from the most recent donor
- `[[[c:streamlabs:latest_donation_currency]]]` — Currency of the most recent donation (e.g. USD)
- `[[[c:streamlabs:total_received]]]` — Running total of all donation amounts (session)

note: StreamLabs, Ko-fi, StreamElements, and Fourthwall share a unified control schema — the six keys are identical across all four integrations, so you can swap the prefix (`c:streamlabs:`, `c:kofi:`, `c:streamelements:`, `c:fourthwall:`) and the template keeps working.
