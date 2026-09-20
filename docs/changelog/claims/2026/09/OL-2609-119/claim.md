## OL-2609-119 - feat(chat): search every Bunny font from the chat designer, and drop Google Fonts

**Shipped:** 2026-09-20
**Commit:** `git log --grep=OL-2609-119`

### Surface
- `app/Support/BunnyFonts.php` - new file: the vendored catalogue reader. `slug()`, `has()`, `canonical()`, `cssUrl()`, `choices()`, and the `WEIGHTS` / `HOST` / `CATALOGUE_PATH` constants
- `app/Console/Commands/SyncBunnyFonts.php` - new file: `fonts:sync-bunny`, fetches `https://fonts.bunny.net/list`, trims it and writes `public/fonts-bunny.json`
- `public/fonts-bunny.json` - new file: 1969 families as `slug => {name, category}`
- `app/Http/Controllers/OverlayTemplateController.php` - `renderAuthenticated()` collects controls whose `config['webfont']` is set into a new `webfont_controls` key in the render payload
- `app/Http/Controllers/OverlayControlController.php` - `setValue()` validates a webfont control's value against the catalogue and stores the catalogue's spelling; imports `BunnyFonts`
- `app/Support/ChatDesigner.php` - `CHOICES['font']` removed; `SUGGESTED_FONTS` added; class docblock rewritten for the open vocabulary
- `app/Http/Controllers/ProductController.php` - `design()` ships `suggested_fonts` and `fonts_url`; imports `BunnyFonts`
- `resources/js/components/products/FontPicker.vue` - new file: the search picker
- `resources/js/pages/products/design.vue` - the `font` row renders `FontPicker` ahead of the `choices` branch; two new props
- `resources/js/components/OverlayRenderer.vue` - `syncWebfontLink()`, the `webfontControls` ref, a call per key after `injectHead()`, and a call in `applyControlUpdate()`
- `resources/recipes/twitch-chat-overlay/chat.md` - head loses the six-family Google link and preconnects Bunny; `c:font` gains `webfont=true` and a rewritten description
- `resources/recipes/chat-tower/tower.md` - font link host swapped to Bunny, preconnect pair collapsed to one
- `resources/recipes/follower-bowling/lane.md` - same swap
- `database/migrations/2026_09_20_140000_move_overlay_fonts_off_google.php` - new file: rewrites stored `overlay_templates.head`
- `database/migrations/2026_09_20_150000_declare_installed_chat_font_controls_as_webfonts.php` - new file: backfills `config.webfont` and the description on installed chat font controls
- `tests/Feature/WebfontControlTest.php` - new file, 5 tests
- `tests/Feature/ProductChatDesignerTest.php` - font assertions moved out of the choices test into two new tests; `choices.font` assertion replaced with `suggested_fonts` / `fonts_url`
- `tests/Feature/ProductChatPresetsTest.php` - preset font assertion changed from the head link to the catalogue

### Claims
- **C1** [code] `BunnyFonts::cssUrl('Space Grotesk')` returns `https://fonts.bunny.net/css?family=space-grotesk:400,700&display=swap`.
- **C2** [code] `BunnyFonts::cssUrl()` returns null for a family absent from the catalogue rather than a constructed URL.
- **C3** [code] `BunnyFonts::slug($entry['name'])` equals the key for every entry in `public/fonts-bunny.json`.
- **C4** [code] `public/fonts-bunny.json` contains neither `buda` nor `coda-caption`, the only two upstream families with neither weight 400 nor weight 700.
- **C5** [code] `SyncBunnyFonts::handle()` returns `FAILURE` without writing the file when any upstream family's slug is not its name lowercased and hyphenated.
- **C6** [code] `renderAuthenticated()` includes key `webfont_controls` in its JSON response, holding the `tagIdentifier()` of each control whose `config['webfont']` is truthy.
- **C7** [code] `OverlayControlController::setValue()` responds 422 and leaves `value` unchanged when a control has `config['webfont']` and the submitted value is not in the catalogue.
- **C8** [code] `setValue()` stores `BunnyFonts::canonical()` of the submitted value for a webfont control, so `space grotesk` is written as `Space Grotesk`.
- **C9** [code] `setValue()` applies neither check to a control without `config['webfont']`.
- **C10** [code] `OverlayRenderer.vue` `syncWebfontLink()` derives its href as the family name lowercased with spaces replaced by hyphens, against `WEBFONT_HOST` and `WEBFONT_WEIGHTS`, and returns without creating a link when the name does not match `/^[A-Za-z0-9]+(?: [A-Za-z0-9]+)*$/`.
- **C11** [code] `syncWebfontLink()` mutates the existing element's `href` when one with that id is already present rather than removing and re-appending it.
- **C12** [code] `applyControlUpdate()` calls `syncWebfontLink()` only for keys present in `webfontControls`.
- **C13** [code] `resources/recipes/twitch-chat-overlay/chat.md`'s `head` block contains no `link` element with a `family=` query.
- **C14** [code] The same file's `c:font` control-detail entry carries `webfont=true`, which `OverlayMarkdown::behaviourPairs()` parses to boolean true.
- **C15** [code] `ChatDesigner::CHOICES` has no `font` key.
- **C16** [code] Every `ChatDesigner::SUGGESTED_FONTS` value satisfies `BunnyFonts::canonical($value) === $value`.
- **C17** [code] Every `ChatPresets::PRESETS` entry's `font` value satisfies the same.
- **C18** [code] No file under `resources/recipes/` contains the string `fonts.googleapis.com` or `fonts.gstatic.com`.
- **C19** [code] `FontPicker.vue`'s `results` computed returns the suggested list concatenated with the remaining catalogue when the query and category are both empty, not the suggested list alone.
- **C20** [code] `FontPicker.vue` renders a row reading `Showing N of M` whenever `results.length < matchCount`.
- **C21** [test] `WebfontControlTest` asserts C1, C2, C3, C4, C6, C7, C8 and C9.
- **C22** [test] `ProductChatDesignerTest` asserts C13, C14, C15 and C16, and separately that a freshly installed chat overlay's `font` control row carries `config['webfont'] === true`.
- **C23** [unverified] With the `config['webfont']` branch in `setValue()` replaced by `if (false)` and the same branch in `renderAuthenticated()` likewise, `php artisan test --filter=WebfontControlTest` failed 2 of 5; both passed again on restore.
- **C24** [unverified] On `overlabels.test` in Chrome, the designer's preview frame held exactly one `link` with id `ol-webfont-font`. Picking `Cinzel` from the picker rewrote its href from `slackside-one:400,700` to `cinzel:400,700` with no reload, the element count stayed at 1, and the computed `font-family` of `.ol-chat .name` became `Cinzel, "Albert Sans", system-ui, sans-serif`.
- **C25** [unverified] In the same session the picker's unfiltered list rendered 60 rows beginning with the six suggested names followed by `42dot Sans`, above the row `Showing 60 of 1969. Keep typing to narrow it down.`
- **C26** [unverified] `https://fonts.bunny.net/css2?family=...` answers the exact multi-family query the two `/engine` recipes previously sent to `fonts.googleapis.com`, returning `@font-face` blocks for Albert Sans at 400/600/800 and JetBrains Mono at 400/700.
- **C27** [unverified] Bunny answers an unknown family with HTTP 200 and a CSS comment containing `Error: API Error` and no `@font-face`, which is why C2 and C7 exist.
- **C28** [unverified] Bunny serves whichever of `:400,700` a family has and ignores the other; a request naming only weights a family lacks returns the C27 error body.
- **C29** [unverified] `https://fonts.bunny.net/list` requires no credential. A `BUNNY_API_KEY` in `.env` authenticates against `api.bunny.net` (a bogus key there returns 401, this one returns 200) but `api.bunny.net/fonts`, `/font`, `/fonts/list` and `/fonts/families` all return 404, and the list endpoint returns byte-identical responses with and without the header.

### Unchanged
- `injectHead()` in `OverlayRenderer.vue` still injects the template head exactly once at load and is not in the diff. Font loading was moved beside it rather than into it, so no overlay's head is re-parsed or re-applied on a control update.
- The template `head` column is still served raw by `renderAuthenticated()` with no tag substitution; `[[[c:font]]]` does not work in a head block and this change did not make it work.
- `OverlayControl::TYPES` and `sanitizeValue()` are untouched. A webfont control is an ordinary `text` control carrying a config key, so no new control type, no new validation enum and no change to `ControlFormModal.vue`.
- `--font: [[[c:font]]]` in the chat recipe's CSS, and the `var(--ol-c-font)` binding it compiles to, are unchanged: the control's value is still the CSS family name, which is why names rather than slugs are stored.
- `ChatPresets::PRESETS` values are unchanged - all ten already named families Bunny serves.
- `ChatPresets::apply()` still writes through `writeValue()` and does not pass the C7 allowlist; C17's test is what covers that path instead.
- `resources/views/app.blade.php` already loaded Albert Sans from `fonts.bunny.net` before this change and is not in the diff.
- `resources/fonts/*.ttf` are read only by `OgImageService` for OG image rendering and are unrelated to overlay type; not in the diff.
- The `overlabels:engine` meta line in both `/engine` recipes still carries its original date and sha256. Nothing reads it, and rewriting it would misdate the export.

### Risk
Two migrations rewrite existing rows. The first edits `overlay_templates.head` for every overlay
holding a Google Fonts URL; the second sets `config.webfont` on chat font controls, matched on the
description the recipe wrote. Both have an intentionally empty `down()`.

Existing chat installs need the second migration or their font control writes a value nothing loads,
because the first migration has already removed the head link they used to rely on. The two must
ship together.

An overlay whose font control holds a family absent from the catalogue renders the CSS fallback
(`Albert Sans`, then the system font) rather than erroring. Values written before this change were
never validated, so this is reachable.

`public/fonts-bunny.json` is committed rather than built on deploy, so a new family appearing at
Bunny is not offered until someone runs `fonts:sync-bunny` and commits the result.
