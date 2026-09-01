## OL-2609-006 - feat(overlay): checkins iterable with live delta updates

**Shipped:** 2026-09-01
**Commit:** `git log --grep=OL-2609-006`

### Surface
- `app/Http/Controllers/OverlayTemplateController.php` - `buildCheckinData()`, allowlist-gated merge into the render payload, `checkins_window` field, imports
- `app/Models/User.php` - `checkins => 50` in `PREFERENCE_DEFAULTS['foreach_caps']`
- `resources/js/utils/checkinSlots.ts` - new: pure `checkins.*` slot builder (clamp, toPin, pinsFromData, upsertPin, withCheckinSlots)
- `resources/js/utils/checkinSlots.test.ts` - new file
- `resources/js/components/OverlayRenderer.vue` - `checkins.updated` listener + `handleCheckinsUpdated()`, `checkinsWindow` ref seeded from the payload
- `resources/js/pages/settings/Account.vue` - checkins row in the foreach caps UI
- `resources/js/types/index.d.ts` - `checkins` member on `ForeachCaps`
- `resources/js/utils/tagCompletions.ts` - `checkins` in `ITERABLES` + `ITEM_FIELDS`, `!checkins` bang snippet
- `resources/js/utils/tagCompletions.test.ts` - snippet expectations extended with checkins
- `tests/Feature/CheckinOverlayRenderTest.php` - new file (6 tests)
- `tests/Feature/SettingsForeachCapsTest.php` - payloads extended with the new required key

### Claims
- **C1** [code] `renderAuthenticated()` merges `checkins.count` + `checkins.N.*` into the payload only when the template's tag allowlist contains `checkins.count`, and ships `checkins_window` from `foreachCaps()['checkins']`.
- **C2** [test] `CheckinOverlayRenderTest` covers: window + cap shipped, no data without a checkins loop, per_stream empty with no open session, per_stream time-window filter, cap slicing pins but never the count, and no-integration rendering empty rather than erroring.
- **C3** [code] `handleCheckinsUpdated()` in `OverlayRenderer.vue` upserts the broadcast pin by login via `upsertPin()`, trims to `checkinsWindow`, and on `cleared` drops every `checkins.*` key before writing (`withCheckinSlots` is drop-then-write).
- **C4** [code] `clampCheckinsWindow()` falls back to `DEFAULT_CHECKINS_WINDOW` (50) on NaN or values below 1 - never to 1 - and caps at 50.
- **C5** [test] `checkinSlots.test.ts` pins the NaN fallback, latest-wins upsert, cap trim, the drop-then-write clearing, and the flat-data round trip.
- **C6** [code] No DSL or block-engine change: the client resolves any flat `checkins.N.*` key set, and PHP `extractForeachTags` already emits `checkins.count` for any foreach block, which is the allowlist signal C1 gates on.
- **C7** [code] `tagCompletions.ts` offers `checkins` (alias `pin`) as an iterable, its nine item fields in scope, and a `!checkins` snippet; `tagCompletions.test.ts` asserts the snippet set covers it.
- **C8** [code] The route validation for `PATCH /settings/foreach-caps` derives its rules from `PREFERENCE_DEFAULTS['foreach_caps']`, so the new key is required and clamped to `FOREACH_CAP_MAX` with no route change; the pre-existing structural test "every declared cap is settable through the endpoint" covers it.

### Unchanged
- `useConditionalTemplates.ts` and `resources/dsl/dsl.json` are not in the diff - C6 is why: the foreach engine synthesizes iterables from flat keys and needs no registry entry.
- `HTML_SAFE_FOREACH_FIELDS` in `OverlayRenderer.vue` still lists only `chat` - every checkin field is plain text and renders escaped.
- `TemplateDataMapperService::INDEXED_USER_SCOPE_FIELDS` and `OverlayTemplate::buildEffectiveForeachCaps()` are untouched: those slice the Twitch payload, which checkins is not part of; the allowlist expansion for unknown iterables (`FOREACH_DEFAULT_CAP`) is harmless and unused by the payload merge.
- The `checkins.updated` broadcast shape (OL-2609-004) is unchanged; this change only adds its consumer.

### Risk
Overlays cached in OBS keep their old bundle until reloaded; a `foreach:checkins` template added
before the overlay is reloaded shows the initial window but not live deltas. Reloading the browser
source resolves it - same as every renderer change.
