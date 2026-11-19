## OL-2611-023 - feat(controls): a knob greys itself out while another holds a value; a Plain look for Emote Bubbles; the control dialog keeps config it does not draw; a per-product sample-chat ceiling

**Shipped:** 2026-11-19
**Commit:** `git log --grep=OL-2611-023`

### Surface
- `app/Support/ControlConditions.php` - new: reads `config.enabled_if` (`of()`) and lists the problems with one overlay's declarations (`problems()`)
- `app/Services/Recipes/RecipeManifestValidator.php` - `semanticErrors()` also merges new `controlConditionErrors()`, which runs `ControlConditions::problems()` over every installed overlay document; `designerErrors()` refuses `sample_chat_max` without `sample_chat` in extras
- `resources/js/utils/controlConditions.ts` - new: `enabledIf()` and `disabledReason()`, evaluating through `useConditionalTemplates()`
- `resources/js/utils/controlConditions.test.ts` - new: Vitest cases for both functions
- `resources/js/components/controls/ControlKnob.vue` - new `disabledReason` prop: the knob sits in a `<fieldset>` disabled by it, the reason renders under it, the choice hint is hidden while it is set
- `resources/js/pages/products/design.vue` - `conditionSubjects` and `reasonFor()`; passes `disabled-reason` to every knob; the sample-chat slider's max is the `sample_chat_max` prop, 120 without it; the sample-chat row is a bar joined to the stage with a Play/Pause button (`togglePlay()`), a full-width slider and a large rate readout
- `resources/js/components/ControlPanel.vue` - `conditionSubjects`; passes `disabled-reason` to every knob
- `resources/js/utils/controlConfigPayload.ts` - new: `controlConfigPayload()`, the config the control dialog sends on save
- `resources/js/utils/controlConfigPayload.test.ts` - new: Vitest cases for it
- `resources/js/components/ControlFormModal.vue` - `buildPayload()` takes its number, timer and other-type config from `controlConfigPayload()`, passing the edited or copied row's config
- `resources/recipes/chat-emote-bubbles/bubbles.md` - `look` gains the `plain` choice and description; a `.look-plain .skin img` rule; `enabled_if` on `bubble_color`, `bubble_color_2`, `gradient_angle`, `spawn_x`, `spawn_y`; intro names the plain look
- `resources/recipes/chat-emote-bubbles/manifest.json` - a fourth designer preset `plain`; copy that counted three looks now counts four; `designer.sample_chat_max` 300
- `resources/recipes/recipe-manifest.schema.json` - `designer.sample_chat_max`, an integer from 10 to 400
- `app/Support/ProductDesigner.php` - `DEFAULT_SAMPLE_CHAT_MAX` (120) and `sampleChatMax()`
- `app/Http/Controllers/ProductController.php` - the design page sends `sample_chat_max` when `sample_chat` is in extras
- `tests/Feature/ProductChatDesignerTest.php` - the chat designer page sends `sample_chat_max` 120
- `resources/recipes/twitch-chat-overlay/chat.md` - `enabled_if` on `background_color`
- `resources/views/welcome/hero.blade.php` - bubbles card label "3 design presets" becomes "4 design presets"
- `database/migrations/2026_11_19_120000_add_plain_look_to_installed_chat_emote_bubbles.php` - new: appends the `plain` choice and the `.look-plain` CSS rule to existing Emote Bubbles installs
- `database/migrations/2026_11_19_130000_declare_enabled_if_on_installed_product_controls.php` - new: adds the recipes' `enabled_if` declarations to existing Emote Bubbles and Twitch Chat installs
- `tests/Unit/ControlConditionsTest.php` - new: 8 tests on `ControlConditions`
- `tests/Feature/BubblesPlainLookBackfillTest.php` - new: 4 tests on the Plain backfill
- `tests/Feature/EnabledIfBackfillTest.php` - new: 2 tests on the `enabled_if` backfill
- `tests/Feature/RecipeDesignerBlockTest.php` - three tests added: a misspelled choice in an `enabled_if` fails validation; `sample_chat_max` without `sample_chat`, and past 400, fail validation
- `tests/Feature/ProductChatEmoteBubblesTest.php` - preset list and counts expect `plain` as a fourth preset; the design page sends `sample_chat_max` 300
- `tests/Feature/ControlChoicesBackfillTest.php` - the `look` assertion compares against that migration's own frozen list instead of the live document
- `tests/Feature/BubblesGradientBackfillTest.php` - the "exactly what a fresh install has" test also runs the `enabled_if` backfill after the gradient backfill
- `CLAUDE.md` - `enabled_if` bullets and the control dialog config fix, under "Control vocabularies and the unified product designer"

### Claims
- **C1** [code] `ControlConditions::of()` returns a list for a string or a non-empty list of non-empty strings under `enabled_if`, and null for anything else.
- **C2** [code] `ControlConditions::problems()` refuses `enabled_if` on a control whose type is not in `ProductDesigner::KNOB_TYPES`.
- **C3** [code] `ControlConditions::problems()` refuses a condition whose subject does not start with `c:` or names a key the same overlay does not declare.
- **C4** [code] `ControlConditions::problems()` refuses a condition whose subject is a control outside `ProductDesigner::KNOB_TYPES`.
- **C5** [code] `ControlConditions::problems()` refuses a condition whose subject is the declaring control itself.
- **C6** [code] `ControlConditions::problems()` refuses an `=` or `!=` comparison whose value is not one of the subject's `config.choices`, when it declares any.
- **C7** [code] `ControlConditions::problems()` refuses a condition written with `==`.
- **C8** [code] Conditions are parsed with `Dsl::conditionBodyPattern()` in PHP and `parseCondition()` / `evaluateCondition()` from `useConditionalTemplates()` in TypeScript.
- **C9** [code] `RecipeManifestValidator::semanticErrors()` includes `controlConditionErrors()`, prefixing each message with the document's file name.
- **C10** [code] `disabledReason()` returns null when every condition holds, and the sentence for the first failing condition otherwise.
- **C11** [code] `disabledReason()` skips a condition whose subject is not in the map it is given.
- **C12** [code] `ControlKnob.vue` sets `disabled` on its `<fieldset>` exactly when `disabledReason` is truthy, and renders the reason in a `<p>` outside the fieldset.
- **C13** [code] Neither `design.vue` nor `ControlPanel.vue` writes a control value as a result of a condition.
- **C14** [code] `bubbles.md` declares `enabled_if=["c:look != plain"]` on `bubble_color`, `bubble_color_2` and `gradient_angle`.
- **C15** [code] `bubbles.md` declares `enabled_if=["c:spawn = cannon"]` on `spawn_x` and `spawn_y`.
- **C16** [code] `chat.md` declares `enabled_if=["c:background != none"]` on `background_color`.
- **C17** [code] The `plain` preset in `manifest.json` sets `look` to `plain` and every other designer key to the `soap` preset's value.
- **C18** [test] `ControlConditionsTest` covers C1 through C7.
- **C19** [test] `controlConditions.test.ts` covers C10 and C11.
- **C20** [test] `RecipeDesignerBlockTest` "rejects an enabled_if that names a choice the control does not have" asserts C9's message for a misspelled `cannon`.
- **C21** [test] `BubblesPlainLookBackfillTest` asserts an install from before Plain ends with the same `look` config and CSS as a fresh install, and that a second run changes nothing.
- **C22** [test] `BubblesPlainLookBackfillTest` asserts a `look` row with any other choice list, and a hand-made `look` control, are left alone.
- **C23** [test] `BubblesPlainLookBackfillTest` asserts an install whose heart emote rule was rewritten keeps its CSS and still gains the `plain` choice.
- **C24** [test] `EnabledIfBackfillTest` asserts installs of both products from before end with the same control configs as a fresh install, values untouched.
- **C25** [test] `EnabledIfBackfillTest` asserts a row that already declares `enabled_if`, and a hand-made control with a matching key, are left alone.
- **C26** [unverified] In Firefox on the local designer, setting Look to Plain disabled the two color knobs and the gradient angle with "Not used while Look is Plain.", and the Values tab showed Spawn X and Y disabled with "Only used while Spawn is Cannon.".
- **C27** [code] `controlConfigPayload()` returns the existing config minus every key in `DIALOG_KEYS`, plus the form's fields for a number or counter, and null for other non-timer types when nothing is left.
- **C28** [code] `controlConfigPayload()` returns a timer config built from the form alone, as `buildPayload()` did before.
- **C29** [code] `ControlFormModal.vue` passes `props.control?.config ?? props.copyFrom?.config ?? null` as the existing config.
- **C30** [test] `controlConfigPayload.test.ts` asserts `choices`, `webfont` and `enabled_if` survive a save of text, color and number controls, and that another type's dialog keys are dropped.
- **C31** [unverified] Before the fix, 4 of the 6 cases in `controlConfigPayload.test.ts` failed against a version of `controlConfigPayload()` holding the old `buildPayload()` logic.

- **C32** [code] `ProductDesigner::sampleChatMax()` returns the block's `sample_chat_max` when it is a positive integer, else `DEFAULT_SAMPLE_CHAT_MAX` (120).
- **C33** [code] `ProductController` sends `sample_chat_max` only when `sample_chat` is among the designer's extras.
- **C34** [code] The schema bounds `designer.sample_chat_max` to integers from 10 to 400.
- **C35** [test] `ProductChatEmoteBubblesTest` asserts the bubbles design page sends 300, and `ProductChatDesignerTest` asserts the chat design page sends 120.
- **C36** [test] `RecipeDesignerBlockTest` asserts `sample_chat_max` without `sample_chat` in extras is refused.

- **C37** [code] `togglePlay()` in `design.vue` sets the rate to 0 when playing, else to the last non-zero rate, else to a quarter of `rateMax` rounded to tens with a floor of 10.

### Unchanged
- `OverlayControl::sanitizeValue()` and `OverlayControlController::setValue()` are what a knob's commit reaches; neither is in the diff, so a disabled control's value is still accepted from any other writer.
- `ProductDesigner` builds the `controls` prop the designer reads `config` from; it is not in the diff.
- `OverlayControlController::update()` still stores the `config` it is sent whole; the fix is in what the dialog sends.

### Risk
Two migrations rewrite `overlay_controls.config` on every existing Emote Bubbles and Twitch Chat
install, and one appends a CSS rule to Emote Bubbles overlays. Nothing on stream changes: the new
rule only applies to the new `plain` look.
