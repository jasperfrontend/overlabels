## OL-2609-009 - fix(checkin): unit-free distance names, presentation via the distance pipe

**Shipped:** 2026-09-02
**Commit:** `git log --grep=OL-2609-009`

### Surface
- `database/migrations/2026_09_02_090000_rename_checkin_distance_control_keys.php` - new: renames provisioned rows in place
- `app/Services/External/Drivers/CheckinServiceDriver.php` - control keys/labels, `PER_STREAM_CONTROL_KEYS`, `event.distance` tag, wire field `distance`
- `app/Http/Controllers/Api/Internal/BotCheckinController.php` - wire payload field `distance` (DB column write unchanged)
- `app/Models/Checkin.php` - pin array field `distance` (reads the `distance_km` column)
- `resources/js/utils/checkinSlots.ts` - `PIN_FIELDS` `distance`
- `resources/js/utils/checkinSlots.test.ts` - fixture field renamed
- `resources/js/globe/brandPin.ts` - `BRAND_PIN` field renamed
- `resources/js/globe/globeTag.test.ts` - fixture field renamed
- `resources/js/utils/tagCompletions.ts` - `ITEM_FIELDS.checkins` `distance`
- `resources/js/components/controls/controlPresets.ts` - `CHECKIN_PRESETS` keys/labels
- `resources/js/pages/settings/integrations/checkin.vue` - copy names the new tags and the `|distance:km` pipe
- `resources/help/pages/checkin.md` - new tag names + a km/mi pipe example
- `resources/help/reference/eventsub-tags/all-chat-checkin-events.md` - `event.distance` + pipe usage
- `resources/help/reference/integration-controls/checkin.md` - regenerated
- `resources/help/reference/integration-controls/all-integration-controls.md` - regenerated
- `tests/Feature/BotCheckinTest.php` - control key references renamed

### Claims
- **C1** [code] No formatter code changed: `|distance:km` / `|distance:mi` already existed in `formatters.ts` with input-assumed-km, mirrored by `PipeFormatter::distance()` in PHP, and the renamed tags simply become valid pipe inputs.
- **C2** [code] Values stay stored in kilometers everywhere (haversine at checkin time, control values, `event.distance`, the `checkins.N.distance` slot), matching the distance pipe's documented input unit and GPS's convention.
- **C3** [code] The renames are: control `farthest_checkin_km_this_stream` -> `farthest_checkin_this_stream`, control `latest_checkin_distance_km` -> `latest_checkin_distance`, foreach field `distance_km` -> `distance`, event tag `event.distance_km` -> `event.distance`; labels drop the "(km)" suffix.
- **C4** [code] The migration UPDATEs existing `source='checkin'` `source_managed` rows to the new keys and labels via literal `DB::table`, preserving values, `sort_order` and timestamps; the driver provisions the new names for future connects.
- **C5** [test] `BotCheckinTest` exercises the new keys (distance set, farthest upward-only, go-live reset to 0) and the full affected set (`IntegrationControlsReferenceTest`, `IntegrationEventTagDocsTest`, `StreamControlResetTest`, checkin suites) passes - 50 tests in the targeted run, full gate green.
- **C6** [code] Every `event.*` literal in the driver appears in the regenerated/updated reference pages, which `IntegrationEventTagDocsTest` enforces.

### Unchanged
- The `checkins.distance_km` DB column keeps its name: it never reaches a template, and the unit
  in the column name documents what is at rest; `Checkin::toPinArray()` maps it to the unit-free
  field. Same for the internal GPS settings keys (`session_distance_km`), which are a different
  integration and not in the diff.
- GPS's `distance` / `session_distance` controls (stored km, no unit in the key) already follow
  this convention and are untouched.
- `formatters.ts` and `PipeFormatter.php` are not in the diff (C1).

### Risk
Templates written against the day-old tags render the old names as nothing after deploy. The
window is one day and the only connected integration is the maintainer's; there is no
compatibility alias on purpose. Historical `external_events.normalized_payload` rows keep
`event.distance_km` as recorded - replaying one of those events feeds an alert the old tag name.
