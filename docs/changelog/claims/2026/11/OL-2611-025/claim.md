## OL-2611-025 - feat(controls): the gradient angle is a circle you drag

**Shipped:** 2026-11-19
**Commit:** `git log --grep=OL-2611-025`

### Surface
- `resources/js/utils/angleKnob.ts` - new: `angleColorKeys()`, `normaliseAngle()`, `angleFromPointer()`, `gradientCss()`
- `resources/js/utils/angleKnob.test.ts` - new: Vitest cases for all four, and one that reads `bubbles.md` for the overlay's gradient formula
- `resources/js/components/controls/AnglePicker.vue` - new: the circle painted with two colors, a line to drag, keyboard steps
- `resources/js/components/controls/ControlKnob.vue` - new `angleColors` prop and an `angle` kind that mounts `AnglePicker` beside the exact number box
- `resources/js/pages/products/design.vue` - `angleColorsFor()`; passes `angle-colors` to every knob
- `resources/js/components/ControlPanel.vue` - `angleColorsFor()`; passes `angle-colors` to every knob
- `app/Support/AngleKnob.php` - new: `problems()` for one overlay's `angle_colors` declarations
- `app/Services/Recipes/RecipeManifestValidator.php` - `controlConditionErrors()` also reports `AngleKnob::problems()`
- `resources/recipes/chat-emote-bubbles/bubbles.md` - `gradient_angle` declares `angle_colors=["bubble_color","bubble_color_2"]`
- `database/migrations/2026_11_19_150000_declare_angle_colors_on_installed_chat_emote_bubbles.php` - new: adds that declaration to existing installs
- `tests/Unit/AngleKnobTest.php` - new: 5 tests on `AngleKnob::problems()`
- `tests/Feature/AngleColorsBackfillTest.php` - new: 2 tests on the backfill
- `tests/Feature/BubblesGradientBackfillTest.php` - the "exactly what a fresh install has" test also runs the angle backfill
- `tests/Feature/EnabledIfBackfillTest.php` - `enabledIfConfigs()` sorts each config's keys before comparing
- `CLAUDE.md` - an `angle_colors` bullet under "Control vocabularies and the unified product designer"

### Claims
- **C1** [code] `angleColorKeys()` returns the two keys when `config.angle_colors` is an array of exactly two non-empty strings, and null otherwise.
- **C2** [code] `angleFromPointer()` returns 0 for a pointer straight above the centre, 90 to the right, 180 below and 270 to the left.
- **C3** [code] `gradientCss(angle, first, second)` returns `linear-gradient(<(180 + angle) mod 360>deg, first, second)`.
- **C4** [test] `angleKnob.test.ts` asserts `bubbles.md` contains `--flow: calc(180deg + [[[c:gradient_angle]]]deg);` and `--fill: linear-gradient(var(--flow), var(--tint), var(--tint2));`.
- **C5** [code] `ControlKnob.vue` mounts `AnglePicker` only when the control's type is `number`, `angleColorKeys(config)` is not null and the `angleColors` prop is set; otherwise a number control keeps the slider.
- **C6** [code] `AnglePicker.vue` emits `input` on pointer down and on each pointer move that changes the angle, and `commit` once on pointer up or cancel.
- **C7** [code] `AnglePicker.vue` handles no pointer or key event while `disabled`, and sets `tabindex` to -1.
- **C8** [code] On keydown, `AnglePicker.vue` steps the angle by 1 for an arrow key, by 15 with Shift, sets 0 for Home, and emits both `input` and `commit`.
- **C9** [code] `design.vue` and `ControlPanel.vue` pass `angle-colors` as the live values of the two named controls, and null when either is missing; `ControlPanel.vue` only matches controls with no `source`.
- **C10** [code] `AngleKnob::problems()` refuses `angle_colors` on a non-number control, a value that is not a list of two strings, a key the overlay does not declare, and a key whose control is not a `color`.
- **C11** [test] `AngleKnobTest` covers C10.
- **C12** [test] `AngleColorsBackfillTest` asserts an install from before gets the same `gradient_angle` config as a fresh install with its value kept, and that a second run and a row already declaring `angle_colors` are left as they were.
- **C13** [unverified] In Firefox on the local designer, dragging from the circle's right edge to its lower left saved 225, the circle painted `linear-gradient(45deg, ...)`, and Home on the focused circle saved 0; the overlay's Values tab drew the same circle.

### Unchanged
- `OverlayRenderer.vue` and the overlay CSS read `gradient_angle` as a number; `bubbles.md`'s `--flow` line is not in the diff, so nothing on stream changes.
- `OverlayControl::sanitizeValue()` still clamps a number write to the row's min and max; the picker writes through the same endpoint the slider did.

### Risk
One migration adds `angle_colors` to the `gradient_angle` row of every existing Chat Emote Bubbles
install. The designer and the Values tab draw a circle there instead of a slider.
