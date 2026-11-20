## OL-2611-029 - feat(products): a color designer for Plinko, Chat Tower and Follower Bowling

**Shipped:** 2026-11-20
**Commit:** `git log --grep=OL-2611-029`

### Surface
- `resources/recipes/twitch-chat-plinko/plinko.md` - `:root` palette reads five color controls; Controls section and detail added
- `resources/recipes/chat-tower/tower.md` - `:root` palette reads ten color controls; `--green` renamed `--record`; ten rows and details added
- `resources/recipes/follower-bowling/lane.md` - `:root` palette reads six color controls; six rows and details added
- `resources/recipes/twitch-chat-plinko/manifest.json` - `designer` block: one group, three presets; `description` says "or any command you choose yourself" instead of "or the word your streamer picked"
- `resources/recipes/chat-tower/manifest.json` - `designer` block: three groups, three presets
- `resources/recipes/follower-bowling/manifest.json` - `designer` block: one group, three presets
- `resources/js/plinko/plinkoBoard.ts` - colours and font re-read every `LOOK_INTERVAL_MS`
- `database/migrations/2026_11_20_120000_give_installed_fullscreen_products_their_palette_controls.php` - new: upgrades unedited existing installs
- `tests/Feature/PaletteControlsMigrationTest.php` - new file
- `tests/Feature/fixtures/palette-migration/plinko.css`, `tower.css`, `lane.css` - new: each product's stylesheet as shipped before this change
- `tests/Feature/ProductTwitchChatPlinkoTest.php` - expects five color controls instead of none
- `tests/Feature/ProductTowerTest.php` - expects 20 controls, 10 of them color
- `tests/Feature/ProductBowlingTest.php` - expects 26 controls, 6 of them color
- `tests/Feature/ProductChatPresetsTest.php` - "no designer" case moved from `chat-tower` to `ko-fi-alerts`
- `tests/Feature/FollowerBowlingRedesignMigrationTest.php` - tag comparison ignores `*_color` tags
- `CLAUDE.md` - Plinko section: colours re-read live, the designer exists

### Claims
- **C1** [code] `plinko.md`'s `:root` sets `--ink`, `--ink-soft`, `--ink-muted`, `--highlight` and `--field-deep` from `[[[c:text_color]]]`, `[[[c:soft_text_color]]]`, `[[[c:muted_color]]]`, `[[[c:highlight_color]]]` and `[[[c:background_color]]]`, and no longer declares `--field`.
- **C2** [code] `tower.md`'s `:root` sets `--ink`, `--ink-soft`, `--ink-muted`, `--highlight`, `--warning`, `--danger`, `--flash`, `--record`, `--field` and `--field-deep` from `[[[c:*_color]]]` tags, and the file contains no `--green`.
- **C3** [code] `lane.md`'s `:root` sets `--ink`, `--ink-soft`, `--ink-muted`, `--highlight`, `--field` and `--field-deep` from `[[[c:*_color]]]` tags.
- **C4** [code] Every color control's default in the three documents equals the literal its variable held before this change (Twilight).
- **C5** [code] Each of the three manifests declares a `designer` block whose presets are `twilight`, `nordic` and `moss`, with identical color values per key across the three manifests.
- **C6** [code] Chat Tower's designer groups `show_hint` under "How to play", and every Tower preset sets it to `"1"`.
- **C7** [test] `RecipeManifestValidator` accepts all three designer blocks (the catalogue tests that validate every manifest pass).
- **C8** [code] `mountPlinkoBoard()` copies `pegColor`, `accent`, `ballColor`, `labelBg`, `labelColor`, `slotColor` and `font` from `readConfig()` into `config` at most every `LOOK_INTERVAL_MS` (500); `rows`, `maxBalls` and `rate` are only read at mount.
- **C9** [code] The migration upgrades an install only when sha256 of its overlay's `html` equals the frozen `html_sha256` AND sha256 of its `css` is in `old_css_sha256`; otherwise it logs a warning with the `recipe_instance_id` and changes nothing.
- **C10** [code] An upgrade replaces `css`, merges the color tags into `template_tags`, and inserts one `color` control per frozen entry, skipping a key the overlay already has, inside one transaction.
- **C11** [code] The migration references no Eloquent model; every value it writes is a constant in the file.
- **C12** [test] `PaletteControlsMigrationTest` asserts, for each of the three products: an install carrying the fixture stylesheet is upgraded to the frozen CSS with the frozen controls; a stylesheet or markup edit leaves the install untouched; a fresh install is untouched and two runs add nothing twice.
- **C13** [test] `PaletteControlsMigrationTest` asserts an existing `highlight_color` control keeps its value and label through the migration.
- **C14** [unverified] With the old-CSS check in the migration replaced by `true`, 7 of the 13 `PaletteControlsMigrationTest` cases failed.
- **C15** [unverified] At commit time, a one-off test (not kept) confirmed a fresh install's CSS, color controls (key, label, description, value, sort_order) and color tags equal the migration's frozen values for all three products.
- **C16** [unverified] The fixture CSS files hash to entries of `old_css_sha256`: they were extracted from commits `b208a132` (Plinko), `04f5e79c` (Tower) and `a2fb38d3` (Bowling).

### Unchanged
- `ProductDesigner`, `products/design.vue` and the saved-looks table carry the designer for these products as declared data; none of them is in the diff.
- The 2026-11-16 bowling migration's frozen constants are untouched; only its test stopped comparing tags this change adds.
- The Plinko board's rows, ball cap and release rate (`--plinko-rows`, `--plinko-max-balls`, `--plinko-rate`) are still read once at mount and have no controls.

### Risk
`php artisan migrate` on deploy rewrites the CSS of every unedited install of the three products. The
colors render the same until a streamer changes one. Hand-edited installs get no designer until
reinstalled. The designer pages and the live Plinko repaint were not exercised in a browser before
this commit.
