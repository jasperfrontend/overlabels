# StreamElements controls

StreamElements provisions 6 controls when you connect it. They are filled in automatically and stay up to date; you read them, Overlabels writes them.

Reference them anywhere a tag works, using the `c:streamelements:` prefix.

## Controls

| Tag | Type | Default | Holds |
|---|---|---|---|
| `[[[c:streamelements:donations_received]]]` | counter | `0` | StreamElements Donations Received |
| `[[[c:streamelements:latest_donor_name]]]` | text | empty | Latest Donor Name |
| `[[[c:streamelements:latest_donation_amount]]]` | number | `0` | Latest Donation Amount |
| `[[[c:streamelements:latest_donation_message]]]` | text | empty | Latest Donation Message |
| `[[[c:streamelements:latest_donation_currency]]]` | text | empty | Latest Currency |
| `[[[c:streamelements:total_received]]]` | number | `0` | Total StreamElements Amount (session) |

## Events that update them

`donation`

## Notes

- These are **service-managed** controls. Setting one by hand through the dashboard or the API returns a 403 - the integration owns the value.
- Referencing one in a template declares that dependency. Someone who copies the template without StreamElements connected is warned, not blocked, and it starts working the moment they connect.
- The shared keys above are identical across every donation integration, so swapping the prefix ports a template between services. See [[all-integration-controls]].

---

*Generated from the StreamElements driver by `php artisan help:build-integration-controls`. Do not edit by hand - your changes will be overwritten.*
