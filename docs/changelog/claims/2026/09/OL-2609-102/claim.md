## OL-2609-102 - docs(claims): restate who reads external_events.raw_payload, and the status of the Buy Me a Coffee email row

Remedies OL-2609-096 audit F1, F2.

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-102`

### Surface
- `docs/changelog/claims/2026/09/OL-2609-096/remedy.md` - new: the outcome of each finding in that ID's audit

### Claims
- **C1** [code] `AdminTwitchEventController::showExternal()` passes a whole `ExternalEvent` model to Inertia as the `event` prop and `ExternalEvent` declares no `$hidden`, so `raw_payload` is serialised into the admin event detail page and printed there by `resources/js/pages/admin/events/show-external.vue` as `JSON.stringify(event.raw_payload, null, 2)`. The GPS path is not the only code reading `raw_payload` back out of the database (corrects OL-2609-096 C6, audit F1).
- **C2** [code] `AdminTwitchEventController::index()` projects both branches with `->through()` - seven fields for an external event, five for a Twitch event, neither including a payload - so no admin list page serialises `raw_payload` (audit F1).
- **C3** [code] Neither reader in C1 and C2 selects a key by name out of a donation payload: the detail page stringifies the whole array, and `ExternalEventController::replayForUser()` reads `normalized_payload`, not `raw_payload`. Scrubbing a `PayloadScrubber::DENIED_KEYS` key therefore changes no reader's behaviour, which is what OL-2609-096 C6 concluded from an incomplete inventory (audit F1).
- **C4** [code] `BMACServiceDriver::normalizeEvent()` unsets `data.supporter_email`, `data.shipping_address`, `data.total_amount_charged` and `data.commission.shipping_address` from the payload it stores and computes no hash of the email, and `external_events` has neither a `supporter_email_hash` nor a `private_metadata` column (restating OL-2609-100 C7 and C8). The `resources/help/pages/your-data.md` "Removed on arrival" row naming "Email address" for "Buy Me a Coffee" is true of the tree as it stands (corrects OL-2609-096 audit F2).
- **C5** [test] `tests/Feature/BMACWebhookTest.php` "stores event with PII stripped from raw_payload and email captured into private metadata" asserts the stored row's `raw_payload.data` has no `supporter_email`, `total_amount_charged` or `shipping_address`, that the database row has no `private_metadata` and no `supporter_email_hash` key, and that the row's JSON contains neither `john@example.com` nor its sha256 (audit F2).

### Unchanged
- `app/Http/Controllers/Admin/AdminTwitchEventController.php` and `resources/js/pages/admin/events/show-external.vue`, the subjects of C1, are not in this diff. The finding is that OL-2609-096 C6 missed a reader, not that the detail page should stop rendering a payload; every payload it renders is one `PayloadScrubber` or `BMACServiceDriver` has already stripped.
- `app/Services/External/PayloadScrubber.php` is not in this diff. Its class docblock carries the same sentence as OL-2609-096 C6, that "the only code that reads `raw_payload` back out is the GPS aggregator"; C1 is the correction of record, and a comment edit is not a change a test can hold red then green.
- `resources/help/pages/your-data.md` is not in this diff. Its Buy Me a Coffee line described a tree that arrived one commit later, and C4 and C5 state that it has arrived.
- `docs/changelog/claims/2026/09/OL-2609-096/claim.md` and `audit.md` are not in this diff. A shipped claim is not rewritten; the correction is this file.
