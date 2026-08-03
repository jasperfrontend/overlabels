# Throne controls

Throne provisions 9 controls when you connect it. They are filled in automatically and stay up to date; you read them, Overlabels writes them.

Reference them anywhere a tag works, using the `c:throne:` prefix.

## The shared donation schema

| Tag | Type | Default | Holds |
|---|---|---|---|
| `[[[c:throne:donations_received]]]` | counter | `0` | Throne Gifts Received |
| `[[[c:throne:latest_donor_name]]]` | text | empty | Latest Gifter Name |
| `[[[c:throne:latest_donation_amount]]]` | number | `0` | Latest Gift Amount |
| `[[[c:throne:latest_donation_message]]]` | text | empty | Latest Gift Message |
| `[[[c:throne:latest_donation_currency]]]` | text | empty | Latest Currency |
| `[[[c:throne:total_received]]]` | number | `0` | Total Throne Amount (session) |

## Specific to Throne

| Tag | Type | Default | Holds |
|---|---|---|---|
| `[[[c:throne:latest_item_name]]]` | text | empty | Latest Item Name |
| `[[[c:throne:latest_item_thumbnail_url]]]` | text | empty | Latest Item Thumbnail URL |
| `[[[c:throne:latest_is_surprise_gift]]]` | text | `0` | Latest Is Surprise Gift |

## Events that update them

`donation`

## Notes

- These are **service-managed** controls. Setting one by hand through the dashboard or the API returns a 403 - the integration owns the value.
- Referencing one in a template declares that dependency. Someone who copies the template without Throne connected is warned, not blocked, and it starts working the moment they connect.
- The shared keys above are identical across every donation integration, so swapping the prefix ports a template between services. See [[all-integration-controls]].

---

*Generated from the Throne driver by `php artisan help:build-integration-controls`. Do not edit by hand - your changes will be overwritten.*
