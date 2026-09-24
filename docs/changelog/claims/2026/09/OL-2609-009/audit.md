## Audit of OL-2609-009 - fix(checkin): unit-free distance names, presentation via the distance pipe

**Audited:** 2026-09-24
**Commit:** 6d9e098d7ae21238829caff99bb25f2859ef9e4b
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONTRADICTED (compound) | Half 1, "no formatter code changed", CONFIRMED: neither `formatters.ts` nor `PipeFormatter.php` is in `git show --stat 6d9e098`. Half 2, "`formatters.ts` input-assumed-km", CONFIRMED: `resources/js/utils/formatters.ts:312-326 @6d9e098` - `formatDistance()` "Input is assumed to be in kilometers", `mi` divides by 1.609344. Half 3, "mirrored by `PipeFormatter::distance()` in PHP", CONTRADICTED: `app/Services/Messages/PipeFormatter.php:129-143 @6d9e098` reads the value as `$meters`, `'km' => $meters / 1000`, `'mi' => $meters / 1609.344`, and defaults an empty arg to `km`. @HEAD `PipeFormatter.php:247-263` treats input as km (changed by `9d94a380`, OL-2609-011) |
| C2 | CONFIRMED | `BotCheckinController.php:118-121 @6d9e098` - `$distanceKm = round(GeoMath::haversineDistance(...))`; `GeoMath.php @6d9e098` "Returns distance in kilometers" (`$earthRadiusKm = 6371.0`); the same `$distanceKm` goes to the `distance_km` column (`:151`) and the payload `distance` (`:177`); `CheckinServiceDriver.php @6d9e098` copies `$raw['distance']` unchanged into `latest_checkin_distance`, `farthest_checkin_this_stream` and `event.distance`; `Checkin::toPinArray()` `:111 @6d9e098` emits `distance_km` as `distance`. GPS: `GpsServiceDriver.php:139,145 @6d9e098` labels `distance`/`session_distance` km, `:309` adds haversine km. Same @HEAD |
| C3 | CONFIRMED | `CheckinServiceDriver.php @6d9e098` - `PER_STREAM_CONTROL_KEYS` and `getAutoProvisionedControls()` carry `farthest_checkin_this_stream` / `latest_checkin_distance` with labels "Farthest Checkin This Stream" / "Latest Checkin Distance"; `'event.distance'`; `checkinSlots.ts` `PIN_FIELDS` ends in `'distance'`. `git grep` @6d9e098 finds no old key/field/tag name outside the DB column, GPS code and the migration. Same @HEAD (`CheckinServiceDriver.php:36,107,118,81`), with `farthest_checkin_name_this_stream` added by OL-2609-013 |
| C4 | CONFIRMED | `database/migrations/2026_09_02_090000_rename_checkin_distance_control_keys.php:35-43 @6d9e098` - `DB::table('overlay_controls')->where('source','checkin')->where('source_managed',true)->where('key',$from)->update(['key'=>..., 'label'=>...])`; no model reference, `value`/`sort_order`/`updated_at` not in the update array (query builder does not stamp timestamps). Driver provisions the new keys (C3). File unchanged @HEAD |
| C5 | CONFIRMED (compound, see F3) | `tests/Feature/BotCheckinTest.php:225-226 @6d9e098` asserts `latest_checkin_distance` and `farthest_checkin_this_stream` > 400; `:237,242` upward-only; `:283` go-live reset to `'0'`. Ran `php artisan test --filter='BotCheckinTest\|IntegrationControlsReferenceTest\|IntegrationEventTagDocsTest\|StreamControlResetTest\|CheckinOverlayRenderTest'` @HEAD: 56 passed (188 assertions). Pest could not be run @6d9e098 (HEAD `vendor/` lacks `Stevebauman\Location\LocationServiceProvider`). The "50 tests in the targeted run, full gate green" half is a past run and cannot be checked |
| C6 | CONFIRMED | Driver `'event.*'` literals @6d9e098: `user_name`, `user_login`, `place`, `country`, `country_code`, `lat`, `lng`, `distance`; all eight appear as `[[[event.x` in `resources/help/reference/eventsub-tags/all-chat-checkin-events.md @6d9e098` (`[[[event.distance]]]` at `:10`). `tests/Feature/IntegrationEventTagDocsTest.php @6d9e098` asserts `str_contains($docs, "[[[{$tag}]]]")` per driver tag; passed @HEAD in the run above |

### Surface
Phantom: `resources/help/reference/integration-controls/all-integration-controls.md` - listed as "regenerated", not in the diff.

### Findings
- **F1** claim contradicted - C1 says `PipeFormatter::distance()` mirrors the km input contract, but `app/Services/Messages/PipeFormatter.php:129-143 @6d9e098` assumes meters and divides by 1000 for `km`, so at ship time `[[[c:checkin:farthest_checkin_this_stream|distance:km]]]` in a bot reply or alert TTS/chat rendered a value 1000x too small; OL-2609-011 fixed the code a day later but does not cite OL-2609-009 C1 inline, so a new claim should record the correction against C1.
- **F2** phantom path - Surface lists `resources/help/reference/integration-controls/all-integration-controls.md` as "regenerated", but it is not in `git show --stat 6d9e098`, and @6d9e098 its checkin row (`:24`) names no control key, so nothing in it needed to change; the record should drop the line.
- **F3** compound claim / mistagged half - C5 joins a checkable test assertion with "50 tests in the targeted run, full gate green", which is an observation of a past run that `[test]` cannot carry; that half should have been a separate `[unverified]` claim.

### Notes
- HEAD drift disclosed by later claims: `PipeFormatter::distance()` km contract (OL-2609-011, then OL-2609-012 parity); `farthest_checkin_name_this_stream` added to `PER_STREAM_CONTROL_KEYS` (OL-2609-013); `BotCheckinController` reply formatting (`4fd61b2e`).
- The migration's `down()` (`:46-54 @6d9e098`) restores the old keys but not the "(km)" labels. No claim covers `down()`.
- C3 renames a key that OL-2609-004 C3 lists in `PER_STREAM_CONTROL_KEYS`; this claim does not cite OL-2609-004. That is an update to a recorded fact, not to a rule.
- Commit author date is 2026-09-01 12:41 +0200; the claim's Shipped line and the migration filename say 2026-09-02.
- Vitest `checkinSlots.test.ts` + `globeTag.test.ts`: 25 passed @6d9e098 (temporary worktree, since removed) and @HEAD.
