## Audit of OL-2609-100 - docs(claims): restate the erasure ordering, the Streamlabs scope and the two dropped PII columns

**Audited:** 2026-09-25
**Commit:** `4e9dfca4`
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `tests/Feature/AccountDeletionForkedKitTest.php:6-7 @4e9dfca` imports `Http` and `Storage`; `:14-17 @4e9dfca` is `beforeEach` with `Http::fake(['*' => Http::response(['data' => []], 200)])` and `Storage::fake('images')`. The `### Surface` block of `OL-2609-097/claim.md @4e9dfca` has no `AccountDeletion` path (grep exit 1). File unchanged @HEAD |
| C2 | CONFIRMED | `tests/Feature/AccountDeletionRedirectTest.php:6-7, 14-17 @4e9dfca` - the same imports and the identical `beforeEach`; also absent from OL-2609-097 Surface. File unchanged @HEAD |
| C3 | CONFIRMED | `app/Services/UserDeletionService.php:73 @4e9dfca` - `attempt('twitch eventsub subscriptions', ...)`; `:75` opens `DB::transaction`; `:120-122` are `stored images`, `fourthwall webhook`, `twitch token revocation`. Those four are the only `attempt(` calls besides the definition at `:238`. File unchanged @HEAD |
| C4 | CONFIRMED | `app/Services/UserDeletionService.php:50-51 @4e9dfca` - "Read what the external cleanup will need, and hand back the Twitch subscriptions, while the rows still exist."; the file is not in `git show --stat 4e9dfca` |
| C5 | CONFIRMED | `app/Http/Controllers/Settings/StreamLabsIntegrationController.php:61 @4e9dfca`, inside `redirect()` at `:44` - `'scope' => 'socket.token donations.read'`; `OL-2609-064/claim.md:13 @4e9dfca` records `socket.token donations.read donations.create`. File unchanged @HEAD |
| C6 | CONFIRMED | `CLAUDE.md:467 @4e9dfca` - "Scopes: `socket.token`, `donations.read`, `donations.create`"; `CLAUDE.md` is not in `git show --stat 4e9dfca`. @HEAD line 467 no longer names `donations.create` (commit `7685fdf3`, see Notes) |
| C7 | CONFIRMED | `database/migrations/2026_09_18_170000_drop_supporter_email_columns_from_external_events.php:33-35 @4e9dfca` drops `private_metadata`; `app/Models/ExternalEvent.php:47-61, 63-70 @4e9dfca` - neither `$fillable` nor `$casts` names it; no later migration in `database/` re-adds it @HEAD. `OL-2609-096/claim.md:36` is the superseded line |
| C8 | CONFIRMED | same migration `:29-31 @4e9dfca` drops `supporter_email_hash`; `app/Services/External/Drivers/BMACServiceDriver.php:86-90 @4e9dfca` unsets `data.supporter_email`, and the file's only `sha256` uses are the request-signature `hash_hmac` at `:50` and header names at `:32,:45`. `OL-2609-096/claim.md:40` is the superseded line. Unchanged @HEAD |

### Surface
Complete.

### Findings
None.

### Notes
- C6 describes `CLAUDE.md` as shipped; `7685fdf3` ("docs: correct the Streamlabs scope line in CLAUDE.md", 2 minutes later) rewrote line 467 with no `Changelog:` trailer. It is a markdown-only diff, which `claims-guide.md` exempts from needing a claim, so it is not reported as a finding.
- Remedy rows F1-F5 in `OL-2609-097/remedy.md @4e9dfca` were checked against `OL-2609-097/audit.md`: each finding maps to the claim lines it cites, and C5/C7/C8 cite OL-2609-064 and OL-2609-096 inline.
- C7 and C8 are restated by OL-2609-102 C4 and cited in `OL-2609-096/audit.md`; no conflict.
- No `[test]` claims; no tests were run.
