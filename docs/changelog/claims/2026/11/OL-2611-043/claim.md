## OL-2611-043 - feat(products): Snow Overlay for OBS

**Shipped:** 2026-11-27
**Commit:** `git log --grep=OL-2611-043`

### Surface
- `resources/js/snow/snowTag.ts` - new: `[[[snow_scene]]]` literal pre-pass and placeholder
- `resources/js/snow/snowPhysics.ts` - new: pure flake model, settings reader, gradient math, crystal generator
- `resources/js/snow/snowScene.ts` - new: three.js mount drawing every flake as one `THREE.Points`
- `resources/js/snow/snow.test.ts` - new: Vitest coverage of the tag and `snowPhysics.ts`
- `resources/js/components/OverlayRenderer.vue` - snow pre-pass, lazy chunk, morphdom guard, teardown
- `app/Services/TemplateDataMapperService.php` - `snow_scene` catalogue entry and empty server value
- `resources/recipes/snow-overlay/manifest.json` - new product manifest with designer block
- `resources/recipes/snow-overlay/snow.md` - new overlay document, nine controls
- `resources/recipes/chat-checkin/manifest.json` - second sentence of `description` replaced
- `resources/js/components/product-icons/SnowOverlay.vue` - new product icon
- `resources/js/components/product-icons/registry.ts` - `snow-overlay` registered
- `resources/js/welcome/videoDemo.ts` - new: start, pause and resume for a recorded demo
- `resources/js/welcome/app.ts` - calls `initVideoDemos()`
- `resources/views/welcome/demos/snow.blade.php` - new: video demo partial
- `resources/views/welcome/products.blade.php` - `snow-overlay` demo mapping; rows show two sentences; intro sentence excepts Snow Overlay
- `resources/views/products/page.blade.php` - `snow-overlay` demo mapping
- `resources/views/products/pitch.blade.php` - `snow-overlay` demo mapping
- `public/products/snow-overlay-hero.jpg` - new hero image
- `public/products/snow-overlay/demo.mp4` - new demo recording
- `public/llms.txt` - product table row for Snow Overlay for OBS
- `tests/Feature/ProductSnowOverlayTest.php` - new
- `tests/Feature/ProductCategoryTest.php` - product counts and slug lists include `snow-overlay`
- `tests/Feature/ProductInstallTest.php` - listed slugs and count include `snow-overlay`
- `tests/Feature/ProductSlugRenameTest.php` - `snow-overlay` added to `$bornHyphenated`
- `tests/Feature/ProductTwitchChatPlinkoTest.php` - shelf index 5 to 6
- `tests/Feature/ProductTwitchChatTest.php` - shelf index 4 to 5, test title

### Claims
- **C1** [code] `replaceSnowTags()` swaps the literal `[[[snow_scene]]]` for `<div class="ol-snow" data-snow-scene></div>`.
- **C2** [code] `OverlayRenderer.vue` imports `@/snow/snowScene` only via dynamic `import()` inside `loadSnowModule()`, reached only when `templateUsesSnow` is true.
- **C3** [code] The morphdom `onBeforeElChildrenUpdated` guard in `OverlayRenderer.vue` skips elements with `data-snow-scene`.
- **C4** [code] `destroySnows()` is called in `OverlayRenderer.vue`'s `onUnmounted`.
- **C5** [code] `TemplateDataMapperService::TAG_CATALOG` has a `snow_scene` entry, and `mapTwitchDataForTemplates()` returns `snow_scene => ''`.
- **C6** [code] `readSnowSettings()` reads `--snow-count`, `--snow-speed`, `--snow-size`, `--snow-shape`, `--snow-color`, `--snow-color-2` and `--snow-angle`, falling back to `DEFAULT_SETTINGS` on a missing or non-numeric value.
- **C7** [code] `stepFlake()` moves a flake by `flake.speed * speedFactor * BASE_FALL * dt`.
- **C8** [code] `snowScene.ts` re-reads the settings every `LOOK_INTERVAL_MS` (500).
- **C9** [code] `snowScene.ts` allocates `MAX_FLAKES` (3000) flakes once and shows `settings.count` of them via `geometry.setDrawRange`.
- **C10** [code] `crystal(seed)` is seeded through `seededRandom`, and `snowScene.ts` draws `CRYSTAL_VARIANTS` (16) crystals into one atlas.
- **C11** [test] `snow.test.ts` asserts each flake keeps its own speed under a speed factor, that `gradientT()` matches the Chat Emote Bubbles orientation at 0, 90, 180 and 270, and that crystal branches come in mirrored pairs.
- **C12** [code] `snow.md` binds each of the seven `--snow-*` properties on `.ol-snow` to a `[[[c:*]]]` control.
- **C13** [code] `snow.md` declares nine controls; `c:gradient_angle` carries `angle_colors=["flake_color","flake_color_2"]` and `c:background_color` carries `enabled_if=["c:background = color"]`.
- **C14** [test] `ProductSnowOverlayTest` asserts the install creates exactly nine controls on one static overlay and no integration or option set.
- **C15** [code] The `snow-overlay` manifest designer declares five presets, and their `shape` values cover `round`, `square` and `snowflake`.
- **C16** [code] `snow.blade.php` renders a `<video>` with `preload="none"`, `muted`, `loop`, `playsinline` and the hero as `poster`.
- **C17** [code] `initVideoDemos()` starts the video on a `[data-video-demo-start]` click, or on first intersection when `data-video-demo-autostart="1"`, and pauses it while off screen once started.
- **C18** [test] `ProductSnowOverlayTest` asserts the pitch page carries `data-video-demo-autostart="1"` and the homepage `data-video-demo-autostart="0"`.
- **C19** [code] `$firstSentences` in `welcome/products.blade.php` returns the text up to the second `. `, or the whole text when it has fewer.
- **C20** [test] `ProductSnowOverlayTest` asserts `public/products/snow-overlay-hero.jpg` exists and contains no `c2pa`.
- **C21** [unverified] The snowfall, all five presets and the video demo were seen working in Firefox on `overlabels.test`.

### Unchanged
- `ProductDemo` and `_overlay-demo.blade.php`, the live overlay demo Tower and Bowling use, are not in the diff: the snow demo is a separate partial.
- `$command()` in `welcome/products.blade.php`, which picks the `!command` chip from the same description, is not in the diff.

### Risk
Every product row on the homepage now shows two sentences of its description, so the second sentence
of every listed product's manifest is newly on the homepage.
