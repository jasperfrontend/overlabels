## OL-2609-090 - feat(products): ten looks for Twitch Chat, a skin control and one-click presets on the product page

**Shipped:** 2026-09-17
**Commit:** `git log --grep=OL-2609-090`

### Surface
- `resources/recipes/twitch_chat/manifest.json` - `version` 1 -> 2; description, changelog and ready message mention the looks
- `resources/recipes/twitch_chat/chat.md` - new `skin` text control (default `clean`) first in the controls table with its detail line; root class gains `skin-[[[c:skin]]]`; `Silkscreen` added to the Google Fonts link; ten `.skin-*` rule blocks and an `ol-chat-blink` keyframe added to the css; copy says thirteen
- `app/Support/ChatPresets.php` - new file: `PRODUCT`, `OVERLAY_REF`, `KEYS`, `PRESETS` (ten bundles), `has()`, `overlayFor()`, `forProduct()`, `apply()`, `matches()`
- `app/Http/Controllers/ProductController.php` - `show()` publishes `product.presets` from `ChatPresets::forProduct()`; new `applyPreset()` action; imports for `ChatPresets` and `ControlValueUpdated`
- `routes/web.php` - `POST /products/{slug}/presets/{preset}` named `products.preset`, inside the auth group next to install and uninstall
- `resources/js/pages/products/show.vue` - `Preset` interface and `presets` on `Product`; `applyPreset()`, `swatchStyle()`, `PRESET_FONTS` link in `<Head>` when presets exist; a "Pick a look" section of ten cards (swatch, label, blurb, Apply or Applied) rendered only when installed
- `tests/Feature/ProductTwitchChatTest.php` - control count 12 -> 13 with `skin` first and default `clean`; root class assertion includes `skin-[[[c:skin]]]`
- `tests/Feature/ProductChatPresetsTest.php` - new file

### Claims
- **C1** [code] `ChatPresets::PRESETS` has exactly ten entries keyed `clean`, `terminal`, `bubbles`, `neon`, `paper`, `broadcast`, `caption`, `pixel`, `cards`, `vapor`, and each entry's `values` array has exactly the keys in `ChatPresets::KEYS`, with `values['skin']` equal to the entry's own key.
- **C2** [code] `ChatPresets::KEYS` equals the set of control keys `OverlayMarkdown::parse()` reads from `chat.md`, which is thirteen.
- **C3** [code] For every skin other than `clean`, `chat.md`'s css contains a `.skin-<name>` selector; `clean` has no rules and is the base look.
- **C4** [code] Every skin rule is at least `.skin-x .msg` in specificity and appears after the `.bg-x .msg` rules in the css, so a skin overrides a background rule where it sets the same property.
- **C5** [code] `ChatPresets::forProduct()` returns `[]` for any slug but `twitch_chat`, and otherwise ten rows with `active` true only for the preset whose every value string-equals the overlay's current control values; with no install every `active` is false.
- **C6** [code] `ChatPresets::apply()` writes each preset value through `OverlayControl::writeValue()` onto the matching control of the given template, skips a key the template has no control for, never creates a control, and returns the controls it wrote.
- **C7** [code] `ProductController::applyPreset()` aborts 404 unless the slug is `twitch_chat`, the preset key exists, the account has an instance, and that instance's `chat` overlay is owned by the account; on success it dispatches `ControlValueUpdated` once per written control with the template's slug, the control's `broadcastKey()`, type, value and the account's `twitch_id`, then redirects to `products.show`.
- **C8** [code] `show.vue` renders the "Pick a look" section only when `installed` is truthy and `product.presets` is non-empty; an active preset renders "Applied" and no button; the `<link>` to the Google Fonts families is rendered only when presets exist.
- **C9** [code] The card swatch draws two sample lines in the preset's font, text colour and background; with `twitch_colors` `1` the sample names use two fixed Twitch-like colours, otherwise the preset's `name_color`.
- **C10** [code] The recipe manifest's `version` is 2, so `RecipeCatalog::sync()` creates a new `recipes` row and a version 1 install keeps its own row; `ProductController::instanceFor()` finds an instance by slug across versions.
- **C11** [test] `ProductChatPresetsTest` asserts C1, C2, C3 (through the css), every preset's layout and background having a rule and its font being in the head's font link, C5 before and after an install, the apply endpoint writing all thirteen `terminal` values and dispatching `ControlValueUpdated` thirteen times, `active` flipping from `clean` to `terminal`, no active preset after one control drifts, 404 for an unknown preset, for `chat_tower`, and for an account without an install, and a redirect for a guest.
- **C12** [test] `ProductTwitchChatTest` asserts the install creates thirteen controls with `skin` first and `clean` as its value.
- **C13** [unverified] Each of the ten presets was applied to a local install on `overlabels.test` and viewed in Chrome with generated chat from a `VITE_CHAT_HOSE=1` build; the root class, computed font family and rendering matched the preset in every case, and the Clean card's Apply button on the product page turned to Applied after the POST.
- **C14** [unverified] Three skin rules were corrected from what Chrome showed before this shipped: bubbles put the badges on a line above the name and stretched every bubble to the max width (`.body` is now the block, `.msg` is `fit-content`); broadcast tiles had gaps (the ticker root now carries the background with `gap: 0`); neon and pixel rows stretched full width (`fit-content` on both).

### Unchanged
- `OverlayControl::writeValue()`, `ControlValueUpdated` and the controls tab's `setValue` endpoint are not in the diff: `applyPreset()` composes the same write and the same broadcast per control that `OverlayControlController::setValue()` already performs.
- `RecipeInstaller::installOverlay()` is not in the diff: the thirteenth control is one more row in the document's controls table, installed by the same loop as the other twelve.
- The `chat` foreach, `useTwitchChat.ts` and `OverlayRenderer.vue` are not in the diff: a skin is CSS keyed off a class the template already emits from a control.

### Risk
An account that installed version 1 (none on prod at the time of shipping, one local) has no `skin` control: the product page shows the presets, Apply writes the twelve controls it has and skips `skin`, and the overlay keeps the base look. Reinstalling picks up version 2.
