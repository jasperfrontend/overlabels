## OL-2611-047 - feat(checkin): hexagon globe, mixed land tones, bold text and sample checkins in the designer

**Shipped:** 2026-11-28
**Commit:** `git log --grep=OL-2611-047`

### Surface
- `resources/js/globe/landmask.ts` - `EVEN_DOT_COUNT` and `evenLandDots()` (Fibonacci-sphere sampling of the land mask) added
- `resources/js/globe/checkinGlobe.ts` - land as instanced flat hexagons, smaller core, per-tile tones, three pin heights, cylinder stalks, name fade around the spin axis, pins hidden on the far side
- `resources/js/utils/checkinSample.ts` - new file, invented checkins for the designer preview
- `resources/js/utils/checkinSample.test.ts` - new file
- `resources/js/components/OverlayRenderer.vue` - `handleCheckinSample()` and the `checkin-sample` ready message
- `resources/js/pages/products/design.vue` - the sample-checkins bar and its frame handshake
- `app/Support/ProductDesigner.php` - `sample_checkins` added to `EXTRAS`
- `resources/recipes/recipe-manifest.schema.json` - `sample_checkins` added to the extras enum and description
- `resources/recipes/chat-checkin/globe.md` - `land_tones` and `font_bold` controls, the CSS that reads them, the label outline, the dot size description
- `resources/recipes/chat-checkin/manifest.json` - both keys in the designer groups and every preset, `extras: ["sample_checkins"]`
- `database/migrations/2026_11_27_120000_add_land_tones_and_bold_text_to_installed_chat_checkin_globes.php` - new file, upgrades existing installs
- `tests/Feature/ChatCheckinLandTonesUpgradeTest.php` - new file
- `tests/Feature/ChatCheckinDesignerBackfillTest.php` - asserts the 2026-11-16 backfill chains into the new migration
- `tests/Feature/ProductInstallTest.php` - install key list, hero c2pa test
- `public/products/chat-checkin-hero.jpg` - replaced with the new 1280x720 hero

### Claims
- **C1** [code] `landmask.ts` exports `EVEN_DOT_COUNT = 10000` and `evenLandDots()`, which keeps the Fibonacci-sphere points whose 2 degree mask cell `isLandCell()` reports as land.
- **C2** [code] `checkinGlobe.ts` no longer contains `dotTexture()` or a `THREE.PointsMaterial`.
- **C3** [code] The land is two `THREE.InstancedMesh` of one `CircleGeometry(1, 6)`: `frontHexes` with `THREE.FrontSide`, and `backHexes` with `THREE.BackSide`, `transparent`, `opacity: FAR_SIDE_OPACITY`.
- **C4** [code] `backHexes.instanceMatrix` is assigned `frontHexes.instanceMatrix`, so both draw the same tiles.
- **C5** [code] `FAR_SIDE_OPACITY` is 0.3.
- **C6** [code] The core sphere's radius is `SPHERE_RADIUS * CORE_RADIUS`, and `CORE_RADIUS` is 0.6.
- **C7** [code] `layoutHexes()` sets a tile's radius to `(share * TILE_FILL * spacing) / 2`, where `share` is `--globe-dot-size` clamped to 0..`MAX_DOT_SIZE` (10) over `MAX_DOT_SIZE`, `TILE_FILL` is 0.9 and `spacing` is `SPHERE_RADIUS * sqrt(4 * PI / EVEN_DOT_COUNT)`.
- **C8** [code] `readStyle()` sets `dotTones` true only when `--globe-dot-tones` parses as the number 1.
- **C9** [code] `colorHexes()` gives each tile tone 0, 1 or 2 from an integer hash of its instance index, not from `Math.random()`.
- **C10** [code] Tones 1 and 2 are the land colour's HSL lightness plus and minus `TONE_STEP` (0.12), read and written with `THREE.SRGBColorSpace`; with `dotTones` false the step is 0.
- **C11** [code] `pinTipRadius(login)` returns one of `PIN_TIP_RADII` (`[1.08, 1.16, 1.26]`), chosen by an FNV-1a hash of the login.
- **C12** [code] A pin's stalk is a `THREE.Mesh` of `CylinderGeometry(STALK_RADIUS, STALK_RADIUS, 1, 6)` scaled on y to `tipRadius - SPHERE_RADIUS`; `STALK_RADIUS` is 0.002.
- **C13** [code] `placeLabels()` sets every part in `PinObject.parts` (stalk and head) visible only while the pin's world `z` is above 0.05.
- **C14** [code] `placeLabels()` sets a name's opacity to `smoothstep(turn, NAME_FADE_FROM, NAME_FADE_TO)` (0.4, 0.6), where `turn` is `z / hypot(x, z)` of the anchor in `spin.matrix` space, so latitude does not enter it.
- **C15** [code] `globe.md` declares `land_tones` and `font_bold`, both `boolean` with default `0`.
- **C16** [code] `globe.md`'s CSS maps `--globe-dot-tones: var(--land-tones)` on `.ol-checkin-globe`.
- **C17** [code] `globe.md`'s CSS gives `.globe-stage` `font-weight: calc(400 + 300 * var(--bold))`, with `--bold` read from `c:font_bold`.
- **C18** [code] `globe.md`'s `.ol-globe-label` `text-shadow` is eight 1px offsets with no blur, in place of `0 0 6px #000`.
- **C19** [code] `chat-checkin/manifest.json` puts `land_tones` in the Globe group after `land_dot_size` and `font_bold` in the Text group after `font`.
- **C20** [code] Every preset in `chat-checkin/manifest.json` sets `land_tones` and `font_bold` to `"0"`.
- **C21** [code] `chat-checkin/manifest.json` declares `"extras": ["sample_checkins"]`.
- **C22** [code] `ProductDesigner::EXTRAS` and the schema's extras enum both contain `sample_checkins`.
- **C23** [code] `sampleCheckinPins(count, now)` returns the first `count` of `SAMPLE_CHECKINS` (clamped to 0..`SAMPLE_CHECKINS_MAX`), newest first.
- **C24** [test] `checkinSample.test.ts` asserts the clamp, that a larger count contains a smaller count's logins, newest-first order, that every pin survives `toPin()` with exactly `PIN_FIELDS`, and unique logins.
- **C25** [code] `OverlayRenderer.vue` registers `handleCheckinSample` only when `props.sample` is set.
- **C26** [code] `handleCheckinSample()` ignores any message whose `event.source` is not `window.parent` or whose `ol` is not `checkin-sample`.
- **C27** [code] `handleCheckinSample()` caps the count at `checkinsWindow` and writes the pins through `withCheckinSlots()`.
- **C28** [code] On a count of 0 or less, `handleCheckinSample()` writes back the pins and count it stored before its first sample.
- **C29** [code] `handleCheckinSample()` calls `syncGlobes()` when `templateUsesGlobe` is true.
- **C30** [code] In sample mode, after the render payload sets `checkinsWindow`, the renderer posts `{ ol: 'checkin-sample', ready: true }` to its parent.
- **C31** [code] `design.vue` renders the sample-checkins bar only when `has('sample_checkins')`.
- **C32** [code] `design.vue` answers a `checkin-sample` ready message by posting the current count when that count is above 0.
- **C33** [code] The migration upgrades an install only when its trimmed CSS equals `OLD_CSS`; otherwise it returns `css edited` and logs a warning.
- **C34** [code] An upgraded install gets the `land_tones` and `font_bold` rows with value `'0'`, `NEW_CSS`, and `c:land_tones` and `c:font_bold` in `template_tags`, in one transaction.
- **C35** [code] An install that already has a `land_tones` control is reported `done` and not changed.
- **C36** [code] `describeSize()` replaces the `land_dot_size` description only where it equals `SIZE_DESCRIPTION_FROM`, and runs whether or not the CSS was upgraded.
- **C37** [test] `ChatCheckinLandTonesUpgradeTest` asserts `NEW_CSS` equals a fresh install's CSS.
- **C38** [test] `ChatCheckinLandTonesUpgradeTest` asserts an upgraded install's `land_tones`, `font_bold` and `land_dot_size` rows match a fresh install's label, type, config and description.
- **C39** [test] `ChatCheckinLandTonesUpgradeTest` asserts an edited-CSS install keeps its CSS and gets no `land_tones` row.
- **C40** [test] The same test asserts that edited-CSS install still gets the new size description.
- **C41** [test] `ChatCheckinLandTonesUpgradeTest` asserts a description the streamer wrote is kept.
- **C42** [test] `ChatCheckinLandTonesUpgradeTest` asserts an already-upgraded install is left as it is.
- **C43** [test] `ChatCheckinDesignerBackfillTest` asserts the 2026-11-16 backfill's `NEW_CSS` equals this migration's `OLD_CSS`.
- **C44** [test] `ChatCheckinDesignerBackfillTest` asserts that running both migrations in order leaves a pre-designer install with a fresh install's CSS.
- **C45** [unverified] With the upgrade step disabled, the three behaviour tests across the two migration test files failed and the guard tests passed; this was run against the land-tones-only version of the migration, before `font_bold` was added.
- **C46** [test] `ProductInstallTest` asserts a fresh install's control keys include `land_tones` and `font_bold`.
- **C47** [test] `ProductInstallTest` asserts `public/products/chat-checkin-hero.jpg` exists and contains no `c2pa`.

### Unchanged
- `handleCheckinsUpdated()` in `OverlayRenderer.vue` writes real checkins into the same `checkins.*` slots that `handleCheckinSample()` writes, but does not call `syncGlobes()`; it is not in the diff.
- `landDots()` and `latLngToUnitVector()` in `landmask.ts` are still exported and unchanged; the globe now reads `evenLandDots()` instead of `landDots()`.
- The 2026-11-16 backfill migration file is not in the diff; only its test is.

### Risk
Existing installs keep their `land_dot_size` value, but it now sizes tiles as a share of the gap
between them instead of pixels, so tiles look larger at the same setting. Installs whose CSS was
edited are skipped by the migration and logged, and keep the old CSS without the two new switches.
A real checkin may not appear on a globe whose HUD and pin list are both off until the browser
source reloads (see Unchanged, first line); observed in the designer through the sample path, not
reproduced with a real checkin.
