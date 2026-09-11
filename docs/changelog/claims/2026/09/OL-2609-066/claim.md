## OL-2609-066 - feat(controls): a color control, with a picker, that never rejects a value

**Shipped:** 2026-09-11
**Commit:** `git log --grep=OL-2609-066`

### Surface
- `app/Models/OverlayControl.php` - `color` added to `TYPES`; `color` added to the `text` arm of `sanitizeValue()`; docblock on that method records why it is not validated
- `resources/js/components/ui/color-picker/ColorPicker.vue` - new file, the picker: swatch trigger, popover, area, hue and alpha sliders, preset grid, format toggle
- `resources/js/components/ui/color-picker/index.ts` - new file, re-exports `ColorPicker`
- `resources/js/components/ControlPanel.vue` - `color` added to the local `typeLabels` and `typeOrder`; a `ctrl.type === 'color'` branch; `saveColorValue()`; imports `ColorPicker`
- `resources/js/components/ControlFormModal.vue` - `color` added to the starting-value section's type list; a `form.type === 'color'` field with the picker; imports `ColorPicker`; "eight control types" comment now says nine
- `resources/js/components/ControlsManager.vue` - a swatch rendered in the row summary for `color` controls
- `resources/js/components/controls/controlTypeCatalog.ts` - a `color` entry in `CONTROL_TYPES`; `{ kind: 'color' }` added to `ControlTypeDemo`; `Palette` imported; two "eight" comments now say nine
- `resources/js/components/controls/ControlTypeCard.vue` - a `demo.kind === 'color'` branch
- `resources/js/components/controls/ControlTypePicker.vue` - "Eight kinds of control" copy now says Nine; one comment likewise
- `resources/js/types/index.d.ts` - `'color'` added to the `OverlayControl['type']` union
- `resources/js/utils/controls.ts` - `color: 'Color'` in `CONTROL_TYPE_LABELS`; `'color'` in `CONTROL_TYPE_ORDER`
- `tests/Feature/ColorControlTest.php` - new file

### Claims
- **C1** [code] `OverlayControl::TYPES` contains `color`, and no migration accompanies it: the three `in:` validation rules that gate a control type (`OverlayControlController::store()`, `OverlayControlController` line ~503, `OverlayTemplateController` line ~1278) all derive their allowlist from that constant with `implode(',', OverlayControl::TYPES)`.
- **C2** [code] `OverlayControl::sanitizeValue()` routes `color` through the same match arm as `text`, `expression` and `datetime`, whose body is `strip_tags((string) $raw)`.
- **C3** [code] No code path validates a color control's value against any color grammar. `sanitizeValue()` is the only transformation applied on the store, update and `setValue()` paths, and the min/max clamp in `setValue()` is gated on `in_array($control->type, ['number', 'counter'])`.
- **C4** [code] `ColorPicker.vue` never passes `props.modelValue` to a Reka color component. Every read goes through its local `readable()`, which returns null unless `isValidColor()` accepts the trimmed string, and the component's `working` ref holds the last readable value otherwise.
- **C5** [code] `ColorPicker.vue` declares two emits, `update:modelValue` and `commit`. `ColorAreaRoot` and both `ColorSliderRoot` instances bind `@update:model-value="preview"` and `@change-end="commit"`; `preview()` emits only `update:modelValue`.
- **C6** [code] `ControlPanel.vue` calls `saveColorValue()` from the picker's `@commit` only. Its `@update:model-value` handler writes `localValues[ctrl.id]` and issues no request, so dragging a slider produces no `postValue()` call until the pointer is released.
- **C7** [code] The `.ol-color-preset` rule in `ColorPicker.vue` sets `background: var(--reka-color-swatch-color)`. Reka's `ColorSwatch` paints no background of its own; it sets only `--reka-color-swatch-color` and `--reka-color-swatch-alpha` as inline custom properties.
- **C8** [code] The trigger swatch and the `ControlsManager.vue` row swatch are both painted with the raw stored string via `:style="{ background: ... }"`, not with a parsed value.
- **C9** [code] `ColorPicker.commitSwatch()` returns early unless the incoming `AcceptableValue` is a non-empty string, so the preset grid's Listbox deselect (which emits null) does not clear the current pick.
- **C10** [code] `ControlFormModal.buildPayload()` is unchanged and sends `config: null` for a `color` control, because `color` is not one of `expression`, `list_writer`, `number`, `counter` or `timer`. No config marker is stored for a color control.
- **C11** [code] `ControlPanel.vue` carries its own `typeLabels` and `typeOrder` locals, separate from `CONTROL_TYPE_LABELS` and `CONTROL_TYPE_ORDER` in `resources/js/utils/controls.ts`; both copies gained `color`, and a type absent from `typeOrder` renders in no group at all.
- **C12** [test] `ColorControlTest` has 16 tests. Its `css colors the picker cannot parse` dataset holds six values - `rebeccapurple`, `rgb(170 187 204 / 50%)`, `oklch(0.7 0.15 30)`, `color-mix(in oklab, red 40%, blue)`, `var(--brand)`, `transparent` - and each is asserted to survive both the create endpoint and the `setValue` endpoint byte for byte.
- **C13** [unverified] Eight of those 16 tests were run against a tree with `color` removed from `OverlayControl::TYPES` and failed; the `sanitises a color value exactly like text` test was run against a tree with `color` removed from the `sanitizeValue()` text arm and failed. Both trees were then restored.
- **C14** [unverified] Reka UI 2.10.4's `parseColor()` throws for any string that does not begin with `#`, `rgb`, `hsl`, `hsb` or `hsv`, and its `RGB_RE` and `HSL_RE` both require comma separators, so space-and-slash CSS color syntax is rejected.
- **C15** [unverified] The picker was exercised in Chrome against `overlabels.test`: the preset grid rendered twelve painted swatches, and entering `oklch(0.7 0.19 145)` left the input text unchanged, painted the trigger swatch green, and displayed the "The picker cannot read this value" note while the picker held its previous position.

### Unchanged
- No migration is in the diff. `overlay_controls.type` is a varchar with no database-level check constraint, and the allowlist lives entirely in `OverlayControl::TYPES` (C1).
- `App\Services\OverlayShareService::behaviourConfig()` and `App\Support\OverlayMarkdown::parseControls()` are how a control survives `.md` export and import. Neither is in the diff, and neither carries a list of control types: the markdown reader takes the type verbatim out of the `- \`c:key\` (type)` line.
- `App\Services\Recipes\RecipeInstaller::installTemplate()` special-cases only `list_writer` and `expression` when copying controls, and is not in the diff, so a `color` control in a recipe installs through the generic `createForTemplate()` path.
- No dependency was added. `reka-ui` is already a direct dependency, pinned `^2.9.2` with 2.10.4 installed, and `package.json` and `package-lock.json` are not in the diff.
- `OverlayControl::resolveDisplayValue()` is not in the diff. It special-cases `timer` and `isRandom()`, so a `color` control falls to the `return $this->value ?? ''` line that `text` already used.
- `resources/help/` is not in the diff: `/help/controls` still describes eight control types and does not mention this one.

### Risk
The help documentation is now behind the product by one control type, as noted above.
