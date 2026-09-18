## OL-2609-096 - fix(privacy): strip supporter contact and postal details from stored integration payloads, and document the whole data tree

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-096`

### Surface
- `app/Services/External/PayloadScrubber.php` - new file: `DENIED_KEYS` and a recursive `scrub(array): array`
- `app/Services/External/Drivers/KofiServiceDriver.php` - imports `PayloadScrubber`; `raw:` argument now `PayloadScrubber::scrub($payload)`
- `app/Services/External/Drivers/FourthwallServiceDriver.php` - same two changes
- `app/Services/External/Drivers/ThroneServiceDriver.php` - same two changes
- `app/Services/External/Drivers/StreamLabsServiceDriver.php` - same two changes
- `database/migrations/2026_09_18_150000_scrub_pii_from_stored_external_events.php` - new file: one-way backfill over existing `external_events` rows for the five donation services
- `tests/Unit/PayloadScrubberTest.php` - new file, 7 tests
- `tests/Feature/PayloadPiiScrubbingTest.php` - new file, 3 tests
- `resources/help/pages/your-data.md` - new help guide, `section: Getting started`, `context: settings.account`
- `resources/help/pages/index.md` - the new guide linked under `### Getting started`

### Claims
- **C1** [code] `PayloadScrubber::scrub()` removes every key whose lowercased name is in `DENIED_KEYS`, at any nesting depth, and recurses into both associative and list arrays. Non-matching keys, including numeric list keys, are preserved in order.
- **C2** [code] `DENIED_KEYS` contains `email`, `supporter_email`, `buyer_email`, `donor_email`, `gifter_email`, `customer_email`, `contact_email`, `phone`, `phone_number`, `telephone`, `shipping`, `shipping_address`, `billing_address`, `delivery_address`, `address`, `discord_userid`, `discord_username`, `verification_token`.
- **C3** [code] Each of the four drivers passes `PayloadScrubber::scrub($payload)` as the `raw:` argument to `NormalizedExternalEvent`. No driver passes a bare `$payload` to `raw:` any more except `GpsServiceDriver`, `CheckinServiceDriver` and `TowerServiceDriver`.
- **C4** [code] `BMACServiceDriver` is not in the diff. It already built its own `$rawForStorage` with explicit `unset()` calls and is the only driver that also retains a hashed email.
- **C5** [code] No driver reads any key in `DENIED_KEYS` while normalising. Ko-fi reads `from_name`, `message`, `amount`, `currency`, `kofi_transaction_id`, `tier_name`, `url` and the two subscription flags; Fourthwall reads `data.id`, `data.username`, `data.message`, `data.amounts.total.*`, `data.status`; Throne and Streamlabs likewise read none. Scrubbing therefore cannot change a template tag or a control value.
- **C6** [code] The only code that reads `raw_payload` back out of the database is the GPS path (`GpsSessionAggregator`, `GpsLivenessService`, `GpsSessionMapController`, `GpsSessionController`), which reads `lat`, `lon`, `spd`, `alt`, `battery` and `session_id`. GPS is not scrubbed and none of those keys is in `DENIED_KEYS`.
- **C7** [code] The migration updates `external_events.raw_payload` for `service IN ('kofi','fourthwall','streamlabs','bmac','throne')` only, chunked by id, skipping rows whose scrubbed payload is identical. `down()` is an empty method.
- **C8** [code] The migration uses `DB::table('external_events')` and references no Eloquent model, and freezes its own copy of the key list as a private constant rather than reading `PayloadScrubber::DENIED_KEYS`.
- **C9** [test] `PayloadScrubberTest` asserts flat removal, nested removal, removal inside a list of items, case-insensitive matching, numeric-key preservation, and that a clean payload is returned byte-identical.
- **C10** [test] `PayloadPiiScrubbingTest` posts a real Ko-fi `Commission` webhook carrying `email`, `shipping`, `discord_username` and `discord_userid`, and asserts the stored `ExternalEvent::raw_payload` contains none of those keys and that the JSON contains none of the values, while `from_name`, `message` and `amount` survive.
- **C11** [test] The same file asserts a stored Ko-fi row contains no `verification_token` key and that its JSON does not contain the integration's secret.
- **C12** [test] The same file asserts a Fourthwall `DONATION` row has no `data.email` key and that the JSON does not contain the supporter address, while `data.username` and `data.message` survive.
- **C13** [unverified] All three tests in `PayloadPiiScrubbingTest` were run against the pre-fix tree on 2026-09-18, by reverting only the `raw:` argument in the Ko-fi and Fourthwall drivers, and all three failed.
- **C14** [unverified] Production `external_events` rows were inspected on 2026-09-18 by key name only, never by value. All 21 stored Ko-fi rows carried a non-empty `email` and a non-empty `verification_token`; 6 carried `discord_username`; `shipping` was present as a key on all 21 and JSON-null on all 21, no shop order or commission having been received. The single Throne row and the 6 Streamlabs rows carried no email key.
- **C15** [code] `resources/help/pages/your-data.md` declares `section: Getting started` and `context: settings.account`, and `settings.account` is claimed by no other help page.

### Unchanged
- `external_events.raw_payload` is still a plain `array` cast on `ExternalEvent` and still unencrypted jsonb. The fix removes what goes into it rather than encrypting the column, because the values removed are ones nothing reads; `private_metadata` remains the `encrypted:array` column and is still written only by BMAC.
- `NormalizedExternalEvent` is not in the diff. Its `$raw` property was already documented as "payload-as-stored: PII already stripped by the driver" - the contract was correct and only four drivers failed to honour it.
- `ExternalWebhookController::handle()` still stores `$normalizedEvent->getRaw() ?: $payload`. The `?:` fallback is untouched and still reached only by a driver that returns an empty raw array; every scrubbed driver returns a non-empty one for any payload that has a non-denied key.
- The 90-day prune of `external_events` in `routes/console.php` is unchanged. The backfill exists because it would otherwise take 90 days to achieve the same thing.
- `supporter_email_hash` is still written by BMAC and still read by nothing. It was not extended to the other services, because adding an identifier nothing consumes would be storing more, not less.

### Risk
The migration rewrites existing rows and cannot be reversed. It touches only keys that no code reads,
so replay, the events feed and the admin events page continue to work; an admin viewing an old
donation event will no longer see a supporter's email or address in the raw payload, which is the
intent. Ko-fi shop orders and commissions will no longer deliver a postal address to anything,
including a future feature that might have wanted one.
