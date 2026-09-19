## OL-2609-118 - feat(products): one designer door on the chat product page, and American spelling on the chat surfaces

**Shipped:** 2026-09-20
**Commit:** `git log --grep=OL-2609-118`

### Surface
- `resources/js/pages/products/show.vue` - the "Pick a look" section and its ten preset cards
  removed; the designer row becomes a single bordered CTA card with an icon tile, a primary button
  and a `.product-design` glow in the scoped style block; `Preset` narrowed to `{ key }`;
  `applyPreset()`, `applying`, `swatchStyle()`, `PRESET_FONTS` and the Google Fonts `<link>` deleted;
  `ArrowRight` imported
- `resources/recipes/twitch-chat-overlay/manifest.json` - `ready_message` no longer points below at
  a preset row; `description` and `changelog` spelling
- `resources/recipes/twitch-chat-overlay/chat.md` - four control labels and the prose spelled
  American
- `app/Support/ChatDesigner.php` - group title `Colors`; the `solid` background hint
- `app/Support/ChatPresets.php` - two preset blurbs and the class doc block
- `database/migrations/2026_09_20_120000_rename_chat_control_labels_to_american_english.php` - new
  file: rewrites the four labels on control rows existing installs already hold

### Claims
- **C1** [code] `show.vue` renders no preset card and no Apply button. `presets` is read in exactly two places: the `v-if` on the designer card and the count in its copy.
- **C2** [code] The designer card renders only when `installed` is truthy and `presets.length` is non-zero, and its href is `withLastMileHint(route('products.design', product.slug), product.slug)` - the same target the removed row had.
- **C3** [code] The card is one `<Link>` whose call to action is a `<span class="btn btn-primary">`, not a `<button>`: no interactive element is nested inside the anchor.
- **C4** [code] `PRESET_FONTS` and the `<link rel="stylesheet">` it fed in `<Head>` are gone, and nothing else in `show.vue` names those font families.
- **C5** [code] `.product-design` in the scoped style block carries a violet box-shadow halo plus an inset wash and a 5s `product-design-breathe` keyframe animation; no gradient is declared. The `prefers-reduced-motion` block lists `.product-design` among the selectors set to `animation: none` and cancels the arrow's hover transform.
- **C6** [code] The server payload is untouched: `ProductController::show()` still publishes `ChatPresets::forProduct($slug, $instance)` with `label`, `blurb`, `active` and `preview` per preset, and `ChatPresets::apply()` and the `products.preset` route still serve `design.vue`.
- **C7** [test] `ProductChatPresetsTest` and `ProductChatDesignerTest` pass unchanged; neither is in the diff.
- **C8** [code] `grep -rn olour` over `app/Support/ChatDesigner.php`, `app/Support/ChatPresets.php`, `resources/recipes/twitch-chat-overlay/` and `resources/js/pages/products/design.vue` and `show.vue` returns nothing.
- **C9** [code] `ChatDesigner::GROUPS` has the title `Colors`, and its `keys` arrays are unchanged - no control key was renamed by this change.
- **C10** [code] The migration matches on both `key` and the exact prior label for `twitch_colors`, `name_color`, `text_color` and `background_color`, so a control a streamer renamed themselves is not rewritten.
- **C11** [code] The migration uses `DB::table('overlay_controls')` with literal table and column names, references no Eloquent model, freezes the four label pairs as a private constant in the file, and `down()` reverses the rename.

### Unchanged
- `ChatPresets::PRESETS` keeps all ten looks with their `values` bundles; the presets are the
  designer's starting strip, and only two `blurb` strings are in the diff. Nothing about which
  presets exist changed with the cards going away.
- `ChatPresets::KEYS` and the thirteen control keys are untouched. The migration rewrites `label`
  and nothing else, so `[[[c:name_color]]]` in an overlay keeps resolving.
- The `?product=&state=product` UI-mode hint the card passes is the pre-existing
  `withLastMileHint()` helper, unchanged.

### Risk
Existing installs carry the old labels in their own `overlay_controls` rows; the migration rewrites
them in place on deploy. An account that renamed one of those four controls by hand keeps its name.
