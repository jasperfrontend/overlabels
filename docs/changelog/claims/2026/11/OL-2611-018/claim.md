## OL-2611-018 - feat(checkin): a designer for the globe, with seven looks and your location

**Shipped:** not yet pushed (committed 2026-11-16)
**Commit:** `git log --grep=OL-2611-018`

### Surface
- `resources/recipes/chat-checkin/globe.md` - thirteen new controls (twelve look keys and `hometown`), CSS reading them through custom properties, HUD names the location
- `resources/recipes/chat-checkin/manifest.json` - `designer` block (four groups, seven presets, one stage) and copy that pointed at the settings page or the Controls tab
- `app/Support/ProductDesigner.php` - `lookKeys()`, `personal` flag on `groups()`, `currentValues()` and `forProduct()` read look keys
- `app/Services/Recipes/RecipeManifestValidator.php` - personal groups excluded from preset completeness and forbidden in presets
- `resources/recipes/recipe-manifest.schema.json` - optional `personal` boolean on a designer group
- `resources/js/pages/products/design.vue` - hint line under a personal group, three copy changes on the shared designer page
- `resources/js/globe/checkinGlobe.ts` - styles re-read every 500 ms and applied in place, land dots drawn after the shell, label rebuild skipped for unchanged pins, inline position set only when the placeholder is static
- `app/Http/Controllers/Api/Internal/BotCheckinController.php` - `homeFor()`: distance measured from the installed globe's `hometown` control, settings home as fallback
- `app/Http/Controllers/Settings/CheckinIntegrationController.php` - `home_in_designer` prop, home settings left untouched while the product is installed
- `resources/js/pages/settings/integrations/checkin.vue` - home field replaced by a pointer to the designer when the product is installed
- `database/migrations/2026_11_16_120000_add_designer_controls_to_installed_chat_checkin_globes.php` - new file, backfills existing installs
- `tests/Feature/ChatCheckinDesignerBackfillTest.php` - new file, five tests for the migration
- `tests/Feature/BotCheckinTest.php` - two tests for the hometown control
- `tests/Feature/RecipeDesignerBlockTest.php` - chat-checkin added to the shipped-blocks test, three tests for personal groups
- `tests/Feature/ProductInstallTest.php` - globe install expectation lists fifteen controls

### Claims
- **C1** [code] `globe.md` declares fifteen controls: `land_color`, `land_dot_size`, `globe_color`, `globe_opacity`, `spin_seconds`, `tilt`, `pin_color`, `label_color`, `label_size`, `font` (`webfont=true`), `text_color`, `accent_color`, `hometown` (label "Your location"), `toggle_hud`, `toggle_pinlist`.
- **C2** [code] The `chat-checkin` manifest `designer` block has seven presets with keys `default`, `classic`, `night`, `day`, `monotone`, `warm`, `cold`, in that order.
- **C3** [code] The designer group titled "Settings" has `"personal": true` and holds `hometown`, `toggle_hud` and `toggle_pinlist`.
- **C4** [code] The control defaults in `globe.md` equal the `default` preset's values.
- **C5** [code] `ProductDesigner::lookKeys()` returns `keys()` minus every key in a group with `personal` true.
- **C6** [code] `ProductDesigner::currentValues()` (what a saved look captures) reads `lookKeys()`, not `keys()`.
- **C7** [code] `ProductDesigner::controls()` still reads `keys()`, so personal keys render as knobs.
- **C8** [code] `RecipeManifestValidator::designerErrors()` requires presets to set every non-personal designer key and reports a preset that sets a personal key.
- **C9** [test] `RecipeDesignerBlockTest` "lets a preset leave out a personal key and rejects one that sets it" and "still asks a preset for a key once its group is no longer personal" assert C8 against the chat-checkin manifest.
- **C10** [code] `checkinGlobe.ts` re-reads its seven `--globe-*` custom properties every `STYLE_POLL_MS` (500) and applies changes to existing materials and the tilt without rebuilding.
- **C11** [code] The land `THREE.Points` has `renderOrder = 1`; the shell keeps the default 0.
- **C12** [code] `update()` in `checkinGlobe.ts` returns without calling `rebuildPins()` when the brand-pinned list's login, name, place, lat and lng are unchanged since the last build.
- **C13** [code] `mountCheckinGlobe()` sets an inline `position: relative` only when the placeholder's computed position is `static`.
- **C14** [unverified] Before C12, a HUD toggle on an account with a running timer control rebuilt the globe's labels about four times a second (33 rebuilds in 8 s, measured in Firefox); after C12 the same toggle produced 0 rebuilds in 6 s.
- **C15** [code] `BotCheckinController::homeFor()` returns the resolved place of the `hometown` control on the overlays in the account's chat-checkin install's `primitive_map.overlays` when that control exists, null when its value is empty or unresolvable, and the integration settings' `home_lat`/`home_lng` only when no such control exists.
- **C16** [test] `BotCheckinTest` "an installed globe measures from its hometown control, not the old setting" and "an empty hometown on an installed globe measures nothing" assert C15.
- **C17** [unverified] Both tests in C16 failed against `BotCheckinController.php` as it stood before this change.
- **C18** [code] `CheckinIntegrationController::save()` neither resolves nor writes `home_place_label`, `home_lat` or `home_lng` while the account has a chat-checkin install.
- **C19** [code] The migration patches an install only when its CSS, trimmed, equals `OLD_CSS` exactly; otherwise it logs "css edited" and changes nothing.
- **C20** [code] The migration inserts its thirteen controls at the `classic` preset's values, with `hometown` set to the account's Chat Checkin `home_place_label` or empty.
- **C21** [test] `ChatCheckinDesignerBackfillTest` asserts the migration's `NEW_CSS` equals a fresh install's CSS, that patched controls match a fresh install's label, type, config and description, that every look control equals the `classic` preset, and that an edited install is left alone.
- **C22** [code] `ProductInstallTest` expects the installed globe to have exactly the fifteen keys in C1.

### Unchanged
- The checkin integration's other settings (`pin_lifetime`, `cooldown_seconds`, enabled) and their save path are untouched; only the home branch of `save()` moved behind the install check.
- `OverlayRenderer.vue`, whose render effect calls `syncGlobes()` and so `update()` on every data write once the globe module is loaded, is not in the diff; C12 makes those calls cheap rather than stopping them. Plinko is wired the same way (`syncPlinkos()`) and is not in the diff.
- The Twitch Chat and Chat Emote Bubbles designer blocks are unchanged; neither declares a personal group, so `lookKeys()` equals `keys()` for both.

### Risk
Existing installs see the font change from generic monospace to JetBrains Mono, brighter near-side
continents and no far-side dots once the migration runs. An install whose globe CSS was edited is
skipped and keeps working, but its designer shows only the two panel switches until it is
reinstalled.
