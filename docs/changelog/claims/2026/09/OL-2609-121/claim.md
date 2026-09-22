## OL-2609-121 - feat(products): save your own chat looks by name, and apply, update, rename or delete them from the designer

**Shipped:** 2026-09-22
**Commit:** `git log --grep=OL-2609-121`

The ten built-in looks are a constant and nothing about a streamer's own tuning was stored, so one
click on a built-in preset lost it. A look can now be saved under a name the streamer chooses, and
the designer lists them above the built-ins. Builds on OL-2609-090 (the built-in presets) and
OL-2609-109 (the designer).

### Surface
- `database/migrations/2026_09_22_120000_create_user_chat_presets_table.php` - new file: `user_chat_presets` (`user_id` FK cascade, `name` varchar(40), `values` json, timestamps, unique on `user_id` + `name`)
- `app/Models/UserChatPreset.php` - new file: `MAX_PER_USER` 20, `NAME_MAX` 40, `values` array cast, `toDesigner()`
- `app/Http/Controllers/SavedChatPresetController.php` - new file: `store`, `apply`, `overwrite`, `rename`, `destroy`, all JSON, all install-gated through `resolve()`
- `routes/web.php` - `products.saved-presets.{store,apply,overwrite,rename,destroy}` under `/products/{slug}/saved-presets`, in the auth group beside `products.preset`; controller import
- `app/Support/ChatPresets.php` - `apply()` now delegates to new `applyValues()`; new `currentValues()`
- `app/Support/ChatDesigner.php` - `savedPresets()`; model import
- `app/Http/Controllers/ProductController.php` - `design()` ships `saved_presets` and `saved_presets_max`; model import
- `resources/js/pages/products/design.vue` - "Your looks" section above "Start from"; `SavedPreset` type, two props, `holds()`, `adoptValues()` extracted from `applyPreset()`, the saved-look state and the five request functions
- `tests/Feature/UserChatPresetTest.php` - new file, 11 tests

### Claims
- **C1** [code] `SavedChatPresetController::store()` writes `values` from `ChatPresets::currentValues($template)`, never from the request. The only request field read anywhere in the controller is `name`.
- **C2** [code] `ChatPresets::currentValues()` returns the overlay's control values for `ChatPresets::KEYS` only, in `KEYS` order, omitting any key the overlay has no control for.
- **C3** [code] `ChatPresets::apply()` is `return self::applyValues($template, self::PRESETS[$key]['values'])`, and `applyValues()` keeps `apply()`'s skip rule: a key with no control on the overlay is passed over, never created.
- **C4** [code] `store()` answers 422 with the message `You have 20 saved looks already. Delete one to save another.` when the caller already has `MAX_PER_USER` rows, before validating the name.
- **C5** [code] `validateName()` trims the name, then requires it, caps it at `NAME_MAX` characters with Laravel's `max` rule (multibyte-aware), and applies `Rule::unique('user_chat_presets', 'name')` scoped to the caller's `user_id`, ignoring the preset being renamed.
- **C6** [test] `UserChatPresetTest` "keeps any name verbatim, emoji and punctuation included" saves `🔥|name:thing` and a name with a ZWJ family emoji, HTML and quotes, reads both back unchanged, accepts forty fire emoji and refuses forty-one.
- **C7** [code] Every door calls `resolve()`, which 404s unless `ChatPresets::has($slug)`, the caller has a `RecipeInstance` of it, and `ChatPresets::overlayFor()` yields a template owned by the caller; the four preset doors additionally 404 when `$preset->user_id !== $user->id`.
- **C8** [test] "answers 404 for another account's look on every door, and never touches it" covers apply, overwrite, rename and destroy and asserts the row is unchanged afterwards.
- **C9** [code] `apply()` dispatches `ControlValueUpdated` once per control `applyValues()` returned, with the same positional arguments `ProductController::applyPreset()` passes, and returns `{values}`.
- **C10** [test] "applies a saved look" asserts every captured value is on the rows, `ControlValueUpdated` is dispatched thirteen times, and one dispatch carries `font_size` `31`.
- **C11** [code] `overwrite()` replaces `values` with `currentValues()` and leaves `name` alone; `rename()` replaces `name` and leaves `values` alone.
- **C12** [code] The routes use the segment `saved-presets`, not `presets`, because `products.preset` constrains `{preset}` to `[a-z][a-z0-9_]*` and would otherwise match `/products/{slug}/presets/mine`.
- **C13** [code] `design.vue` derives `activeSaved` by the same `holds()` comparison `activePreset` uses; nothing stores which saved look is active.
- **C14** [code] `selectedSavedId` is separate state from `activeSaved`, initialised from it, so a look that has drifted stays selected and its "Update with current look" button is enabled exactly when `selectedDrifted` is true.
- **C15** [code] `applySaved()` and `applyPreset()` both call `adoptValues()`, which sets the knobs, calls `expectInFrame()` for the keys the overlay has (OL-2609-120), and rewrites the history entry once (`remember('controls', ...)`). `saveCurrentLook()`, `overwriteSaved()`, `renameSaved()` and `deleteSaved()` rewrite `saved_presets` in the entry through `rememberSaved()`.
- **C16** [code] Neither text input in the section carries a `maxlength` attribute; the length limit is the server's.
- **C17** [code] `design()` orders `saved_presets` by `name`, and `ChatDesigner::savedPresets()` is the only reader.
- **C18** [test] "goes with the account when the account is deleted" asserts the row is gone after `forceDelete()` on the user, through the FK cascade and nothing else.
- **C19** [unverified] On overlabels.test the whole flow was walked through the page's own inputs and buttons: save as `🔥|name:thing`, nudge a knob to see "with changes" and Update enable, update, nudge again and re-pick the look from the dropdown (knob returned to the saved value, veil shown), rename to `🌙 late night`, then the two-step Delete. `history.state.page.props.saved_presets` held the current list after each step and was empty at the end.
- **C20** [unverified] Same walk-through: with the apply request in flight the section's buttons were disabled, and a Delete click inside that window did nothing; the delete went through once they re-enabled.

### Unchanged
- `ProductController::applyPreset()` and `ChatPresets::forProduct()` are not in the diff; the built-in presets keep their own door and the product page keeps deriving `active` the way it did.
- `OverlayControlController::setValue()` is not in the diff. A saved bundle holds values that endpoint already accepted onto the rows, which is why `applyValues()` writes them through `writeValue()` the way the built-ins are written.
- `UserDeletionService` is not in the diff; the migration's `cascadeOnDelete` on `user_id` is what removes a deleted account's rows (C18).
- The veil and the history rewrite from OL-2609-120 and the commit before it are reused, not changed: `expectInFrame()`, `remember()` and `reportApplied()` are called, not edited.

### Risk
A migration creates one table. Names are per-user unique and case-sensitive: `Cozy` and `cozy` are two
looks. A saved bundle is not re-validated on apply, so a font removed from the Bunny catalogue after
it was saved would be written as-is and rendered as the fallback, the same outcome a built-in preset
naming a vanished family would have.
