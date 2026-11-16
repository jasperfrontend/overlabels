## OL-2611-019 - feat(bowling): a full-screen lane that runs itself, first come first served

**Shipped:** 2026-11-16
**Commit:** `git log --grep=OL-2611-019`

### Surface
- `app/Services/Recipes/AutoPlayService.php` - the `switch` is gone from the loop; `controlChanged()` removed; `isPlaying()` takes only the list
- `app/Models/OverlayControl.php` - the `saved` hook no longer calls `AutoPlayService`
- `app/Jobs/AutoPlayNext.php` - docblock only
- `resources/recipes/recipe-manifest.schema.json` - `auto_play` loses `switch`; requires `list` and `seconds`
- `resources/recipes/follower-bowling/manifest.json` - `auto_play` without `switch`, `bot_aliases` removed, copy describing the switch and the mod commands rewritten
- `resources/recipes/follower-bowling/lane.md` - the lane overlay rebuilt as a 1920x1080 scene; `gobowl` control removed
- `database/migrations/2026_11_16_200000_move_installed_follower_bowling_lanes_to_the_redesign.php` - new: moves every installed lane to the redesign
- `resources/help/pages/deep-dives/follower-bowling-lane.md` - the sentence about `gobowl` and mods skipping ahead rewritten
- `public/products/follower-bowling-hero.jpg` - replaced with the vertical-lane hero
- `public/products/follower-bowling-hero.svg` - deleted
- `tests/Feature/FollowerBowlingRedesignMigrationTest.php` - new file
- `tests/Feature/ProductBowlingAutoPlayTest.php` - switch tests replaced by list-disabled tests and a no-switch install test
- `tests/Feature/ProductBowlingTest.php` - 20 controls, no `bot_aliases` in the manifest
- `tests/Feature/ProductChatInstallsTest.php` - the alias install and collision tests use a test-built manifest instead of Follower Bowling
- `tests/Feature/ProductUninstallTest.php` - alias assertions replaced by appender assertions; removals count 5 -> 3

### Claims
- **C1** [code] `AutoPlayService::isPlaying()` returns true when the list's `disabled_at` is null and its items are non-empty, and reads no control.
- **C2** [code] `AutoPlayService::loopOf()` returns a loop for a manifest `auto_play` with a string `list`, without requiring a `switch` key.
- **C3** [code] `AutoPlayService` has no `controlChanged()` method, and `OverlayControl::booted()` calls only `LivingTitleService::controlChanged()`.
- **C4** [code] `recipe-manifest.schema.json` `auto_play` has `required: ["list", "seconds"]`, no `switch` property, and `additionalProperties: false`.
- **C5** [code] The Follower Bowling manifest declares `"auto_play": { "list": "lane", "seconds": 15 }` and has no `installs.bot_aliases`.
- **C6** [code] `lane.md` declares 20 controls, all of type expression, and none keyed `gobowl`.
- **C7** [code] `lane.md` html contains no `[[[if:c:gobowl]]]`.
- **C8** [code] The ten stand-in pins in `lane.md` keep the exact `[[[if:channel_followers.N.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pinN)">` markup.
- **C9** [test] `ProductBowlingAutoPlayTest` "arms the first pop at once when a chatter joins an idle lane" dispatches `AutoPlayNext` with no control written.
- **C10** [test] `ProductBowlingAutoPlayTest` "installs no on/off switch on the lane overlay" asserts no `gobowl` control on an installed lane.
- **C11** [test] `ProductBowlingAutoPlayTest` "arms nothing while the lane list is disabled" and "pops nothing once the lane list has been disabled" assert no dispatch and no pop for a disabled list.
- **C12** [code] The migration selects installs by `recipes.slug` in `follower-bowling` or `follower_bowling` and uses `DB::table()` only, no models.
- **C13** [code] The migration replaces `head`, `html`, `css`, `description` and `template_tags` of the install's `lane` overlay without comparing the existing source.
- **C14** [code] The migration deletes the overlay's `gobowl` control and the `bot_aliases` rows whose ids are in the install's `primitive_map.bot_aliases`, scoped to the install's `user_id`.
- **C15** [code] The migration removes the `bot_aliases` key from the install's `primitive_map`.
- **C16** [code] The migration's `TAGS` is the lane's allowlist at a followers cap of 50 (`User::FOREACH_CAP_MAX`), a superset of any account's installed allowlist.
- **C17** [test] `FollowerBowlingRedesignMigrationTest` "ships exactly the source a fresh install gets" asserts the migration's `HTML`, `CSS`, `HEAD` and `DESCRIPTION` equal a fresh install's, and that a fresh install's `template_tags` are all in `TAGS`.
- **C18** [test] `FollowerBowlingRedesignMigrationTest` "replaces an old lane wholesale" asserts the new source, no `gobowl`, 20 expression controls, the install's aliases deleted and `bot_aliases` gone from `primitive_map`.
- **C19** [test] `FollowerBowlingRedesignMigrationTest` "leaves aliases the install did not create alone" asserts an alias of the same user not in the map and another user's `fbfirst` survive.
- **C20** [test] `FollowerBowlingRedesignMigrationTest` "leaves a fresh install untouched and is safe to run twice" asserts the overlay's `updated_at` does not move.
- **C21** [test] `ProductChatInstallsTest` "installs aliases pointing at their targets, and uninstalls them again" installs two aliases from a test-built manifest and asserts both deleted by `RecipeInstaller::uninstall()`.
- **C22** [unverified] On overlabels.test the lane rendered at 61 fps with 16 bound CSS properties; a strike and a forced gutter ball were watched in Firefox; three `!bowl`s through `ListAppendService::fire()` were popped by `AutoPlayNext` fifteen seconds apart and a repeat `!bowl` was refused.
- **C23** [unverified] Jasper reported that no account other than his had Follower Bowling running on prod.

### Unchanged
- The twenty expression controls the migration leaves in place: their expressions in `lane.md` are identical in every version of the recipe since 1f2e2b25, so installed rows already match the new source.
- `ListActionService::pop()` and `draw()`, which the lane reads through `last_removed` / `last_removed_at`, are not in the diff; `!list lane pop first` still drives a throw by hand.
- `version` in the manifest stays 1, as Chat Tower's redesign (OL-2611-016) did.

### Risk
Deploying runs the migration: every installed lane's source is overwritten, including any hand edits,
and the install's `!fbfirst` / `!fbdraw` aliases are deleted. An install whose lane is in OBS starts
bowling through its line every fifteen seconds whenever someone types `!bowl`, with no switch.
