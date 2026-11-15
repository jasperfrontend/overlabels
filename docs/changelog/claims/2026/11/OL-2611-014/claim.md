## OL-2611-014 - feat(products): two-color gradient bubbles, a real color picker and calmer saves in the designer

**Shipped:** 2026-11-15
**Commit:** `git log --grep=OL-2611-014`

### Surface
- `resources/recipes/chat-emote-bubbles/bubbles.md` - controls `bubble_color_2` and `gradient_angle`; every look paints `--fill`, a linear gradient turned by the angle; control count copy 10 -> 12
- `resources/recipes/chat-emote-bubbles/manifest.json` - both keys in the Look designer group and in all three presets; description and highlight mention the gradient
- `database/migrations/2026_11_15_130000_add_gradient_to_installed_chat_emote_bubbles.php` - new file, backfills existing installs
- `resources/js/pages/products/design.vue` - knob writes coalesced per key, slider/picker drag writes only on release, checkerboard stage replaced with `bg-background`, checkerboard sentence removed
- `resources/js/components/controls/ControlKnob.vue` - color knob uses `ColorPicker` instead of `<input type="color">`; doc comment on `input`
- `resources/help/pages/chat-emote-bubbles.md` - Second color and Gradient angle documented; ten -> twelve controls
- `resources/help/pages/index.md` - ten -> twelve controls
- `tests/Feature/ProductChatEmoteBubblesTest.php` - counts and key order updated to twelve; new gradient test
- `tests/Feature/BubblesGradientBackfillTest.php` - new file

### Claims
- **C1** [code] `bubbles.md` declares `bubble_color_2` (color, default `#ffffff`) and `gradient_angle` (number, min 0, max 360, step 1, default 0), immediately after `bubble_color` in the controls table.
- **C2** [code] `bubbles.md` CSS sets `--flow: calc(180deg + [[[c:gradient_angle]]]deg)` and `--fill: linear-gradient(var(--flow), var(--tint), var(--tint2))` on `.ol-bubbles`.
- **C3** [test] `ProductChatEmoteBubblesTest` "paints every look with the two-color gradient" asserts both declarations in C2 and that each of the bubble, snow and heart look rules contains `background: var(--fill)`.
- **C4** [code] Every preset in `manifest.json` sets `bubble_color_2` equal to its `bubble_color` and `gradient_angle` to `"0"`.
- **C5** [code] In `design.vue`, `ControlKnob`'s `input` event calls `moveKnob()`, which assigns the local value and sends no request.
- **C6** [code] `writeControl()` in `design.vue` keeps at most one request per key in flight; a value asked for while one is in flight is held in `waiting[key]` and sent when the first settles, replacing any earlier waiting value.
- **C7** [code] `writeControl()` calls `expectInFrame()` only when no newer value is waiting for that key.
- **C8** [unverified] On overlabels.test, a scripted drag with six pauses of 400 ms sent six POSTs before this change and one after; ten arrow-key change events sent two POSTs and saved the last value.
- **C9** [code] `ControlKnob.vue` renders `ColorPicker` for a `color` control, emitting `input` on `update:modelValue` and `commit` on `commit`, with the text field kept beside it.
- **C10** [code] `design.vue` no longer has a scoped style block; the stage div carries `bg-background`.
- **C11** [code] The migration patches an install's CSS only when every entry of `PATCHES` occurs exactly once; otherwise it leaves CSS, controls and `template_tags` untouched and logs the overlay id.
- **C12** [code] The migration inserts `bubble_color_2` with the install's current `bubble_color` value and `gradient_angle` with `"0"`, at `bubble_color`'s sort_order + 1 and + 2, shifting later controls by 2.
- **C13** [code] The migration appends `c:bubble_color_2` and `c:gradient_angle` to the overlay's `template_tags`.
- **C14** [code] The migration does not write `updated_at` on pre-existing control rows.
- **C15** [test] `BubblesGradientBackfillTest` asserts a pre-gradient install ends with the same CSS and control rows (key, label, type, description, config, sort_order, source, source_managed) as a fresh install.
- **C16** [test] `BubblesGradientBackfillTest` asserts the first preset is not active on a pre-gradient install before the migration and is active after it.
- **C17** [test] `BubblesGradientBackfillTest` asserts an install with a rewritten heart rule keeps its CSS, its ten controls and its `template_tags`.
- **C18** [test] `BubblesGradientBackfillTest` asserts a second run changes nothing.
- **C19** [unverified] The frozen patches applied to the recipe CSS of commits `e1e73d34` and `959d24b7` each produce the current recipe CSS byte for byte.

### Unchanged
- `ColorPicker.vue` is mounted, not modified; its `update:modelValue` / `commit` split is what `ControlKnob` maps onto `input` / `commit`.
- `OverlayControlController::setValue()` and the queued `ControlValueUpdated` broadcast the designer writes through are not in the diff; one write still reaches the preview through the queue.
- `ProductDesigner::matches()` and the page's `holds()` still require every preset key to be present; existing installs are fixed by the migration, not by loosening the match.

### Risk
The migration rewrites the CSS of existing installs on deploy. An install whose patched rules were
edited by its streamer is skipped and named in the log line "Chat Emote Bubbles gradient backfill
skipped an install"; on such an install no preset shows as active until it is reinstalled or edited.
