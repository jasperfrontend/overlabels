# All integration controls

Every control that an external integration provisions and keeps up to date, across all 7 services.

## The shared donation schema

Every donation integration provisions the same six keys, so a template written against one service ports to another by changing the prefix alone.

| Key | Type |
|---|---|
| `donations_received` | counter |
| `latest_donor_name` | text |
| `latest_donation_amount` | number |
| `latest_donation_message` | text |
| `latest_donation_currency` | text |
| `total_received` | number |

## Per service

| Service | Prefix | Controls | Beyond the shared six |
|---|---|---|---|
| [[kofi]] | `c:kofi:` | 6 | nothing |
| [[gps]] | `c:gps:` | 13 | does not use the shared schema |
| [[streamlabs]] | `c:streamlabs:` | 6 | nothing |
| [[streamelements]] | `c:streamelements:` | 6 | nothing |
| [[fourthwall]] | `c:fourthwall:` | 6 | nothing |
| [[bmac]] | `c:bmac:` | 7 | `latest_support_type` |
| [[throne]] | `c:throne:` | 9 | `latest_item_name`, `latest_item_thumbnail_url`, `latest_is_surprise_gift` |

## Notes

- Overlabels GPS is the odd one out: it is an integration, but it carries location and device telemetry rather than donations, so it shares none of the six keys.
- All of these are service-managed. They are read-only to the user and to the API.
- Every value is a string on the wire. Use a formatter pipe to present it: `[[[c:kofi:total_received|currency:EUR]]]`.

---

*Generated from App\Services\External\ExternalServiceRegistry by `php artisan help:build-integration-controls`. Do not edit by hand - your changes will be overwritten.*
