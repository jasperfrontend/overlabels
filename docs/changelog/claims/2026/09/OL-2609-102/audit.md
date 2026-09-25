## Audit of OL-2609-102 - docs(claims): restate who reads external_events.raw_payload, and the status of the Buy Me a Coffee email row

**Audited:** 2026-09-25
**Commit:** 0df54c66c88d90295eab30c3c1cc4ca52a9c929e
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/Admin/AdminTwitchEventController.php:103-110 @0df54c66` - `showExternal()` renders `admin/events/show-external` with `'event' => $externalEvent` (whole model); `app/Models/ExternalEvent.php:42-82 @0df54c66` declares `$fillable` and `$casts` (`raw_payload` => `array`) and no `$hidden`; `resources/js/pages/admin/events/show-external.vue:105 @0df54c66` prints `JSON.stringify(event.raw_payload, null, 2)`. All three files unchanged @HEAD (`git diff 0df54c66 HEAD` on them is empty) |
| C2 | CONFIRMED | `AdminTwitchEventController.php:42-50 @0df54c66` - external branch `->through()` returns 7 keys (`id`, `service`, `event_type`, `controls_updated`, `alert_dispatched`, `created_at`, `user`); lines 85-91 - Twitch branch returns 5 keys (`id`, `event_type`, `processed`, `created_at`, `user`); no payload key in either. Same @HEAD |
| C3 | CONFIRMED | `app/Http/Controllers/ExternalEventController.php:46 @0df54c66` - `replayForUser()` reads `$externalEvent->normalized_payload`; `show-external.vue:105 @0df54c66` stringifies the whole array. `git grep raw_payload 0df54c66 -- app/` finds the remaining DB readers selecting only `lat`, `lon`, `spd`, `alt`, `battery`, `session_id` (GPS) and `chatter_id` (`app/Services/ViewerErasureService.php:80`), none in `PayloadScrubber::DENIED_KEYS` (`PayloadScrubber.php:35-65 @0df54c66`). Same @HEAD |
| C4 | CONFIRMED | `app/Services/External/Drivers/BMACServiceDriver.php:84-93 @0df54c66` unsets `data.supporter_email`, `data.shipping_address`, `data.total_amount_charged`, `data.commission.shipping_address`; the only `sha256` in the file is the HMAC signature check (lines 32-52); `database/migrations/2026_09_18_170000_drop_supporter_email_columns_from_external_events.php:29-35 @0df54c66` drops both columns; `resources/help/pages/your-data.md:137-139 @0df54c66` lists "Email address \| Ko-fi, Fourthwall, Buy Me a Coffee" under "Removed on arrival". Same @HEAD |
| C5 | CONFIRMED | `tests/Feature/BMACWebhookTest.php:108-135 @0df54c66` asserts `raw['data']` lacks `supporter_email`, `total_amount_charged`, `shipping_address`, the DB row lacks `private_metadata` and `supporter_email_hash` keys, and the row JSON contains neither `john@example.com` nor its sha256 (fixture email at line 60). @HEAD the test is renamed to "... and the email retained nowhere" (OL-2609-103, disclosed in its Surface), body unchanged. `php artisan test --filter="stores event with PII stripped"` @HEAD: 1 passed (12 assertions); `--filter=BMACWebhookTest`: 10 passed |

### Surface
Complete. The diff's 2 paths are `docs/changelog/claims/2026/09/OL-2609-096/remedy.md` (listed) and the exempt claim file.

### Findings
- **F1** false assertion in Unchanged - the first Unchanged line states "every payload it renders is one `PayloadScrubber` or `BMACServiceDriver` has already stripped", but the detail route `routes/admin.php:53 @0df54c66` binds any `ExternalEvent`, and `GpsServiceDriver.php:85,124`, `CheckinServiceDriver.php:93` and `TowerServiceDriver.php:87 @0df54c66` store `raw: $payload` unscrubbed, so `show-external.vue:105` also renders payloads neither stripper touched; a later claim should restate that line as limited to the five donation services, or tag it as a claim and scope it.

### Notes
- `ViewerErasureService.php:80 @0df54c66` (`raw_payload->>'chatter_id'`) is a third DB reader of `raw_payload` that neither OL-2609-096 C6 nor this claim names; C1 does not claim completeness and `chatter_id` is not a denied key, so C3's conclusion holds.
- The `remedy.md` table resolves OL-2609-096 audit F1 and F2 as RECORD, matching C1-C3 and C4-C5 respectively.
- C1 and C4 are compound claims; every half was checked and is true, so no split verdict is needed.
- The Unchanged line calling `PayloadScrubber`'s docblock the same sentence as OL-2609-096 C6 is accurate: `PayloadScrubber.php:26-28 @0df54c66` still says the GPS aggregator is the only reader, and remains so @HEAD.
