## Audit of OL-2611-047 - feat(checkin): hexagon globe, mixed land tones, bold text and sample checkins in the designer

**Audited:** 2026-11-28
**Commit:** 7b8ead95a166ad37da2255271c63e11b47d4850f (HEAD at audit time; `git log 7b8ead95..HEAD` is empty, so @7b8ead95 and @HEAD are the same tree)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/globe/landmask.ts:91 @7b8ead95` - `EVEN_DOT_COUNT = 10000`; `:98-111` `evenLandDots()` keeps Fibonacci points where `isLandCell(row, col)`, mask is `MASK_COLS = 180`, `MASK_ROWS = 90` (`:16-17`), i.e. 2 degree cells |
| C2 | CONFIRMED | `grep dotTexture\|PointsMaterial` on `resources/js/globe/checkinGlobe.ts @7b8ead95` returns nothing |
| C3 | CONFIRMED | `checkinGlobe.ts @7b8ead95` - `hexGeometry = new THREE.CircleGeometry(1, 6)`; `frontHexes` InstancedMesh with `side: THREE.FrontSide`; `backHexes` with `side: THREE.BackSide, transparent: true, opacity: FAR_SIDE_OPACITY` |
| C4 | CONFIRMED | `checkinGlobe.ts @7b8ead95` - `backHexes.instanceMatrix = frontHexes.instanceMatrix;` |
| C5 | CONFIRMED | `checkinGlobe.ts @7b8ead95` - `const FAR_SIDE_OPACITY = 0.3;` |
| C6 | CONFIRMED | `checkinGlobe.ts @7b8ead95` - `CORE_RADIUS = 0.6`; shell is `new THREE.SphereGeometry(SPHERE_RADIUS * CORE_RADIUS, 48, 32)` |
| C7 | CONFIRMED | `checkinGlobe.ts @7b8ead95` `layoutHexes()` - `share = Math.min(Math.max(style.dotSize, 0), MAX_DOT_SIZE) / MAX_DOT_SIZE`, `radius = (share * TILE_FILL * spacing) / 2`; `MAX_DOT_SIZE = 10`, `TILE_FILL = 0.9`, `spacing = SPHERE_RADIUS * Math.sqrt((4 * Math.PI) / EVEN_DOT_COUNT)` |
| C8 | CONFIRMED | `checkinGlobe.ts:132 @7b8ead95` - `dotTones: cssNumber(styles, '--globe-dot-tones', 0) === 1` |
| C9 | CONFIRMED | `checkinGlobe.ts @7b8ead95` - `tones` computed by `Math.imul`/xor-shift hash of index `i`, `% 3`; no `Math.random()` in it |
| C10 | CONFIRMED | `checkinGlobe.ts @7b8ead95` `colorHexes()` - `getHSL(hsl, THREE.SRGBColorSpace)`, `setHSL(..., l + step / l - step, THREE.SRGBColorSpace)`, `step = style.dotTones ? TONE_STEP : 0`, `TONE_STEP = 0.12` |
| C11 | CONFIRMED | `checkinGlobe.ts @7b8ead95` - `PIN_TIP_RADII = [1.08, 1.16, 1.26]`; `pinTipRadius()` FNV-1a (`0x811c9dc5`, `0x01000193`) mod length |
| C12 | CONFIRMED | `checkinGlobe.ts @7b8ead95` - `CylinderGeometry(STALK_RADIUS, STALK_RADIUS, 1, 6)`, `stalk.scale.set(1, tipRadius - SPHERE_RADIUS, 1)`, `STALK_RADIUS = 0.002`; stalk is `new THREE.Mesh` |
| C13 | CONFIRMED | `checkinGlobe.ts:378 @7b8ead95` - `facing = worldPos.z > 0.05` (anchor in `spin.matrixWorld`); `:402` `for (const part of obj.parts) part.visible = facing`; `parts: [stalk, head]` |
| C14 | CONFIRMED | `checkinGlobe.ts @7b8ead95` - `spinPos.copy(obj.anchor).applyMatrix4(spin.matrix)`, `turn = spinPos.z / Math.hypot(spinPos.x, spinPos.z)` (1 if degenerate), `smoothstep(turn, NAME_FADE_FROM, NAME_FADE_TO)` with 0.4 / 0.6 |
| C15 | CONFIRMED | `resources/recipes/chat-checkin/globe.md @7b8ead95` controls table - `c:land_tones` boolean default `0`, `c:font_bold` boolean default `0` |
| C16 | CONFIRMED | `globe.md @7b8ead95` - `.ol-checkin-globe { ... --globe-dot-tones: var(--land-tones); }` |
| C17 | CONFIRMED | `globe.md @7b8ead95` - `--bold: [[[c:font_bold]]];` in `:root`, `.globe-stage { ... font-weight: calc(400 + 300 * var(--bold)); }` |
| C18 | CONFIRMED | `globe.md @7b8ead95` - `.ol-globe-label` text-shadow is eight `±1px` offsets with `0` blur; old `0 0 6px #000` removed in the diff |
| C19 | CONFIRMED | `resources/recipes/chat-checkin/manifest.json @7b8ead95` - Globe keys `land_dot_size, land_tones, ...`; Text keys `font, font_bold, ...` |
| C20 | CONFIRMED | `manifest.json @7b8ead95` - all seven presets in the diff gain `"land_tones": "0"` and `"font_bold": "0"` |
| C21 | CONFIRMED | `manifest.json @7b8ead95` - `"extras": ["sample_checkins"]` |
| C22 | CONFIRMED | `app/Support/ProductDesigner.php:50 @7b8ead95` - `EXTRAS = [..., 'sample_checkins']`; `recipe-manifest.schema.json:467 @7b8ead95` enum includes `sample_checkins` |
| C23 | CONFIRMED | `resources/js/utils/checkinSample.ts @7b8ead95` `sampleCheckinPins()` - `n = max(0, min(floor(count) \|\| 0, SAMPLE_CHECKINS.length))`, `slice(0, n)`, `at` ascending by index then `.reverse()` |
| C24 | CONFIRMED | `resources/js/utils/checkinSample.test.ts @7b8ead95` asserts clamp (0, -3, 12, MAX+20), `arrayContaining` of 5 within 20, descending `at`, `toPin(pin)` equals pin and keys equal `PIN_FIELDS`, unique logins. `npx vitest run resources/js/utils/checkinSample.test.ts`: 4 passed |
| C25 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:1280-1283 @7b8ead95` - `addEventListener('message', handleCheckinSample)` inside `if (props.sample)` |
| C26 | CONFIRMED | `OverlayRenderer.vue:1767-1770 @7b8ead95` - returns on `event.source !== window.parent`, on `payload.ol !== 'checkin-sample'` (and on a non-number `count`) |
| C27 | CONFIRMED | `OverlayRenderer.vue @7b8ead95` - `sampleCheckinPins(Math.min(payload.count, checkinsWindow.value), ...)`, `withCheckinSlots(data.value, pins, pins.length)` |
| C28 | CONFIRMED | `OverlayRenderer.vue @7b8ead95` - `pinsBeforeSample` stored on first message; `payload.count <= 0` writes `withCheckinSlots(data.value, pinsBeforeSample.pins, pinsBeforeSample.count)` |
| C29 | CONFIRMED | `OverlayRenderer.vue:1789 @7b8ead95` - `if (templateUsesGlobe.value) syncGlobes();` |
| C30 | CONFIRMED | `OverlayRenderer.vue:1413-1420 @7b8ead95` - after `checkinsWindow.value = clampCheckinsWindow(...)`, posts `{ ol: 'checkin-sample', ready: true }` when `props.sample && window.parent !== window` |
| C31 | CONFIRMED | `resources/js/pages/products/design.vue:1108 @7b8ead95` - bar `v-if="has('sample_checkins')"` |
| C32 | CONFIRMED | `design.vue:756-757 @7b8ead95` - on `ol === 'checkin-sample'`, `if (payload.ready && has('sample_checkins') && samplePins.value > 0) postCheckinSample(samplePins.value)` |
| C33 | CONFIRMED | `database/migrations/2026_11_27_120000_add_land_tones_and_bold_text_to_installed_chat_checkin_globes.php @7b8ead95` `upgrade()` - CRLF-normalised `trim(css) !== self::OLD_CSS` returns `'css edited'`; `up()` logs each skipped reason with `Log::warning` |
| C34 | CONFIRMED | same file `upgrade()` - inserts both `CONTROLS` rows (`value '0'`) and updates `css => NEW_CSS`, `template_tags` with `c:land_tones`, `c:font_bold` inside one `DB::transaction` |
| C35 | CONFIRMED | same file - `if ($controls->contains('key', 'land_tones')) return 'done';` before any write |
| C36 | CONFIRMED | same file - `describeSize()` updates only `where('description', SIZE_DESCRIPTION_FROM)`, and `up()` calls it before `upgrade()` regardless of outcome |
| C37 | CONFIRMED | `tests/Feature/ChatCheckinLandTonesUpgradeTest.php @7b8ead95` "ships exactly the CSS a fresh install gets". `php artisan test --filter=ChatCheckinLandTonesUpgradeTest`: 5 passed (17 assertions) |
| C38 | CONFIRMED | same test "gives an old install both switches..." compares `label, type, config, description` for `land_tones`, `font_bold`, `land_dot_size` against a fresh install; passed |
| C39 | CONFIRMED | same test "leaves an install whose CSS was edited..." asserts CSS unchanged and no `land_tones` row; passed |
| C40 | CONFIRMED | same test asserts `land_dot_size` description equals `SIZE_DESCRIPTION_TO`; passed |
| C41 | CONFIRMED | same file "keeps a size description the streamer wrote" (`'Mine.'`); passed |
| C42 | CONFIRMED | same file "does nothing to an install that already has the switch" (count, CSS, value `'1'` kept); passed |
| C43 | CONFIRMED | `tests/Feature/ChatCheckinDesignerBackfillTest.php @7b8ead95` - `expect(checkinBackfillConstant('NEW_CSS'))->toBe($nextOldCss)`. `php artisan test --filter=ChatCheckinDesignerBackfillTest`: 5 passed (45 assertions) |
| C44 | CONFIRMED | same test runs `checkinBackfill()->up()` then `checkinLandTonesUpgrade()->up()` and asserts `css` equals `$freshCss`; passed |
| C45 | UNVERIFIABLE | tagged [unverified]; fail-first run against a tree that no longer exists |
| C46 | CONFIRMED | `tests/Feature/ProductInstallTest.php @7b8ead95` install key list includes `font_bold` and `land_tones`. `php artisan test --filter=ProductInstallTest`: 21 passed (203 assertions) |
| C47 | CONFIRMED | `ProductInstallTest.php @7b8ead95` "ships the chat-checkin hero without a c2pa manifest" - `is_file` true and `not->toContain('c2pa')`; passed |

### Surface
Complete. Every path in `git show --stat 7b8ead95` is listed except the claim file and `docs/changelog/changelog-2026-11.md`, both exempt; no phantom paths.

### Findings
- **F1** scope - `resources/js/pages/products/design.vue:1206 @7b8ead95` adds a sentence to the preview caption ("Sample checkins are invented viewers and only show here, never on stream."), outside the bar and the handshake that Surface names, and no claim covers it. A later claim should disclose it. Its "never on stream" promise rests on C25.
- **F2** contradiction with the record - OL-2609-134 C5 records that a far-side label gets inline `style.opacity = '0'` and has it cleared to `''` when it faces the viewer, and its Unchanged line leaves "whether template CSS should own far-side label visibility" open for a decision. `checkinGlobe.ts @7b8ead95` `placeLabels()` now writes `obj.label.style.opacity = nameAlpha.toFixed(2)` on every label every frame and never clears it. Template CSS therefore cannot set any label's opacity without `!important`, not only far-side ones. C14 describes the new formula but does not cite OL-2609-134. A later claim should restate that line of the record and cite it.

### Notes
- C3, C6 and C13 replace what OL-2609-068 C5/C6 recorded (shell `SPHERE_RADIUS * 0.98`, and the shell occluding far-side dots and pins at every opacity). The far side is now meant to show through the gap, and pins are hidden by `visible = facing`. That is disclosed here, but OL-2609-068 is not cited.
- C2 supersedes OL-2611-018 C11 (land `THREE.Points` `renderOrder = 1`): `backHexes.renderOrder = 1` replaces it.
- Unchanged lines verified: `handleCheckinsUpdated()` (`OverlayRenderer.vue:1679 @7b8ead95`) is outside the diff hunks and calls no `syncGlobes()`. `landDots()`/`latLngToUnitVector()` hunks are untouched. The 2026-11-16 backfill migration is not in `--stat`.
- `public/products/chat-checkin-hero.jpg @7b8ead95` is 1280 wide, per `getimagesize`.
