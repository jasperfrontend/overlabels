# Streamlabs controls

Streamlabs provisions 6 controls when you connect it. They are filled in automatically and stay up to date; you read them, Overlabels writes them.

Reference them anywhere a tag works, using the `c:streamlabs:` prefix.

## Controls

| Tag | Type | Default | Holds |
|---|---|---|---|
| `[[[c:streamlabs:donations_received]]]` | counter | `0` | StreamLabs Donations Received |
| `[[[c:streamlabs:latest_donor_name]]]` | text | empty | Latest Donor Name |
| `[[[c:streamlabs:latest_donation_amount]]]` | number | `0` | Latest Donation Amount |
| `[[[c:streamlabs:latest_donation_message]]]` | text | empty | Latest Donation Message |
| `[[[c:streamlabs:latest_donation_currency]]]` | text | empty | Latest Currency |
| `[[[c:streamlabs:total_received]]]` | number | `0` | Total StreamLabs Amount (session) |

## Events that update them

`donation`

## Notes

- These are **service-managed** controls. Setting one by hand through the dashboard or the API returns a 403 - the integration owns the value.
- Referencing one in a template declares that dependency. Someone who copies the template without Streamlabs connected is warned, not blocked, and it starts working the moment they connect.
- The shared keys above are identical across every donation integration, so swapping the prefix ports a template between services. See [[all-integration-controls]].

---

*Generated from the Streamlabs driver by `php artisan help:build-integration-controls`. Do not edit by hand - your changes will be overwritten.*
