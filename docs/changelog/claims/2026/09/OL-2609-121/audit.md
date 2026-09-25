## Audit of OL-2609-121 - feat(products): save your own chat looks by name, and apply, update, rename or delete them from the designer

**Audited:** 2026-09-25
**Commit:** d2cc9f0e03a281e8b38488e63ac26468cc38be4a
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/SavedChatPresetController.php:51 @d2cc9f0` - `'values' => ChatPresets::currentValues($template)`; the only request reads are `input('name')`/`merge(['name'...])` at `:121` and `validate(['name' => ...])` at `:123` (plus `$request->user()` at `:150`, not a field); unchanged @HEAD |
| C2 | CONFIRMED | `app/Support/ChatPresets.php:239-254 @d2cc9f0` - `whereIn('key', self::KEYS)->pluck('value','key')`, then `foreach (self::KEYS ...)` with `array_key_exists` guard; unchanged @HEAD |
| C3 | CONFIRMED | `app/Support/ChatPresets.php:203 @d2cc9f0` - `return self::applyValues($template, self::PRESETS[$key]['values']);`; `:221-222` `if (! $control) { continue; }`, no create; unchanged @HEAD |
| C4 | CONFIRMED | `SavedChatPresetController.php:39-44 @d2cc9f0` - `abort_if($count >= MAX_PER_USER, 422, 'You have '.MAX_PER_USER.' saved looks already. Delete one to save another.')` precedes `validateName()` at `:46`; also asserted by `UserChatPresetTest` "refuses a blank name..." (passed) |
| C5 | CONFIRMED | `SavedChatPresetController.php:121-133 @d2cc9f0` - `trim()` merge, `required`, `string`, `'max:'.NAME_MAX`, `Rule::unique('user_chat_presets','name')->where('user_id', $user->id)->ignore($ignore?->id)` |
| C6 | CONFIRMED | `tests/Feature/UserChatPresetTest.php:70-87 @d2cc9f0` (identical @HEAD) - saves `🔥\|name:thing` and a ZWJ family + `<b>&</b>` + quotes name, reads both back, 40x`🔥` created, 41x refused 422; `php artisan test --filter=UserChatPresetTest` 11 passed |
| C7 | CONFIRMED | `SavedChatPresetController.php:146-161 @d2cc9f0` - `resolve()` aborts on `! ChatPresets::has($slug)`, no `RecipeInstance`, or `overlayFor()` null / `owner_id !== $user->id`; called first in all five methods; `abort_if($preset->user_id !== $user->id, 404)` at `:65, :87, :97, :109` |
| C8 | CONFIRMED | `UserChatPresetTest.php:183-200 @d2cc9f0` - apply, overwrite, rename, destroy each `assertNotFound()`; then row exists with name `Theirs` and `values.skin` `terminal`; passed |
| C9 | CONFIRMED | `SavedChatPresetController.php:67-80 @d2cc9f0` - dispatch args `(slug, broadcastKey(), type, (string) value, twitch_id, null, null, null)` identical to `ProductController.php:408-417 @d2cc9f0`; returns `['values' => $preset->values]` |
| C10 | CONFIRMED | `UserChatPresetTest.php:111-133 @d2cc9f0` - every bundle value compared to stored rows, `assertDispatchedTimes(..., count(ChatPresets::KEYS))` (13 keys, `ChatPresets.php:34-37`), one dispatch with `font_size` `'31'`; passed |
| C11 | CONFIRMED | `SavedChatPresetController.php:89 @d2cc9f0` - `update(['values' => currentValues()])`; `:101` - `update(['name' => ...])` only |
| C12 | CONFIRMED | `routes/web.php:740-742 @d2cc9f0` - `products.preset` with `'preset' => '[a-z][a-z0-9_]*'`; `:746` prefix `/products/{slug}/saved-presets`; unchanged @HEAD. See Notes on the rationale |
| C13 | CONFIRMED | `resources/js/pages/products/design.vue:185, :247 @d2cc9f0` - both `activePreset` and `activeSaved` use `holds()` (`:175`); no active id is persisted |
| C14 | CONTRADICTED (compound) | Half 1 CONFIRMED: `design.vue:248 @d2cc9f0` `selectedSavedId = ref(activeSaved.value)`, separate from `activeSaved`. Half 2 CONTRADICTED: `design.vue:651 @d2cc9f0` `:disabled="!selectedDrifted \|\| savedBusy"` - the button is also disabled while `savedBusy` is true, so it is not enabled "exactly when `selectedDrifted` is true" |
| C15 | CONFIRMED | `design.vue:219, :311 @d2cc9f0` both call `adoptValues()`; `:194-212` sets knobs, `expectInFrame()` at `:203`, one `remember('controls', ...)` at `:204`; save/overwrite/rename reach `rememberSaved()` via `replaceSaved()` (`:278`), delete calls it at `:366` |
| C16 | CONFIRMED | `design.vue:638, :667 @d2cc9f0` - neither `<input>` in the section has `maxlength` |
| C17 | CONFIRMED | `app/Support/ChatDesigner.php:156-163 @d2cc9f0` - `orderBy('name')`; `ProductController::design()` uses it for `saved_presets`; grep of `app/` finds no other query feeding the page |
| C18 | CONFIRMED | `UserChatPresetTest.php:217-225 @d2cc9f0` asserts the row is gone after `forceDelete()`; migration `:30` `cascadeOnDelete()`; `app/Models/User.php @d2cc9f0` has no deleting hooks; passed |
| C19 | UNVERIFIABLE | tagged [unverified] |
| C20 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** compound claim, halves differ - C14 says the "Update with current look" button is enabled exactly when `selectedDrifted` is true, but `resources/js/pages/products/design.vue:651 @d2cc9f0` also disables it while `savedBusy`; the claim should be restated (RECORD) as "enabled when `selectedDrifted` and not `savedBusy`".
- **F2** scope - the commit edits three earlier prose entries in `docs/changelog/changelog-2026-09.md` @d2cc9f0 (OL-2609-109 re-indents a paragraph under a `- 500x800` bullet, OL-2609-060 turns `*depends on*` into `_depends on_`, OL-2609-038 un-indents a list continuation line). This is a formatter reflow that no claim, Surface line or the new entry mentions. The path is exempt from Surface, but the content change is not. Disclose it in a follow-up claim, or revert the three hunks if they were not meant to ship.

### Notes
- Tests: `php artisan test --filter=UserChatPresetTest` ran with 11 passed (84 assertions). The test file is identical at HEAD, and no later commit changes any symbol this claim names.
- C12 rationale: none of the five shipped paths could have been matched by `products.preset` under a `presets` segment. Store has no third segment, apply and overwrite add a fourth, and PATCH/DELETE use numeric ids, which fail `[a-z]`. The same applies to the comment at `routes/web.php:743-745 @d2cc9f0`.
- `selectedDrifted` (`design.vue:250 @d2cc9f0`) compares against `activeSaved`, which is the first saved look that `holds()`. If two saved looks hold identical values, the later one shows "with changes" while it is exactly on screen.
- `resolve()` does not run the slug through `canonical()` the way `applyPreset()` does (OL-2609-114 C6). The page posts `product.slug` from the manifest, so this had no effect I could observe.
