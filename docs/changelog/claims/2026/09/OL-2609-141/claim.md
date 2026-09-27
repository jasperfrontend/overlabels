## OL-2609-141 - feat(products): every product declares its own designer, and Chat Emote Bubbles gets one

**Shipped:** 2026-09-28
**Commit:** `git log --grep=OL-2609-141`

### Surface
- `resources/recipes/recipe-manifest.schema.json` - new top-level `designer` object (`overlay`, `skin_key`, `groups`, `presets`, `extras`, `stage`)
- `resources/recipes/twitch-chat-overlay/manifest.json` - a `designer` block transcribed from the deleted `ChatPresets::PRESETS` and `ChatDesigner::GROUPS`; Prettier reflowed `installs.overlays`
- `resources/recipes/chat-emote-bubbles/manifest.json` - a `designer` block with four groups, three presets, two extras, one stage; Prettier reflowed `installs.overlays`
- `app/Services/Recipes/RecipeManifestValidator.php` - new `designerErrors()`, called from `semanticErrors()`; imports `ProductDesigner`
- `app/Support/ProductDesigner.php` - new: the one reader of the block, plus the preview token and URL helpers moved from `ChatDesigner`
- `app/Support/ChatPresets.php` - deleted
- `app/Support/ChatDesigner.php` - deleted
- `app/Support/BunnyFonts.php` - new `SUGGESTED` constant (the six-font shortlist, moved from `ChatDesigner::SUGGESTED_FONTS`)
- `app/Http/Controllers/ProductController.php` - `show()` passes `designer` instead of `presets`; `applyPreset()` and `design()` gate on the manifest block; `design()` sends `skin_key`, `extras`, `stage`, and the chat props only when declared
- `app/Http/Controllers/SavedChatPresetController.php` - injects `RecipeCatalog`; resolves the designer from the manifest; every door scoped by `product`; new `own()`
- `app/Http/Controllers/OverlayTemplateController.php` - `show()` and `edit()` pass a `fonts` prop; new private `fonts()`; imports `BunnyFonts`
- `app/Models/UserChatPreset.php` - `product` in `$fillable`; docblock
- `database/migrations/2026_09_28_120000_scope_saved_looks_to_a_product.php` - new: `product` column, backfill, unique index widened
- `routes/web.php` - comments on the designer and preset routes only
- `resources/js/components/controls/ControlKnob.vue` - new: the shared knob
- `resources/js/utils/designerStage.ts` - new: `stageFor()`, `DEFAULT_STAGE`
- `resources/js/utils/designerStage.test.ts` - new file, 6 tests
- `resources/js/pages/products/design.vue` - reads the manifest-shaped props, mounts `ControlKnob`, gates the skin strip, the feed section and the sample-chat bar on `skin_key` / `extras`, sizes the stage with `stageFor()`; the `stage` element ref renamed `stageEl`
- `resources/js/pages/products/show.vue` - `Product.presets` becomes `Product.designer`; the card gates on it and names its groups
- `resources/js/components/ControlPanel.vue` - rows instead of cards; the five designer types mount `ControlKnob` with a per-row "Saved" receipt; `fonts` prop; `ColorPicker` import, `saveColorValue()`, `toggleBoolean()`, `numberConstraintsText()`, `onNumberInput()` and the out-of-range tint removed
- `resources/js/pages/templates/show.vue` - `fonts` prop, passed to `ControlPanel`
- `resources/js/pages/templates/edit.vue` - same
- `tests/Feature/ProductChatDesignerTest.php` - repointed at `ProductDesigner` and the manifest; the groups test removed (now the validator's); props test asserts `skin_key`, `extras`, `stage` and no `choices`
- `tests/Feature/ProductChatPresetsTest.php` - repointed; asserts `product.designer` instead of `product.presets`
- `tests/Feature/UserChatPresetTest.php` - repointed; one new test on product scoping
- `tests/Feature/ProductChatEmoteBubblesTest.php` - three new tests on the designer block, the page and the product card
- `tests/Feature/RecipeDesignerBlockTest.php` - new file, 15 tests
- `tests/Feature/ChatTickerOverflowTest.php` - `ChatPresets::PRODUCT` replaced by the literal slug
- `CLAUDE.md` - the "Control vocabularies and the unified product designer" subsection rewritten for the shipped state

### Claims
- **C1** [code] The schema declares `designer` with `additionalProperties: false`, required `overlay`, `groups`, `presets`; `extras.items.enum` is `["sample_chat", "chat_window", "chat_filters"]`; `stage` items require integer `w` and `h` and take an optional `when` object of strings.
- **C2** [code] `ProductDesigner::EXTRAS` equals that enum, and `ProductDesigner::KNOB_TYPES` is `['text', 'number', 'boolean', 'color']`.
- **C3** [test] `RecipeDesignerBlockTest` "rejects an extra the page does not know" asserts C2's first half against the schema file.
- **C4** [code] `RecipeManifestValidator::designerErrors()` reports, with the recipe directory: an `overlay` not in `installs.overlays`; a grouped key the document does not declare; a key in two groups; the skin key in a group; a skin key not declared or not `text`; a declared control of a `KNOB_TYPES` type in no group and not the skin key; a grouped control of another type; a preset missing a designer key or naming a non-designer key; a preset value for a control with `config.choices` outside them; a preset whose skin value is not its key; a duplicate preset key; a duplicate group title; a `stage.when` naming an undeclared control or a value outside its choices.
- **C5** [test] `RecipeDesignerBlockTest` asserts each message in C4 by mutating the shipped chat manifest, in 15 tests, and asserts both shipped blocks produce no errors next to their documents.
- **C6** [code] Without a directory, `designerErrors()` still reports the unknown overlay ref, the double-grouped key and the block's own duplicates, and skips every document-side check.
- **C7** [test] `RecipeDesignerBlockTest` "checks only the block itself without the recipe directory" asserts C6.
- **C8** [code] The chat manifest's `designer.presets` holds the ten bundles with the same keys, labels, blurbs and values `ChatPresets::PRESETS` held at `959d24b7`, in the same order; `groups` equals `ChatDesigner::GROUPS` at that commit; `skin_key` is `skin`; `extras` is `sample_chat, chat_window, chat_filters`; `stage` is `500x800` with a `layout = ticker` exception of `1920x80`.
- **C9** [test] `ProductChatPresetsTest` "has ten presets, each writing exactly the thirteen controls the overlay declares" and "names only skins the overlay CSS defines..." assert the ten bundles' shape, skin equality and CSS/Bunny validity from the manifest.
- **C10** [code] The bubbles manifest's `designer` has `overlay: bubbles`, no `skin_key`, groups Look (`look`, `bubble_size`, `bubble_color`), Motion (`direction`, `speed`, `pop_after`), Spawn (`spawn`, `spawn_x`, `spawn_y`), Crowd (`max_bubbles`), presets `soap`, `winter`, `valentine`, extras `sample_chat, chat_filters`, stage `1920x1080`.
- **C11** [code] The `soap` preset's values equal the ten defaults `bubbles.md` declares.
- **C12** [test] `ProductChatEmoteBubblesTest` "declares a designer with three looks..." asserts C10 and C11.
- **C13** [code] `ProductController::design()`, `applyPreset()` and `SavedChatPresetController::resolve()` 404 when `ProductDesigner::declared()` of the listed manifest is null; none of them reads a product slug constant.
- **C14** [test] `ProductChatDesignerTest` "refuses a product with no designer and an account with no install" and `ProductChatPresetsTest` "refuses an unknown preset, a product without a designer, and an account that has not installed" assert the 404s for `chat-tower`.
- **C15** [test] `ProductChatEmoteBubblesTest` "opens the designer for an install, with the bubbles controls and no chat window" asserts the bubbles design page renders with 10 controls, 3 presets, empty `skins`, null `skin_key`, 4 groups, the two extras, the stage, `chat_filters` present and `chat_window` absent, and 404s for an account with no install.
- **C16** [test] `ProductChatEmoteBubblesTest` "shows the designer card on the product page once installed, and applies a look from it" asserts `product.designer.presets` has 3 entries with `soap` active after install, that POSTing `winter` writes `look = snow`, `direction = down`, `spawn = random`, `max_bubbles = 60` and dispatches `ControlValueUpdated` ten times, and that `clean` 404s on this product.
- **C17** [code] `ProductController::show()` passes `product.designer` as `{presets: [{key, label, blurb, active}], groups: [titles]}` or null; `show.vue` renders the card when `installed && designer` and its copy joins the lowercased group titles.
- **C18** [code] `design()` includes `chat_window` / `chat_window_max` only when `extras` contains `chat_window`, and `chat_filters` / `max_hidden_logins` only when it contains `chat_filters`; the `choices` prop is gone.
- **C19** [test] `ProductChatDesignerTest` "hands the page every control the overlay has, with the presets, the groups, the extras and the window cap" asserts the chat page's `skin_key`, `extras`, `stage`, `chat_window` and `missing('choices')`.
- **C20** [code] The migration adds a nullable `product` string(50) to `user_chat_presets`, sets every null to `twitch-chat-overlay`, makes it not null, drops the unique on `(user_id, name)` and adds one on `(user_id, product, name)`; `down()` deletes rows whose product is not `twitch-chat-overlay` before reversing.
- **C21** [code] `SavedChatPresetController` counts the cap, validates name uniqueness, lists and resolves a preset all `where product = <current slug>`; `own()` 404s a preset whose `user_id` or `product` differs.
- **C22** [test] `UserChatPresetTest` "keeps each product's looks to its own designer" asserts a bubbles look is listed only on the bubbles designer, the same name is free on the chat product, all four chat doors 404 for the bubbles look and leave it unchanged, and the cap of 20 is per product.
- **C23** [code] `ControlKnob.vue` picks its widget from the row: `text` with `controlChoices()` non-empty renders a `<select>` and the held choice's hint; `text` with `config.webfont === true` and a `fontsUrl` renders `FontPicker`; `boolean` a checkbox; `color` an `<input type=color>` beside a text field; `number` an `<input type=range>` with the row's `min`/`max`/`step` beside an `<input type=number>`; anything else a text input. It emits `input` on the range and color inputs' `input` events and `commit` on every widget's `change` (or FontPicker's update).
- **C24** [code] `design.vue` mounts `ControlKnob` for every grouped key with `@input="writeControlSoon"` and `@commit="writeControl"`; the skin section renders only when `skin_key` names a control the page has; the "What the feed shows" section renders only when `chat_window` or `chat_filters` is in `extras`; the sample-chat bar only when `sample_chat` is; `sourceSize` is `stageFor(props.stage, valueOf)`.
- **C25** [code] `stageFor()` returns the last entry whose every `when` pair the controls satisfy, else the first entry without `when`, else `1920x1080`, and never returns the `when` clause.
- **C26** [test] `designerStage.test.ts` asserts C25 in 6 tests.
- **C27** [code] `ControlPanel.vue` mounts `ControlKnob` for a control that is not `source_managed` and is `number`, `boolean`, `color`, or `text` with choices or with `webfont` and a `fonts` prop; on `commit` it posts through `postValue()`, drops the local value so the row shows the server's answer, and sets `receipts[id]` to `Saved: <choice label | on | off | value>` for two seconds, rendered beside the label in the knob's `aside` slot.
- **C28** [code] `ControlPanel.vue` renders counter, timer, expression, datetime, free-text and source-managed rows under the same label line with their previous widgets; the timer gradient tints, the number out-of-range tint, the type badge and the "Min/Max/Step" line are gone; the description renders under every row that has one.
- **C29** [code] `OverlayTemplateController::show()` and `edit()` pass `fonts` as `{url: asset(BunnyFonts::CATALOGUE_PATH), suggested: BunnyFonts::SUGGESTED}`, and both template pages hand it to `ControlPanel`.
- **C30** [test] `ProductChatDesignerTest` "suggests only fonts Bunny actually serves..." asserts every `BunnyFonts::SUGGESTED` family is canonical in the catalogue with a non-empty hint.
- **C31** [code] `ProductDesigner::TOKEN_PURPOSE` is still the string `chat_designer` and `TOKEN_SESSION_KEY` still `chat_designer_token`; `TOKEN_NAME` is `Designer preview`; `previewToken()` and `previewUrl()` are the bodies `ChatDesigner` had.
- **C32** [test] `ProductChatDesignerTest` "keeps one preview token, marked and short-lived, across renders" asserts the purpose literal `chat_designer` and the name constant.
- **C33** [unverified] On `overlabels.test` in Chrome on 2026-09-28: `/products/chat-emote-bubbles/design` rendered with sample-chat emotes bubbling at 1920x1080; clicking Winter moved the knobs to Snowflake, 88, `#bfe3ff`, Down, 2, veiled the stage until the frame reported the writes, and a subsequent "More" burst drew pale blue snowflakes at random positions; `/products/twitch-chat-overlay/design` rendered its ten presets, the skin strip and a 1920x80 stage for the ticker layout; the Values tab of template 359 rendered rows, and typing 90 into Bubble size's exact box and tabbing away showed "Saved: 90" beside the label, with `overlay_controls.value = 90` read back by tinker at 13:40:41 UTC; the value was then put back to 88.
- **C34** [unverified] The one console warning during C33, `[emotes] bttv:channel failed`, predates this change (no BTTV channel set on the local account).

### Unchanged
- `OverlayRenderer.vue` and the `?chat=sample` frame protocol: the bubbles preview works because `templateUsesChat` already counts the `emotes` loop as chat and the sample feed goes through `injectRawLine`; neither is in the diff.
- `OverlayControlController::setValue()`: every knob on both pages still writes through it, and a text value outside a vocabulary is still accepted; not in the diff.
- `FontPicker.vue`, `controlChoices.ts`, `OverlayMarkdown.php` and the two overlay documents (`chat.md`, `bubbles.md`): the block reads the vocabularies those declare and adds none; none in the diff.
- `ControlsManager.vue` (the definitions tab) and `ControlFormModal.vue`: the Values tab overhaul is `ControlPanel.vue` only; `ColorPicker.vue` stays for `ControlFormModal.vue` and is not in the diff.
- The Tower and Checkin settings pages and `external_integrations.settings`: a designer edits overlay controls only; not in the diff.
- The `user_chat_presets` table name, `UserChatPreset` model name and `SavedChatPresetController` name: kept, per "saved looks stay in one table"; only the column and the scoping moved.
- The route paths and names under `/products/{slug}/design`, `/presets/{preset}` and `/saved-presets`: unchanged; only two comments in `routes/web.php` moved.

### Risk
The migration runs on deploy and stamps every existing saved look `twitch-chat-overlay`; the unique
index changes shape. A copy a streamer made from a product overlay is still not an install and still
has no designer. The chat designer's per-knob `lifetime` hint ("Messages stay until they scroll off")
is gone; the control's description carries that sentence on the Values tab. The Values tab no longer
tints a running timer or an out-of-range number; the timer keeps its green/red dot and the server
clamps numbers on write.
