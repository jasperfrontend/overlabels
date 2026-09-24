## Audit of OL-2609-010 - fix(checkin): gate !checkin on a confidently live stream, speak the stored distance

**Audited:** 2026-09-24
**Commit:** 4fd61b2e902ec8c1bab808115c8975515e9c338a
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/Api/Internal/BotCheckinController.php:98-100 @4fd61b2` - `if (! StreamSessionService::isLive($user))` returns `['reply' => 'Checkins open when the stream is live. Come back then!']`; `StreamSessionService::isLive()` is `StreamState::forUser($user)->isConfidentlyLive()` (`app/Services/StreamSessionService.php:478-481 @4fd61b2`); the return comes before `resolve()` (`:102`), `applyCheckin()` (`:108`, which does the upsert and the `CheckinsUpdated` dispatch at `:159-179`) and `runPipeline()` (`:110`). @HEAD the gate is at `:110-111`, unchanged |
| C2 | CONFIRMED | `BotCheckinController.php:87-89 @4fd61b2` - the silent `Cache::add` cooldown return precedes the gate at `:98`, and the gate returns a non-null reply. The "would make the viewer retype it" clauses are rationale, not checkable. Same order @HEAD |
| C3 | CONTRADICTED (compound) | Half 1: `tests/Feature/BotCheckinTest.php:126-139 @4fd61b2` asserts reply `toContain('live')` (a substring, not the reply text), `Checkin::count()` 0, `ExternalEvent::count()` 0, and exactly two controls: `checkins_this_stream` = `'0'` and `latest_checkin_name` = `''`. The other eight of the ten controls `CheckinServiceDriver::getAutoProvisionedControls()` provisions (`app/Services/External/Drivers/CheckinServiceDriver.php:104-113 @4fd61b2`) are not asserted, so "untouched controls" is narrower in the test than in the claim. Half 2 CONFIRMED: `:141-149 @4fd61b2` asserts the second `postCheckin()` reply `toBeNull()`. Ran `php artisan test --filter=BotCheckinTest` @HEAD: 21 passed (65 assertions); both test bodies are identical @HEAD |
| C4 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a tree that no longer exists |
| C5 | CONFIRMED | `BotCheckinController.php:119-120 @4fd61b2` - `rtrim(rtrim(number_format((float) $payload['distance'], 1), '0'), '.')`, replacing `number_format((float) $payload['distance'])` (removed line in the diff). The stored value is `round(..., 1)` km (`:138-143 @4fd61b2`); `formatDistance()` passes km through with `maximumFractionDigits: 2` (`resources/js/utils/formatters.ts:317-326 @4fd61b2`), so 57.5 renders 57.5 in en-US. @HEAD same expression at `:131` |
| C6 | CONFIRMED | `tests/Feature/BotCheckinTest.php:265 @4fd61b2` - `->and($response->json('reply'))->toContain((string) $pin->distance_km)` in "a home location gives checkins a distance"; passed in the run above; body identical @HEAD |

### Surface
Complete.

### Findings
- **F1** test narrower than claim - C3 says "checkin is refused while the stream is offline" asserts the reply and untouched controls, but `tests/Feature/BotCheckinTest.php:134-138 @4fd61b2` only checks that the reply contains `live` and that two of the ten provisioned checkin controls (`checkins_this_stream`, `latest_checkin_name`) hold their defaults; a new claim should restate C3 as tested, or the test should assert the exact reply and the full control set.

### Notes
- Unchanged: `handleEvent()`, `applyChatSummary()`, `resetCheckinControls()` and `PipeFormatter::distance()` are all absent from the diff. `PipeFormatter.php:129-143 @4fd61b2` does read input as meters, which contradicts OL-2609-009 C1; this claim does not cite OL-2609-009 (already reported as OL-2609-009 audit F1). The PHP pipe moved to km later in OL-2609-011.
- The second Unchanged line ends with a judgment ("make the `_this_stream` label true at all times") that belongs in Claims under a tag if it belongs anywhere.
- HEAD drift disclosed by OL-2609-099: `BotCheckinController::store()` gains an `isSuppressed()` silent return before the args check, and the success reply goes through `ViewerNoticeService::decorate()`. The offline refusal is not decorated. OL-2609-013 adds unrelated tests to `BotCheckinTest.php`.
- The `applyCheckin()` comment "Off-stream, only a brand new pin counts" (`:150-153 @4fd61b2`) predates the gate and is not in the diff.
- Pest ran @HEAD only. The named test bodies and the gate code are byte-identical to @4fd61b2.
