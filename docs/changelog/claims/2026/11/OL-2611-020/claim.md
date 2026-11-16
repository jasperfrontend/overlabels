## OL-2611-020 - fix(bowling): drop the old aliases on installs the redesign move skipped

**Shipped:** 2026-11-16
**Commit:** `git log --grep=OL-2611-020`

Remedies OL-2611-019 audit F1, F2, F3.

### Surface
- `database/migrations/2026_11_16_210000_drop_follower_bowling_aliases_left_behind_by_the_redesign_move.php` - new: drops `bot_aliases` from every Follower Bowling install that still lists them
- `tests/Feature/FollowerBowlingRedesignMigrationTest.php` - two tests for the two skip paths
- `docs/changelog/claims/2026/11/OL-2611-019/remedy.md` - remedy of the OL-2611-019 audit

### Claims
- **C1** [code] `2026_11_16_200000_move_installed_follower_bowling_lanes_to_the_redesign.php` `upgrade()` makes no hand-edit check; its only comparison of existing source is the already-moved check, which returns `'done'` when the lane's `html` equals `HTML`, it has no `gobowl` control and the install has no `bot_aliases`, leaving `head`, `css`, `description` and `template_tags` unwritten (corrects OL-2611-019 C13, audit F1).
- **C2** [code] The same `upgrade()` returns `'no lane overlay in the install'` or `'overlay is gone'` before deleting any alias (adds to OL-2611-019, audit F2).
- **C3** [code] `2026_11_16_210000_drop_follower_bowling_aliases_left_behind_by_the_redesign_move.php` selects installs by `recipes.slug` in `follower-bowling` or `follower_bowling`, uses `DB::table()` only, and skips an install whose `primitive_map` has no `bot_aliases` key.
- **C4** [code] For an install with `bot_aliases`, that migration deletes `bot_aliases` rows whose ids are in the map and whose `user_id` is the install's, and removes the `bot_aliases` key from `primitive_map`, without reading the install's overlay (fixes OL-2611-019 audit F2).
- **C5** [test] `FollowerBowlingRedesignMigrationTest` "drops the aliases of an install the move skipped because its lane overlay is gone" asserts both aliases survive the move, are gone after the cleanup runs twice, `bot_aliases` is gone from `primitive_map`, and the user's own `next` alias survives.
- **C6** [test] `FollowerBowlingRedesignMigrationTest` "drops the aliases of an install the move skipped because it names no lane" asserts the aliases and the `bot_aliases` key are gone after the move and the cleanup.
- **C7** [code] `AutoPlayService::isPlaying()` reads no control, reversing OL-2609-137 C7 (adds to OL-2611-019 C1, audit F3).
- **C8** [code] `AutoPlayService` has no `controlChanged()`, reversing OL-2609-137 C14, and the Follower Bowling manifest has no `installs.bot_aliases`, reversing OL-2609-137's first Unchanged line (adds to OL-2611-019 C3 and C5, audit F3).
- **C9** [code] No test asserts a Follower Bowling install creates `fbfirst` or `fbdraw`; OL-2611-019 rewrote the test OL-2609-038 C32 describes to use a test-built manifest (adds to OL-2611-019 C21, audit F3).

### Unchanged
- The shipped migration `2026_11_16_200000_...` is not in this diff: it has already run on deploy, so the cleanup is a second migration rather than an edit.

### Risk
Deploying runs the cleanup: any Follower Bowling install that still lists `!fbfirst` / `!fbdraw` in its `primitive_map` loses those two aliases, whether or not its lane overlay exists.
