## OL-2609-100 - docs(claims): restate the erasure ordering, the Streamlabs scope and the two dropped PII columns

Remedies OL-2609-097 audit F1, F2, F3, F4, F5.

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-100`

### Surface
- `docs/changelog/claims/2026/09/OL-2609-097/remedy.md` - new: the outcome of each finding in that ID's audit

### Claims
- **C1** [code] `tests/Feature/AccountDeletionForkedKitTest.php` declares a `beforeEach` calling `Http::fake(['*' => Http::response(['data' => []], 200)])` and `Storage::fake('images')`, and imports both facades. The file is not listed in OL-2609-097's Surface (corrects OL-2609-097 Surface, audit F1).
- **C2** [code] `tests/Feature/AccountDeletionRedirectTest.php` declares the identical `beforeEach` and the same two imports, and is likewise not listed in OL-2609-097's Surface (corrects OL-2609-097 Surface, audit F2).
- **C3** [code] `UserDeletionService::eraseAccount()` calls `attempt('twitch eventsub subscriptions', ...)` BEFORE the `DB::transaction(...)` it opens on the next statement, so exactly three of the four external steps - `stored images`, `fourthwall webhook`, `twitch token revocation` - run after the commit (corrects OL-2609-097 C16, audit F3).
- **C4** [code] That ordering is the method's documented phase 1: its docblock reads "Read what the external cleanup will need, and hand back the Twitch subscriptions, while the rows still exist." The code is not in the diff of this change (audit F3).
- **C5** [code] `StreamLabsIntegrationController::redirect()` builds `'scope' => 'socket.token donations.read'`, one scope narrower than the string recorded as unchanged in OL-2609-064 C1 (corrects OL-2609-064 C1, audit F4).
- **C6** [code] `CLAUDE.md`'s StreamLabs section still lists the requested scopes as `socket.token`, `donations.read`, `donations.create`, which `StreamLabsIntegrationController::redirect()` contradicts. `CLAUDE.md` is not in the diff of this change (audit F4).
- **C7** [code] `external_events` has no `private_metadata` column: `2026_09_18_170000_drop_supporter_email_columns_from_external_events.php` drops it, and `ExternalEvent` declares it in neither `$fillable` nor `$casts`. This supersedes OL-2609-096's Unchanged line stating it "remains the `encrypted:array` column" (corrects OL-2609-096 Unchanged, audit F5).
- **C8** [code] `external_events` has no `supporter_email_hash` column and `BMACServiceDriver` computes no sha256 of a supporter email, only unsetting `data.supporter_email` from the stored payload. This supersedes OL-2609-096's Unchanged line stating it "is still written by BMAC" (corrects OL-2609-096 Unchanged, audit F5).

### Unchanged
- `app/Services/UserDeletionService.php` is the subject of C3 and C4 and is not in this diff. The audit found the claim wrong about the ordering, not the ordering wrong.
- `app/Http/Controllers/Settings/StreamLabsIntegrationController.php` is the subject of C5 and is not in this diff. Dropping `donations.create` was the intended change in OL-2609-097; only its cross-reference to OL-2609-064 was missing.
- `tests/Feature/AccountDeletionForkedKitTest.php` and `tests/Feature/AccountDeletionRedirectTest.php`, the subjects of C1 and C2, are not in this diff. Their `beforeEach` is required by the outbound calls OL-2609-097 C14 added; it was undisclosed, not wrong.
- `docs/changelog/claims/2026/09/OL-2609-097/claim.md` and `audit.md`, and `docs/changelog/claims/2026/09/OL-2609-096/claim.md` and `OL-2609-064/claim.md`, are not in this diff. A shipped claim is not rewritten; the correction is this file.
