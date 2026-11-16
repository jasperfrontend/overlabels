## Audit of OL-2611-019 - feat(bowling): a full-screen lane that runs itself, first come first served

**Audited:** 2026-11-16
**Commit:** a2fb38d341e768ce30a36f6379f97889c8f14a2c (the only commit carrying the trailer; it is HEAD, so @a2fb38d3 and @HEAD are the same tree)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Recipes/AutoPlayService.php:132-135 @a2fb38d3` - `isPlaying(OptionSet $list)` is `return $list->disabled_at === null && array_values($list->items ?? []) !== [];`, no control query; same at @HEAD |
| C2 | CONFIRMED | `app/Services/Recipes/AutoPlayService.php:159-170 @a2fb38d3` - `loopOf()` returns null only when `auto_play` is not an array or `list` is not a string; returns `['list', 'seconds']`, no `switch` read; same at @HEAD |
| C3 | CONFIRMED | `grep -n "function " app/Services/Recipes/AutoPlayService.php @a2fb38d3` lists `__construct`, `listAppended`, `schedule`, `run`, `loopFor`, `isPlaying`, `pendingKey`, `remainingWindow`, `loopOf` - no `controlChanged`; `app/Models/OverlayControl.php:94-99 @a2fb38d3` - the `saved` closure calls only `app(LivingTitleService::class)->controlChanged($control)`; same at @HEAD |
| C4 | CONFIRMED | `resources/recipes/recipe-manifest.schema.json:122-131 @a2fb38d3` - `"additionalProperties": false` (125), `"required": ["list", "seconds"]` (126), properties `list` and `seconds` only; same at @HEAD |
| C5 | CONFIRMED | `resources/recipes/follower-bowling/manifest.json:21 @a2fb38d3` - `"auto_play": { "list": "lane", "seconds": 15 }`; `grep -n bot_aliases` on the file returns nothing; same at @HEAD |
| C6 | CONFIRMED | `resources/recipes/follower-bowling/lane.md:440-465 @a2fb38d3` - "These 20 are defined", table of 20 rows all `expression`, none `gobowl`; `grep -n gobowl` on the file returns nothing; same at @HEAD |
| C7 | CONFIRMED | `grep -n gobowl resources/recipes/follower-bowling/lane.md @a2fb38d3` returns nothing (the old `[[[if:c:gobowl]]]` was at line 37 @a2fb38d3^); same at @HEAD |
| C8 | CONFIRMED | `resources/recipes/follower-bowling/lane.md:63-72 @a2fb38d3` - ten lines `[[[if:channel_followers.N.user_id]]][[[else]]]<div class="pin pin-filler" style="--fall: var(--pinN)">...`, N = 0..9, byte-identical to lines 63-72 @a2fb38d3^; same at @HEAD |
| C9 | CONFIRMED | `tests/Feature/ProductBowlingAutoPlayTest.php:101-110 @a2fb38d3` - setup writes no control, fires `!bowl`, asserts `AutoPlayNext` dispatched for the lane list with delay <= 0; ran `php artisan test tests/Feature/ProductBowlingAutoPlayTest.php`: passes |
| C10 | CONFIRMED | `tests/Feature/ProductBowlingAutoPlayTest.php:113-118 @a2fb38d3` - asserts no `gobowl` control on the installed lane overlay; passes |
| C11 | CONFIRMED | `tests/Feature/ProductBowlingAutoPlayTest.php` @a2fb38d3 - "arms nothing while the lane list is disabled" sets `disabled_at`, calls `schedule()`, asserts `AutoPlayNext` not dispatched; "pops nothing once the lane list has been disabled" calls `run()`, asserts items still `['ana','bo']`, `last_removed` null, no dispatch; both pass |
| C12 | CONFIRMED | `database/migrations/2026_11_16_200000_move_installed_follower_bowling_lanes_to_the_redesign.php:35 @a2fb38d3` - `SLUGS = ['follower-bowling', 'follower_bowling']`, line 509 `DB::table('recipes')->whereIn('slug', self::SLUGS)`; imports at 3-5 are `Migration`, `DB`, `Log` only, no `App\Models` |
| C13 | CONTRADICTED | Same migration `:570 @a2fb38d3` - `if ((string) $template->html === self::HTML && ! $hasSwitch && ! isset($map['bot_aliases'])) return 'done';` reads and compares the existing `html`. A lane whose html differs is replaced with no edit check (579-586), but a lane whose html already equals `HTML` with no switch and no aliases is skipped, and its `head`, `css`, `description` and `template_tags` are not written |
| C14 | CONFIRMED | Same migration `:588-591 @a2fb38d3` deletes `overlay_controls` where `overlay_template_id` = the lane and `key` = `gobowl` (scoped by overlay id, not by `user_id`); `:593-598` deletes `bot_aliases` where `user_id` = the install's and `id` in `primitive_map.bot_aliases` |
| C15 | CONFIRMED | Same migration `:575 @a2fb38d3` `unset($map['bot_aliases'])`, `:600-603` writes `primitive_map` back |
| C16 | CONFIRMED | `User::FOREACH_CAP_MAX = 50` at `app/Models/User.php:200 @a2fb38d3`. Ran a script building an `OverlayTemplate` from the migration's `HTML`/`CSS` and calling `extractTemplateTags(['followers' => N])`: caps 1, 10, 25, 50 give 57, 75, 120, 195 tags, none missing from `TAGS`; `TAGS` has 195 entries, identical to the cap-50 set; `HEAD` contributes no tags |
| C17 | CONFIRMED | `tests/Feature/FollowerBowlingRedesignMigrationTest.php:72-85 @a2fb38d3` - asserts `HTML`, `CSS`, `HEAD`, `DESCRIPTION` equal a fresh install's, and `array_diff(template_tags, TAGS) === []`; ran `php artisan test tests/Feature/FollowerBowlingRedesignMigrationTest.php`: passes |
| C18 | CONFIRMED | Same test `:87-105 @a2fb38d3` - asserts html/css/head/template_tags equal the constants, no `gobowl`, 20 expression controls, alias ids gone, `primitive_map` without `bot_aliases`; passes |
| C19 | CONFIRMED | Same test `:107-118 @a2fb38d3` - same-user `next` alias not in the map and another user's `fbfirst` both still exist after `up()`; passes |
| C20 | CONFIRMED | Same test `:120-131 @a2fb38d3` - runs `up()` twice on a fresh install, asserts the overlay's `updated_at` equals the stamp; passes |
| C21 | CONFIRMED | `tests/Feature/ProductChatInstallsTest.php:52-79 @a2fb38d3` - `chatRecipe()` manifest with `!next` and `!raffle`, asserts targets, then `uninstall()` and both `BotAlias::find()` null; ran `php artisan test tests/Feature/ProductChatInstallsTest.php`: passes |
| C22 | UNVERIFIABLE | tagged [unverified] |
| C23 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** Claim contradicted - C13 says the migration replaces the source "without comparing the existing source", but `database/migrations/2026_11_16_200000_move_installed_follower_bowling_lanes_to_the_redesign.php:570 @a2fb38d3` compares the existing `html` with `HTML` and skips the install (no `head`/`css`/`description`/`template_tags` write) when they match and there is no switch and no aliases; a later claim should restate C13 as "no hand-edit check; the only comparison is the already-moved check at line 570".
- **F2** Scope - the migration's two skip paths are in no claim: `:555-557 @a2fb38d3` (`'no lane overlay in the install'`) and `:561-563` (`'overlay is gone'`) return before the alias delete, so such an install keeps its `!fbfirst` / `!fbdraw` rows and its `primitive_map.bot_aliases`, which contradicts the Risk line "the install's `!fbfirst` / `!fbdraw` aliases are deleted"; record the skip paths and their effect in a later claim, or delete the aliases on those paths too.
- **F3** Contradiction with the record - the change reverses OL-2609-137 without citing it: its C7 ("`isPlaying()` is true: the switch control ... has value `'1'`"), its C14 (`AutoPlayService::controlChanged()`), and its first Unchanged line ("The `!fbfirst` and `!fbdraw` aliases are still installed by `manifest.json` `installs.bot_aliases`"); it also reverses OL-2609-038 C32 ("asserts the bowling install creates `fbfirst` ... and `fbdraw`"), whose test this diff rewrote. The claim cites only OL-2611-016; a later claim should name both IDs.

### Notes
- Ran all five test files in the diff: `ProductBowlingAutoPlayTest`, `FollowerBowlingRedesignMigrationTest`, `ProductChatInstallsTest`, `ProductBowlingTest`, `ProductUninstallTest` - 43 passed, 203 assertions.
- Unchanged lines hold: the 20 `  - expression:` lines in `lane.md` hash identically at every revision from 1f2e2b25 to a2fb38d3; no `app/Services/Lists/` path is in the diff; manifest `"version": 1` (line 6) is unchanged, and OL-2611-016 (04f5e79c) left `chat-tower/manifest.json` at version 1 too.
- With the overlay check gone from `isPlaying()` (C1), a lane List whose overlay the streamer deleted still pops every 15 s on `!bowl`; C1 covers this, so it is not a finding.
- `public/products/follower-bowling-hero.svg` was unreferenced before deletion: the manifest's `hero` was already the `.jpg` @a2fb38d3^.
