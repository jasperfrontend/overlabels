## Audit of OL-2609-013 - feat(checkin): farthest_checkin_name_this_stream names the record holder

**Audited:** 2026-09-24
**Commit:** 09aa6e805e8766f77ff2909200b761a9dc0d7708
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/External/Drivers/CheckinServiceDriver.php:111 @09aa6e8` - `'type' => 'text'`, `'config' => ['reset_value' => '']`; `app/Services/StreamSessionService.php:444 @09aa6e8` - `resetCheckinControls()` writes `(string) ($control->config['reset_value'] ?? 0)`, and `''` is not null so `''` is written. Same @HEAD (`CheckinServiceDriver.php:111`, `StreamSessionService.php:447`) |
| C2 | CONFIRMED | `CheckinServiceDriver.php:190-191 @09aa6e8` - name set only inside `if ((float) $distanceKm > $current)`, where `$current` is `farthest_checkin_this_stream` (`:184`); equal distance (tie, replay) fails the strict `>`. Same @HEAD |
| C3 | CONFIRMED | `CheckinServiceDriver.php:33-38 @09aa6e8` - `PER_STREAM_CONTROL_KEYS` holds four keys including `farthest_checkin_name_this_stream`; `StreamSessionService.php:438 @09aa6e8` filters on that constant; `StreamSessionService.php` is not in the diff. Same @HEAD |
| C4 | CONFIRMED | Compound, all halves true. `database/migrations/2026_09_02_100000_backfill_farthest_checkin_name_control.php:26,31,41 @09aa6e8` - only `DB::table('external_integrations')` / `DB::table('overlay_controls')`, no model import (`:3-4`), skip when the row exists (`:31-39`); `app/Http/Controllers/Settings/CheckinIntegrationController.php:105 @09aa6e8` calls `provision()` at connect; `app/Services/External/ExternalControlService.php:174-176 @09aa6e8` `continue`s when no control row matches the key; `app/Http/Controllers/OverlayTemplateController.php:127-130 @09aa6e8` feeds the editor autocomplete from `userScopedControlsFor()` (control rows) |
| C5 | CONFIRMED | `tests/Feature/BotCheckinTest.php @09aa6e8` - `:270` `farthest checkin only ever moves up` asserts name `ViewerOne` stands after closer Rotterdam checkin by ViewerTwo; `:289` `a farther checkin takes the record name with it` asserts `ViewerOne` then `ViewerTwo` after Paris; `:350` `the go-live reset clears the farthest name to empty, never the string zero` asserts `toBe('')` after `openSession()`. `php artisan test --filter=BotCheckinTest` @HEAD: 21 passed (65 assertions). Test file unchanged between @09aa6e8 and @HEAD |
| C6 | CONFIRMED | `app/Console/Commands/BuildIntegrationControlsReference.php:28,136,242 @09aa6e8` generates the "provisions %d control" line and `empty` default cell seen in the diff; `resources/help/reference/integration-controls/checkin.md` is byte-identical @09aa6e8 and @HEAD, and `php artisan help:build-integration-controls --check` @HEAD reports "All 9 integration-controls entries are current." The other hand-edited paths in the diff are `pages/checkin.md` and `all-chat-checkin-events.md`; `all-integration-controls.md` is generator output (`:119`) |

### Surface
Complete.

### Findings
None.

### Notes
- `--check` could not be run @09aa6e8 (HEAD `vendor/` lacks `Stevebauman\Location\LocationServiceProvider`); C6 rests on the file being unchanged since and passing `--check` @HEAD.
- Commit date is 2026-09-01 14:29 +0200; the claim says Shipped 2026-09-02 (push date, not checkable in-repo) and the migration is dated `2026_09_02_100000`.
- OL-2609-004 C3 ("exactly ten" checkin controls) is superseded by this change (eleven), not cited inline; an updated fact, not a reversed rule (same reading as the OL-2609-009 audit).
- `down()` deletes every `source='checkin'` source-managed row with this key, including rows `provision()` created after the migration ran, not only backfilled ones.
- @HEAD `app/Services/ViewerErasureService.php:43` also names this key; added by a later commit, not drift of any symbol this claim describes.
