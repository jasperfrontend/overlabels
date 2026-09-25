## OL-2609-137 - feat(products): Follower Bowling runs itself, and the followers cap defaults to 10

**Shipped:** 2026-09-25
**Commit:** `git log --grep=OL-2609-137`

### Surface
- `app/Services/Recipes/AutoPlayService.php` - new: the loop a manifest declares under `auto_play`; two hooks, a debounced schedule, and the job body
- `app/Jobs/AutoPlayNext.php` - new: one step of the loop, carries a list id
- `app/Services/Lists/ListActionService.php` - `pop()` extracted from `actionPop()` as a public method returning the removed value or null
- `app/Services/Lists/ListAppendService.php` - `AutoPlayService` injected; `fire()` calls `listAppended()` after the `ListUpdated` broadcast
- `app/Models/OverlayControl.php` - the `saved` hook also calls `AutoPlayService::controlChanged()`
- `app/Services/Recipes/RecipeManifestValidator.php` - semantic check: `auto_play.list` must be an `installs.lists` ref
- `resources/recipes/recipe-manifest.schema.json` - `auto_play` property declared (`list`, `switch`, `seconds`; all required)
- `resources/recipes/follower-bowling/manifest.json` - `auto_play` added; description, ready_message and notes reworded for a lane that runs itself
- `resources/recipes/follower-bowling/lane.md` - intro sentence, the on-overlay mods hint, and the `gobowl` control description reworded
- `app/Models/User.php` - `PREFERENCE_DEFAULTS['foreach_caps']['followers']` 5 to 10
- `app/Services/TemplateDataMapperService.php` - `INDEXED_USER_SCOPE_FIELDS['followers']['default_cap']` 5 to 10
- `resources/help/pages/conditionals.md` - followers cap default in the iterables table 5 to 10
- `resources/help/pages/deep-dives/follower-bowling-lane.md` - lead, step 2 and the build-it-yourself closing reworded for the loop and the new default
- `tests/Feature/ProductBowlingAutoPlayTest.php` - new file, 14 tests
- `tests/Feature/ProductBowlingTest.php` - the cap-limited sentence test sets a cap of 5 explicitly now that the default is 10
- `tests/Unit/UserPreferencesTest.php` - the default-merge test expects a followers cap of 10
- `docs/changelog/changelog-2026-09.md` - prose entry

### Claims
- **C1** [code] `resources/recipes/follower-bowling/manifest.json` has `"auto_play": {"list": "lane", "switch": "gobowl", "seconds": 15}`.
- **C2** [test] `ProductBowlingAutoPlayTest` "declares the loop in the manifest" asserts C1.
- **C3** [code] `recipe-manifest.schema.json` declares `auto_play` as an object with `additionalProperties: false`, required `list` (string), `switch` (matching `OverlayControl::KEY_PATTERN`) and `seconds` (integer 1..600); the root keeps `additionalProperties: false`.
- **C4** [test] `RecipeManifestSchemaTest` "validates every shipped manifest against the published schema" passes with C1 present, because of C3.
- **C5** [code] `RecipeManifestValidator::installsErrors()` adds an error at pointer `/auto_play/list` when `auto_play.list` is a string that matches no `installs.lists[].ref`.
- **C6** [test] `ProductBowlingAutoPlayTest` "refuses a manifest whose loop names a list the product does not install" asserts C5 both ways.
- **C7** [code] `AutoPlayService::schedule()` returns without dispatching unless `loopFor()` resolves the list to a declared loop AND `isPlaying()` is true: the switch control (found by key on any overlay in the instance's `primitive_map['overlays']`, owned by the list's user) has value `'1'`, the list has no `disabled_at`, and its items are non-empty.
- **C8** [code] `AutoPlayService::schedule()` dispatches `AutoPlayNext` with a delay of `max(0, seconds - age of last_removed_at)`, guarded by `Cache::add(pendingKey(list id), 1, delay + seconds)`, with `afterCommit()`.
- **C9** [test] `ProductBowlingAutoPlayTest` "arms the first pop at once when a chatter joins an idle lane that is switched on", "arms nothing while the lane is switched off", "starts the loop from the switch when people are already in line", "does not start the loop from the switch when the line is empty", "arms one pending pop however many chatters join" and "waits out a throw still playing before joining the queue arms the next pop" assert C7 and C8 through the two hooks.
- **C10** [unverified] The four hook-driven tests in C9 were run with `ListAppendService.php` and `OverlayControl.php` stashed to their pre-change state and failed; the other nine passed.
- **C11** [code] `AutoPlayService::run()` forgets the pending key first; returns when not playing; re-schedules without popping when `last_removed_at` is younger than `seconds`; otherwise calls `ListActionService::pop($owner, $list, 'first')` and then `schedule()` on the fresh list.
- **C12** [test] `ProductBowlingAutoPlayTest` "pops the front of the line, broadcasts it, says nothing in chat and arms the next pop", "ends the loop when the last bowler has gone", "pops nothing once the lane has been switched off" and "holds a pop that lands while a mod-started throw is still playing" assert C11, including zero `BotChatOutbox` rows and a `ListUpdated` dispatch.
- **C13** [code] `ListActionService::pop()` is the transaction body formerly inline in `actionPop()`: lock, snapshot with `REASON_BEFORE_POP`, remove index 0 or last, write `last_removed` and `last_removed_at`, broadcast, return the value; `actionPop()` now builds its two reply strings around it.
- **C14** [code] `AutoPlayService::controlChanged()` returns before any query unless the control's `type` is `boolean` and `overlay_template_id` is set.
- **C15** [test] `ProductBowlingAutoPlayTest` "ignores a boolean control that is not the switch" and "leaves a list that belongs to no product alone" assert the two hooks are no-ops outside a declared loop.
- **C16** [code] `User::PREFERENCE_DEFAULTS['foreach_caps']['followers']` is 10 and `TemplateDataMapperService::INDEXED_USER_SCOPE_FIELDS['followers']['default_cap']` is 10.
- **C17** [test] `ProductBowlingTest` "counts the real pins the lane renders, which is the followers cap when the channel is above it" passes with a cap of 5 set on the user explicitly.

### Unchanged
- The `!fbfirst` and `!fbdraw` aliases are still installed by `manifest.json` `installs.bot_aliases` and still target `!list lane pop first` and `!list lane draw`; `actionDraw()` is not in the diff. A mod pop mid-loop is honoured by C11's window check, not blocked.
- `AutoPlayNext` does not implement `ShouldBeUnique`. The cache key in C8 is the coalescing mechanism, as `SyncLivingTitle` records.
- No per-install setting for the pace. `seconds` is the recipe author's number in the manifest and is not read from `recipe_instances.ingredients`; `RecipeIngredients` is not in the diff.
- `OverlayControl::createForTemplate()` still does not stamp `recipe_instance_id` on a product's controls; `controlChanged()` resolves the instance through the user's `RecipeInstance` rows and their `primitive_map['overlays']` instead. `RecipeInstaller` is not in the diff.
- The lane overlay's expressions (`bowl_t`, `bowl_age`, the pin timings up to 12 s) are not in the diff; the manifest's 15 s covers them.
- Accounts that saved their own followers cap keep it: `User::foreachCaps()` merges stored values over the defaults and is not in the diff.

### Risk
Every account that never set a followers cap now renders up to 10 followers in a `channel_followers` foreach instead of 5. A Follower Bowling install that was switched on with people in line before this deploy starts popping only on the next `!bowl` or the next `gobowl` write, since nothing re-arms existing state at deploy.
