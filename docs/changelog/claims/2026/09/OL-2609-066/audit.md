## Audit of OL-2609-066 - feat(controls): a color control, with a picker, that never rejects a value

**Audited:** 2026-09-25
**Commit:** 5a1a416502faccbf0278ae765d15429f6845f977
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Models/OverlayControl.php:79 @5a1a416` - `TYPES` contains `color`; the three `in:` rules are `OverlayControlController.php:65` and `:503 @5a1a416` and `OverlayTemplateController.php:1278 @5a1a416`, all `implode(',', OverlayControl::TYPES)`; `git grep` finds no other hardcoded type allowlist under `app/` or `routes/`; no migration in `git show --stat`. @HEAD the same rules sit at `:66`, `:523`, `:1291` |
| C2 | CONFIRMED | `app/Models/OverlayControl.php:182 @5a1a416` - `'text', 'color', 'expression', 'datetime' => strip_tags((string) $raw)`; same line @HEAD |
| C3 | CONFIRMED | `@5a1a416`: store goes through `createForTemplate()` (`OverlayControl.php:319`, value via `sanitizeValue()`), update through `OverlayControlController.php:271-272`, setValue through `:397`/`:400`; the clamp at `:403` is gated `in_array($control->type, ['number', 'counter'])`. @HEAD `setValue()` also canonicalises values for controls whose config carries `webfont` (OL-2609-119) |
| C4 | CONTRADICTED (half) | `ColorPicker.vue @5a1a416`: no Reka color component receives `modelValue` (`ColorAreaRoot`, both `ColorSliderRoot`s and `ColorSwatchPickerRoot` bind `working`, lines 190, 205, 213, 221), and `readable()` (lines 99-103) gates on `isValidColor(trimmed)` - CONFIRMED. "Every read goes through its local `readable()`" is false: line 183 reads `modelValue` raw into the trigger span's `background`, the same read C8 claims |
| C5 | CONFIRMED | `ColorPicker.vue:52-62 @5a1a416` - two emits; lines 195-196, 205, 213 bind `@update:model-value="preview"` and `@change-end="commit"`; `preview()` at 146-148 emits only `update:modelValue`. `changeEnd` is a declared emit of `ColorAreaRoot` and `ColorSliderRoot` in installed reka-ui 2.10.4 |
| C6 | CONFIRMED | `ControlPanel.vue:534-535 @5a1a416` - `@update:model-value="localValues[ctrl.id] = $event"` (no request), `@commit="saveColorValue(ctrl, $event)"`; `saveColorValue()` at 355-358 is the only `postValue()` route from the picker |
| C7 | CONFIRMED | `ColorPicker.vue:266-268 @5a1a416` - `.ol-color-preset { background: var(--reka-color-swatch-color); }`; `node_modules/reka-ui/dist/ColorSwatch/ColorSwatch.js` (2.10.4) sets only `--reka-color-swatch-color` and `--reka-color-swatch-alpha` in `style` |
| C8 | CONFIRMED | `ColorPicker.vue:183 @5a1a416` - `:style="{ background: modelValue \|\| 'transparent' }"`; `ControlsManager.vue:339 @5a1a416` - `:style="{ background: ctrl.value }"`; same @HEAD |
| C9 | CONTRADICTED (half) | `ColorPicker.vue:162-165 @5a1a416` - `if (typeof next !== 'string' \|\| next === '') return;` - CONFIRMED. The parenthetical "Listbox deselect (which emits null)" is false for installed reka-ui 2.10.4: `dist/Listbox/ListboxRoot.js:119` sets `modelValue.value = void 0` (undefined) on toggle-off; the guard rejects it either way |
| C10 | CONFIRMED | `ControlFormModal.vue @5a1a416` - `buildPayload()` has no hunk in the diff; its final `else { payload.config = null; }` catches every type but `expression`, `list_writer`, `number`, `counter`, `timer` |
| C11 | CONFIRMED | `ControlPanel.vue:53-72 @5a1a416` - local `typeLabels`/`typeOrder` include `color`; groups are pushed only by iterating `typeOrder`; `resources/js/utils/controls.ts:12,18 @5a1a416` include `color` |
| C12 | CONFIRMED | `tests/Feature/ColorControlTest.php:22-29 @5a1a416` - the six-value dataset; used by both the create test (line 56-64) and the `setValue` test (66-82), each `toBe($color)`. `php artisan test --filter=ColorControlTest`: 16 passed (30 assertions). File unchanged at HEAD |
| C13 | UNVERIFIABLE | tagged [unverified] |
| C14 | UNVERIFIABLE | tagged [unverified] |
| C15 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** compound claim, half false - C4's "Every read goes through its local `readable()`" is contradicted by `resources/js/components/ui/color-picker/ColorPicker.vue:183 @5a1a416`, which paints the raw `modelValue` on the trigger; a follow-up claim should narrow the sentence to reads that feed Reka components.
- **F2** compound claim, half false - C9's "Listbox deselect (which emits null)" is wrong for reka-ui 2.10.4 (`node_modules/reka-ui/dist/Listbox/ListboxRoot.js:119` sets `undefined`); the code guard at `ColorPicker.vue:163 @5a1a416` is correct, so only the record (and the matching docblock at lines 157-161) needs restating.
- **F3** Unchanged names a nonexistent symbol - `App\Support\OverlayMarkdown::parseControls()` does not exist @5a1a416 or @HEAD; the parser is the private `controls()` at `app/Support/OverlayMarkdown.php:168 @5a1a416`, and it takes the type from the controls-table row regex (line 172, capture 2), not from a `- \`c:key\` (type)` line, which at line 197 carries only the description. Restate the line in a new claim.
- **F4** Unchanged names a nonexistent symbol - `App\Services\Recipes\RecipeInstaller::installTemplate()` does not exist @5a1a416 or @HEAD; the `list_writer`/`expression` special-casing and the `createForTemplate()` call are in `installOverlay()` (`app/Services/Recipes/RecipeInstaller.php:286`, lines 321-341 @5a1a416). Restate the line in a new claim.

### Notes
- C3 @HEAD: `OverlayControlController::setValue()` gained a `webfont` config canonicalisation (commit c6e75409, OL-2609-119); it applies only to controls with `config.webfont`, and a color control is created with `config: null` (C10).
- Unchanged line 6 and Risk (help describes eight types) were true @5a1a416; `resources/help/pages/controls.md` documents `color` since f7e137cd (OL-2609-067).
- C14 describes the installed third-party package, not the repo; left UNVERIFIABLE per its tag.
