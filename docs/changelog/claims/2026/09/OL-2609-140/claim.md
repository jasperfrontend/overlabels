## OL-2609-140 - feat(controls): a text control can declare its values, and gets a select for them

**Shipped:** 2026-09-27
**Commit:** `git log --grep=OL-2609-140`

### Surface
- `app/Support/OverlayMarkdown.php` - `behaviourPairs()` rewritten as a left-to-right scanner; new `jsonEnd()`
- `app/Support/ChatDesigner.php` - class docblock only: `CHOICES` described as the fallback for rows installed before the recipe declared them
- `resources/js/utils/controlChoices.ts` - new: `ControlChoice`, `controlChoices()`, `choiceHint()`
- `resources/js/utils/controlChoices.test.ts` - new file, 7 tests
- `resources/js/components/ControlPanel.vue` - imports the reader; a select branch for a text control with a vocabulary, in the same form-plus-save-button shape as the text row, placed before the plain text branch
- `resources/js/pages/products/design.vue` - imports the reader; new `choicesFor(key)`; the vocabulary branch reads it instead of `choices[key]`
- `resources/recipes/twitch-chat-overlay/chat.md` - a `choices=` line under `c:layout` and `c:background`
- `resources/recipes/chat-emote-bubbles/bubbles.md` - a `choices=` line under `c:look`, `c:direction` and `c:spawn`
- `database/migrations/2026_09_27_120000_declare_vocabularies_on_installed_product_text_controls.php` - new: backfill of the five keys onto installed product overlays
- `tests/Feature/ControlChoicesBackfillTest.php` - new file, 2 tests
- `tests/Feature/ProductChatDesignerTest.php` - one new test
- `tests/Feature/ProductChatEmoteBubblesTest.php` - two new tests
- `tests/Feature/OverlayImportTest.php` - the round-trip fixture gains a `look` text control carrying `config.choices`
- `CLAUDE.md` - a "Control vocabularies and the unified product designer" subsection

### Claims
- **C1** [code] `OverlayMarkdown::behaviourPairs()` reads `key=value` pairs left to right: a value starting with `'` runs to its closing quote, one starting with `[` or `{` runs to the offset `jsonEnd()` returns, anything else to the next comma; pairs are separated by the literal `, ` and reading stops at the first thing that is not one.
- **C2** [code] `jsonEnd()` counts `[`/`{` against `]`/`}` only outside double-quoted strings, honours backslash escapes inside them, and returns the line length for an unbalanced value.
- **C3** [test] `OverlayImportTest`'s `importControls()` fixture includes a `look` text control whose `config.choices` hints contain a comma, square brackets and double quotes; the existing round-trip test asserts `$imported->config` is identical to the source's for it.
- **C4** [unverified] C3 was run against the pre-change `behaviourPairs()` and failed at the config comparison with the imported `look` config holding no `choices`.
- **C5** [code] `chat.md` declares `config.choices` on `layout` and `background` as three `{value,label,hint}` entries each, identical to `ChatDesigner::CHOICES['layout']` and `['background']`.
- **C6** [test] `ProductChatDesignerTest` "declares the same vocabularies on the chat document itself" asserts C5 from the parsed document and asserts an install lands both lists on the rows' `config['choices']`.
- **C7** [code] `bubbles.md` declares `config.choices` on `look` (bubble, snow, heart), `direction` (up, down) and `spawn` (outside, edge, random, cannon), each entry with a non-empty label and hint.
- **C8** [test] `ProductChatEmoteBubblesTest` "declares a vocabulary on each text control" asserts the document's text controls are exactly those three, that every declared value appears in the CSS under `.look-`, `.dir-` or `.spawn-`, that every class with that prefix in the CSS is a declared value, and that each control's default is one of its values; "installs the vocabularies onto the rows" asserts an install copies all three lists and leaves `bubble_size` without one.
- **C9** [code] `controlChoices(config)` returns `[]` for a non-object config or a non-array `choices`; accepts `{value,label,hint}` objects and bare strings; drops entries without a non-empty string `value` and repeats of one already seen; fills a missing label with the value and a missing hint with `''`. `choiceHint()` returns the held value's hint or `''`.
- **C10** [test] `controlChoices.test.ts` asserts C9 in 7 tests.
- **C11** [code] `ControlPanel.vue` renders, for `ctrl.type === 'text'` with a non-empty `controlChoices(ctrl.config)`, a `<form>` holding a `<select>` and the same save button the text row has; a change sets `localValues[ctrl.id]` only, and submit calls the existing `saveTextValue()`, which posts through `postValue()` and toasts `"<label>" updated to <choice label>.` when the saved value is one of the choices (and the plain `"<label>" updated.` otherwise, as before); a held value that is not one of the choices is emitted as an extra `<option>` for itself; the held choice's hint is printed under the form. The branch sits before the plain text branch in the `v-else-if` chain.
- **C11a** [unverified] A first cut saved on `change` with no button and no toast; Jasper's two picks on his local install were stored (`look` to `bubble` at 23:36:52 UTC, `direction` to `up` at 23:36:55 UTC on 2026-09-26, read from `overlay_controls.updated_at`) but read to him as not saving, because nothing on the Values tab showed the write. The shipped shape is the one in C11.
- **C12** [code] `design.vue`'s `choicesFor(key)` returns `controlChoices(controls[key].config)` when non-empty and `props.choices[key] ?? []` otherwise; the vocabulary branch, its options and its hint all read `choicesFor(key)`.
- **C13** [code] The migration, for each of `twitch-chat-overlay` (overlay ref `chat`; keys `layout`, `background`) and `chat-emote-bubbles` (ref `bubbles`; keys `look`, `direction`, `spawn`), resolves every `recipes.id` for the slug, walks `recipe_instances.primitive_map->overlays-><ref>` in chunks, and on `overlay_controls` rows of that template with `type = 'text'` and one of the keys merges a frozen `choices` list into `config` unless `config.choices` is already non-empty. `down()` is a no-op.
- **C14** [test] `ControlChoicesBackfillTest` installs both products, strips `choices` from the five rows, runs `up()`, and asserts each row's `choices` equals the list its recipe document declares and a changed value survives; a second test asserts a hand-made `look` control on another overlay stays `config = null`, a row already holding a custom vocabulary keeps it, and `bubble_size`'s config is unchanged.
- **C15** [unverified] On `overlabels.test` after `php artisan migrate`: the Values tab of the local Chat Emote Bubbles install (template 359) showed selects with hints for Look, Direction and Spawn; picking Heart wrote `heart` to the row (read back with tinker) and the hint changed to "A heart-shaped bubble."; the value was then put back to `snow`. The chat designer's Layout select rendered with its hint.
- **C16** [unverified] The local Bubbles install's `look` description reads "a soap bubble with a shine", older than the recipe's current text, so a description-matched backfill in the 2026-09-20 shape would have missed that row; the install-record match found it.

### Unchanged
- `OverlayShareService::behaviourConfig()` and the detail-line writer: the emitter already wrote a non-scalar config value with `json_encode()`, so the `choices` lines in the two recipes are byte-identical to what a re-export of a fresh install produces. The emitter is not in the diff.
- `OverlayControl::sanitizeValue()` and `OverlayControlController::setValue()`: a text value outside the vocabulary is still accepted. The select is a courtesy on the row, not a gate, and enforcement was not in scope.
- `ProductController::design()` still hands the page `ChatDesigner::CHOICES` as `choices`; the page prefers the row and falls back to it, so the prop is untouched.
- `ChatDesigner::CHOICES` values, `ChatPresets`, `SavedChatPresetController`, `UserChatPreset` and the routes: none in the diff. The designer's existing tests hold `CHOICES` against the CSS as before, and the new test holds it against the document.
- `ControlsManager.vue` (the definitions tab, which does not edit values) and `ControlFormModal.vue`: no way to author `choices` from the UI was added; a vocabulary reaches a row through a document today.

### Risk
The migration runs on deploy and writes `config.choices` onto the five keys of every prod install of
the two products. A copy a streamer made from a product overlay before this is not touched: the chat
designer still works on such a copy through the fallback map, and a Bubbles copy keeps the text box.
The server still accepts any string for these controls.
