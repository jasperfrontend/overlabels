## Audit of OL-2609-096 - strip supporter contact and postal details from stored integration payloads

**Audited:** 2026-09-18
**Commit:** f8361f190f5da926aee6a42b4df2437a792e9db0
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/External/PayloadScrubber.php:71-84 @f8361f19` - `scrub()` skips `is_string($key) && isset($denied[strtolower($key)])`, recurses with `is_array($value) ? self::scrub($value)` and reassigns `$clean[$key]`, so list and string keys both survive in source order; file byte-identical @HEAD (`git diff f8361f19 HEAD -- app/Services/External/PayloadScrubber.php` is empty) |
| C2 | CONFIRMED | `app/Services/External/PayloadScrubber.php:35-65 @f8361f19` - `DENIED_KEYS` holds exactly the 18 names listed, in that order; same @HEAD |
| C3 | CONFIRMED | `KofiServiceDriver.php:107`, `FourthwallServiceDriver.php:110`, `StreamLabsServiceDriver.php:100`, `ThroneServiceDriver.php:145 @f8361f19` all pass `PayloadScrubber::scrub($payload)`; `git grep -n "raw:" f8361f19 -- app/` returns bare `$payload` only at `GpsServiceDriver.php:85,124`, `CheckinServiceDriver.php:93`, `TowerServiceDriver.php:87`, plus BMAC's `$rawForStorage`; unchanged @HEAD |
| C4 | CONFIRMED | `BMACServiceDriver.php` absent from `git show --stat f8361f19`; `BMACServiceDriver.php:85-94 @f8361f19` builds `$rawForStorage` with `unset()` of `supporter_email`, `shipping_address`, `total_amount_charged`, `commission.shipping_address`, and lines 147-148 are the only `supporterEmail:`/`supporterEmailHash:` arguments in `app/`. @HEAD those two arguments and the sha256 are gone (8ad02ed3, OL-2609-097; restated in OL-2609-100 C8) |
| C5 | CONFIRMED | `normalizeEvent()` @f8361f19 reads exactly the keys listed - Ko-fi `KofiServiceDriver.php:76-95`, Fourthwall `FourthwallServiceDriver.php:77-84`, Throne `ThroneServiceDriver.php:100-121`, Streamlabs `StreamLabsServiceDriver.php:69-81` - none in `DENIED_KEYS`. Control values: `ThroneServiceDriver.php:170-190 @f8361f19` is the one driver that reads `$event->getRaw()` in `getControlUpdates()`, and it reads `event_type`, `data.item_name`, `data.item_thumbnail_url`, `data.is_surprise_gift`, none denied, so the stated conclusion holds |
| C6 | CONTRADICTED | `app/Http/Controllers/Admin/AdminTwitchEventController.php:82-89 @f8361f19` (`showExternal()`) hands a whole `ExternalEvent` to Inertia and `resources/js/pages/admin/events/show-external.vue:105 @f8361f19` prints `JSON.stringify(event.raw_payload)`; `index()` at lines 22-45 paginates 50 whole models the same way. `ExternalEvent.php:46-66 @f8361f19` declares no `$hidden`, so `raw_payload` is serialised. The GPS path is not the only reader. The half of C6 about GPS is true: `GpsSessionAggregator`, `GpsLivenessService`, `GpsSessionMapController`, `GpsSessionController @f8361f19` read only `lat`, `lon`, `spd`, `alt`, `battery`, `session_id` |
| C7 | CONFIRMED | `database/migrations/2026_09_18_150000_scrub_pii_from_stored_external_events.php:51-82 @f8361f19` - `SERVICES = ['kofi','fourthwall','streamlabs','bmac','throne']`, `whereIn('service', ...)->orderBy('id')->chunkById(500)`, `if ($clean === $payload) { continue; }`, `public function down(): void {}`; same @HEAD |
| C8 | CONFIRMED | same file lines 53-82 use `DB::table('external_events')` only; `private const DENIED_KEYS` at line 30 is a literal copy of the 18 names, `PayloadScrubber` is not imported or referenced |
| C9 | CONFIRMED | `tests/Unit/PayloadScrubberTest.php @f8361f19` (identical @HEAD) has 7 tests covering flat removal, nested depth, a list of items, case-insensitive names, numeric list keys, a clean payload via `toBe($payload)`, plus `verification_token`. `php artisan test --filter=PayloadScrubber` @HEAD: 12 passed (7 of them this file) |
| C10 | CONFIRMED | `tests/Feature/PayloadPiiScrubbingTest.php:70-115 @f8361f19` posts a Ko-fi `Commission` with `email`, `discord_username`, `discord_userid` and a `shipping` block, asserts the stored `raw_payload` lacks all four keys, that the encoded JSON lacks `alice@example.com`, `Example Road`, `1011AA`, `+3100000000`, and that `from_name`, `message`, `amount` survive. Passed @HEAD |
| C11 | CONFIRMED | same file lines 117-136 - `expect($event->raw_payload)->not->toHaveKey('verification_token')` and `->not->toContain('kofi-secret')`. Passed @HEAD |
| C12 | CONFIRMED | same file lines 138-168 - asserts `$raw['data']` has no `email` key, that the JSON does not contain `supporter@fourthwall.com`, and that `data.username`/`data.message` survive. The absent value is the supporter's email address; the fixture carries no postal address |
| C13 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a reverted tree, which the guide names as a legitimate use |
| C14 | UNVERIFIABLE | tagged [unverified]; production row inspection |
| C15 | CONFIRMED | `resources/help/pages/your-data.md:1-10 @f8361f19` declares `section: Getting started` and `context: settings.account`; `git grep -n "^context:" f8361f19 -- resources/help/pages/` shows 22 declarations and no other page names `settings.account` or a wildcard that matches it |

### Surface
Complete. The diff's 12 paths are the 10 listed plus `docs/changelog/claims/2026/09/OL-2609-096/claim.md` and `docs/changelog/changelog-2026-09.md`, both exempt.

### Findings
- **F1** false claim - C6 says the GPS path is the only code reading `raw_payload` back out of the database, but `AdminTwitchEventController::showExternal()` and `index()` (`app/Http/Controllers/Admin/AdminTwitchEventController.php:22-45,82-89 @f8361f19`) serialise whole `ExternalEvent` models, `raw_payload` included, into Inertia props that `admin/events/show-external.vue:105` renders. The claim's own Risk paragraph depends on that reader existing. The substance C6 was reaching for - that nothing consumes a denied key - still holds; treat C6 as an incomplete inventory of readers, not as evidence that the admin pages were checked.
- **F2** statement shipped ahead of the code - `resources/help/pages/your-data.md:134-141 @f8361f19` tells readers that an "Email address" from "Buy Me a Coffee" is "Removed on arrival", "before anything reaches the database", while at that same commit `ExternalWebhookController.php:151-170 @f8361f19` wrote a BMAC supporter's email as sha256 into `external_events.supporter_email_hash` and in plaintext into the `encrypted:array` `private_metadata` column - which C4 and the claim's own Unchanged section state. No action at HEAD: both columns were dropped by OL-2609-097 (8ad02ed3) and the page's line is now true of BMAC as well.

### Notes
- `PayloadScrubber.php`, the migration and both test files are byte-identical at HEAD; no undisclosed drift.
- C4's "only driver that also retains a hashed email" and the Unchanged lines about `private_metadata` and `supporter_email_hash` are superseded at HEAD by OL-2609-097 (and restated by OL-2609-100 C7/C8), not findings.
- The admin list-page projection F1 relies on was added at HEAD by OL-2609-097 (`->through()`), so only the detail page still serialises a payload.
- Tests run @HEAD: `php artisan test --filter=PayloadScrubber` (12 passed) and `php artisan test --filter=PayloadPiiScrubbingTest` (3 passed).
