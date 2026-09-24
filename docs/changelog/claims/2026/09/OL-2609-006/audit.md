## Audit of OL-2609-006 - feat(overlay): checkins iterable with live delta updates

**Audited:** 2026-09-25
**Commit:** 8643a2d23b603a12f21fc7cec3a08983311442be
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/OverlayTemplateController.php:859-860 @8643a2d2` - `if (in_array('checkins.count', $allowlist, true))` merges `buildCheckinData()` (`:985`), which writes `checkins.count` and `checkins.{$i}.{$field}` (`:1001`, `:1006`); `:960` ships `'checkins_window' => $user->foreachCaps()['checkins']`. Same at @HEAD (`:866`, `:978`) |
| C2 | UNVERIFIABLE | Not run, so this is not a pass: `tests/Feature/CheckinOverlayRenderTest.php @8643a2d2` exists with 6 tests whose assertions match the six listed cases (window + `checkins_window` 50; no `checkins.count` without a loop; per_stream no session count `'0'`; per_stream count `'1'` with only `live_viewer`; cap 2 slices pins, count `'3'`, `checkins_window` 2; no integration count `'0'`). `php artisan test` fails in this checkout (no `vendor/autoload.php`), and running Pest through the main checkout's vendor was refused by the permission system |
| C3 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:1029-1045 @8643a2d2` - `cleared` starts from `[]` and skips the pin, otherwise `upsertPin(pins, pin, checkinsWindow.value)` (`:1040`), then `withCheckinSlots(data.value, pins, count)` (`:1043`); `withCheckinSlots` copies only keys not starting with `checkins.` before writing (`resources/js/utils/checkinSlots.ts:78 @8643a2d2`); `upsertPin` filters by `login` (`:68`). Same at @HEAD (`OverlayRenderer.vue:1329`) |
| C4 | CONFIRMED | `resources/js/utils/checkinSlots.ts:29-33 @8643a2d2` - `!Number.isFinite(n) \|\| n < 1` returns `DEFAULT_CHECKINS_WINDOW` (50, `:17`), else `Math.min(n, DEFAULT_CHECKINS_WINDOW)`. Same at @HEAD (`:31`) |
| C5 | CONFIRMED | `resources/js/utils/checkinSlots.test.ts @8643a2d2` - NaN fallback (`undefined`, `'nope'` -> default), "moves an existing pin ... latest wins", "trims to the cap", "drops every stale slot before writing", "round-trips a window through the flat data shape". `npx vitest run resources/js/utils/checkinSlots.test.ts resources/js/utils/tagCompletions.test.ts` at @HEAD: 2 files, 40 tests passed |
| C6 | CONFIRMED | Diff touches neither `resources/js/composables/useConditionalTemplates.ts` nor `resources/dsl/dsl.json`; `useConditionalTemplates.ts:210 @8643a2d2` reads `data[\`${path}.count\`]` for any path; `app/Models/OverlayTemplate.php:312 @8643a2d2` - `extractForeachTags()` appends `$iterable.'.count'` for every foreach match, called from `extractTemplateTags()` at `:216` |
| C7 | CONFIRMED | `resources/js/utils/tagCompletions.ts @8643a2d2` - `:176` `{ label: 'checkins', alias: 'pin' }`, `:160` nine `ITEM_FIELDS.checkins`, `:417` `!checkins` bang; `tagCompletions.test.ts:159`, `:164` assert the label and the `foreach:checkins as` template. @HEAD field `distance_km` is `distance` (OL-2609-009) |
| C8 | CONTRADICTED (one part) | Rules derived from the constant: CONFIRMED, `routes/settings.php:82-83 @8643a2d2` loops `array_keys(User::PREFERENCE_DEFAULTS['foreach_caps'])` with `'required\|integer\|min:1\|max:'.User::FOREACH_CAP_MAX`. Structural test exists: CONFIRMED, `tests/Feature/SettingsForeachCapsTest.php:34 @8643a2d2` builds its payload from the same keys. "Clamped": CONTRADICTED, the route REJECTS a value above the max with a validation error (`max:` rule; test "rejects values above FOREACH_CAP_MAX" at `:52`). Clamping happens in `User::foreachCaps()` (`app/Models/User.php:280 @8643a2d2`), not the route |

### Surface
Complete.

### Findings
- **F1** contradiction with the record - `CLAUDE.md:511 @8643a2d2` (still present @HEAD, Chat overlay section) says of `foreach_caps.chat`: "It is the ONLY cap enforced client-side". This change makes `checkins` a second client-enforced cap (the commit message itself says "the second client-enforced cap after chat"), and the commit does not update or cite that line. Update the `CLAUDE.md` sentence in a new change.
- **F2** claim partly false - C8 says the route validation clamps the new key to `FOREACH_CAP_MAX`. `routes/settings.php:83 @8643a2d2` rejects an over-max value with a validation error; the clamp is `User::foreachCaps()` at `app/Models/User.php:280 @8643a2d2`. Restate this in a new claim.
- **F3** [test] claim not run - C2's Pest file could not be executed in this audit (no `vendor/` in the checkout, and running Pest through the main checkout was refused). Its assertions match the claim when read. Run `php artisan test --filter=CheckinOverlayRenderTest` locally to close this out.

### Notes
- @HEAD `PIN_FIELDS` and `ITEM_FIELDS.checkins` name `distance`, not `distance_km`. OL-2609-009 renamed them and lists both files in its Surface. The Vitest run was against @HEAD, where the fixtures carry the renamed field.
- Unchanged lines verified @8643a2d2: `HTML_SAFE_FOREACH_FIELDS = { chat: ['html', 'badge_images'] }` (`OverlayRenderer.vue:406`, only a `chat` key); `TemplateDataMapperService`, `OverlayTemplate.php`, `app/Events/CheckinsUpdated.php` and `resources/dsl/` are not in the diff.
- `clampCheckinsWindow` caps at `DEFAULT_CHECKINS_WINDOW` (50), not `FOREACH_CAP_MAX`. Both are 50 @8643a2d2 and @HEAD (`User.php:165` / `:181`), so the two agree for now only because the numbers happen to match.
