## Audit of OL-2611-043 - feat(products): Snow Overlay for OBS

**Audited:** 2026-11-27
**Commit:** 31dec75bd0366cf486ee0d3c834c844285e7b039 (the only commit carrying the trailer; it is also HEAD, so every @31dec75b reading below holds @HEAD)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/snow/snowTag.ts:15,19,26-27 @31dec75b` - `PLACEHOLDER = '<div class="ol-snow" data-snow-scene></div>'`, `replaceSnowTags()` is `source.split(SNOW_TAG).join(PLACEHOLDER)` with `SNOW_TAG = '[[[snow_scene]]]'` |
| C2 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:69 @31dec75b` is `import type` (erased); `:861` is the only runtime `import('@/snow/snowScene')`, inside `loadSnowModule()`; `loadSnowModule()` is called only from `syncSnows()` (`:876`), which is called at `:724` under `if (templateUsesSnow.value)` and at `:864` from the load's own `.then` |
| C3 | CONFIRMED | `OverlayRenderer.vue:713-714 @31dec75b` - `onBeforeElChildrenUpdated` returns false when `fromEl.hasAttribute('data-snow-scene')` |
| C4 | CONFIRMED | `OverlayRenderer.vue:1509,1518 @31dec75b` - `destroySnows()` inside `onUnmounted(() => {...})` |
| C5 | CONFIRMED | `app/Services/TemplateDataMapperService.php:254 @31dec75b` - `TAG_CATALOG['snow_scene']`; `:338` `'snow_scene' => ''` in `mapTwitchDataForTemplates()`; also asserted by `ProductSnowOverlayTest` "lists the snow tag in the tag catalogue" (passed) |
| C6 | CONTRADICTED (compound) | Reads the seven properties: CONFIRMED, `resources/js/snow/snowPhysics.ts:127-136 @31dec75b`. Falls back to `DEFAULT_SETTINGS` when missing: CONTRADICTED for `--snow-color-2` only - `:135` is `prop('--snow-color-2') \|\| prop('--snow-color') \|\| DEFAULT_SETTINGS.color2`, so a missing second colour takes `--snow-color` when that is set, and `snow.test.ts:48-50` pins exactly that. The other six fall back to defaults (`speed`/`size`/`angle` via literals `100`, `100`, `0`, equal to `DEFAULT_SETTINGS` after `/100`) |
| C7 | CONFIRMED | `snowPhysics.ts:179 @31dec75b` - `flake.y += flake.speed * speedFactor * BASE_FALL * dt` |
| C8 | CONFIRMED | `resources/js/snow/snowScene.ts:45 @31dec75b` `LOOK_INTERVAL_MS = 500`; `:279-282` calls `applyLook()` (which calls `readSnowSettings`) when `now - lastLook >= LOOK_INTERVAL_MS` |
| C9 | CONFIRMED | `snowScene.ts:197 @31dec75b` allocates `MAX_FLAKES` flakes once at mount; `snowPhysics.ts:65` `MAX_FLAKES = 3000`; `snowScene.ts:253` `geometry.setDrawRange(0, settings.count)` |
| C10 | CONFIRMED | `snowPhysics.ts:249 @31dec75b` `crystal()` uses `seededRandom(seed * 7919 + 17)`; `:68` `CRYSTAL_VARIANTS = 16`; `snowScene.ts:55-96` draws all `CRYSTAL_VARIANTS` into one canvas, one `CanvasTexture` |
| C11 | CONFIRMED | `npx vitest run resources/js/snow/snow.test.ts` - 15 passed. `snow.test.ts:54-65` per-flake speed under factor 0.5; `:108-118` gradientT at 0, 90, 180, 270; `:133-141` branches in mirrored pairs |
| C12 | CONFIRMED | `resources/recipes/snow-overlay/snow.md:57-68 @31dec75b` - all seven `--snow-*` on `.ol-snow` bound to `[[[c:*]]]`; also asserted by `ProductSnowOverlayTest` "binds every snow setting" (passed) |
| C13 | CONFIRMED | `snow.md:81-89 @31dec75b` nine control rows; `:100` `angle_colors=["flake_color","flake_color_2"]` on `c:gradient_angle`; `:108` `enabled_if=["c:background = color"]` on `c:background_color` |
| C14 | CONFIRMED | `tests/Feature/ProductSnowOverlayTest.php:58-73 @31dec75b` - template `type` static, 9 `OverlayControl` rows on it, 0 `ExternalIntegration`, 0 `OptionSet`; `php artisan test --filter=ProductSnowOverlayTest` 10 passed (44 assertions) |
| C15 | CONFIRMED | `resources/recipes/snow-overlay/manifest.json:138-218 @31dec75b` - five presets (gentle, crystals, blizzard, pixel, night), shapes round, snowflake, round, square, snowflake |
| C16 | CONFIRMED | `resources/views/welcome/demos/snow.blade.php:20-29 @31dec75b` - `<video ... poster="/products/snow-overlay-hero.jpg" preload="none" muted loop playsinline>` |
| C17 | CONFIRMED | `resources/js/welcome/videoDemo.ts:17-38 @31dec75b` - `start()` on `[data-video-demo-start]` click or on first intersection when `dataset.videoDemoAutostart === '1'`; after start an `IntersectionObserver` pauses when not intersecting |
| C18 | CONFIRMED | `ProductSnowOverlayTest.php:145-159 @31dec75b` - `route('products.show', 'snow-overlay')` (`routes/web.php:338-339`, `ProductController::pitch`) sees `data-video-demo-autostart="1"`, `/` sees `data-video-demo-autostart="0"`; passed |
| C19 | CONFIRMED | `resources/views/welcome/products.blade.php:18-29 @31dec75b` - loop of 2 `strpos($text, '. ', ...)`, `return $text` on the first miss, else `substr($text, 0, $end + 1)` |
| C20 | CONFIRMED | `ProductSnowOverlayTest.php:127-133 @31dec75b` - `is_file()` and `not->toContain('c2pa')` on `public/products/snow-overlay-hero.jpg`; passed |
| C21 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Unchanged
- `ProductDemo` and `_overlay-demo.blade.php`: neither path is in `git show --stat 31dec75b`. Confirmed.
- `$command()` in `welcome/products.blade.php`: no hunk in the diff touches it (used at `:66 @31dec75b`, outside both changed hunks). Confirmed.

### Findings
- **F1** claim inaccurate - C6 says every property falls back to `DEFAULT_SETTINGS` when missing, but `resources/js/snow/snowPhysics.ts:135 @31dec75b` makes a missing `--snow-color-2` take `--snow-color` first (pinned by `snow.test.ts:48-50`); the code is consistent with its own test, so a later claim should restate C6 with that exception.

### Notes
- The C11 gradient test hard-codes the expected orientation (`snow.test.ts:108-118`) and does not read the Chat Emote Bubbles source, unlike `angleKnob.test.ts` per CLAUDE.md; a change to the Bubbles convention would not fail it.
- CLAUDE.md "Live product demos" says a product has a live demo exactly when its designer declares scenes; Snow declares none and uses a recorded video through the existing `$demos` slug mapping instead. Not a reversal of that rule (no CSS imitation returns), recorded for whoever next touches the demo mechanism.
- No em dashes in any added line of the diff (`grep -c` on `+` lines: 0).
