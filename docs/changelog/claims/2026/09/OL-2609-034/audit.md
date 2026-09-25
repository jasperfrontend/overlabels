## Audit of OL-2609-034 - feat(gamejam): remove Chat Castle entirely

**Audited:** 2026-09-25
**Commit:** 3f1ff439e9c1c0aa088fc796294b023b37618304 (single commit carrying `Changelog: OL-2609-034`)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `git grep -i -E 'gamejam\|chat castle\|castlehelp' 3f1ff439 -- app routes resources config database tests public ':!public/build'` returns only `2026_04_18_120002_seed_gamejam_bot_commands.php:7-8 @3f1ff439` (docblock) and `2026_09_08_220000_drop_chat_castle.php:9,18,39,58 @3f1ff439`; no `Game`/`GameJoiner`/`GameDoor`/`GameHiddenTile`/`GameHidingSpot`/`GameBlocker`/`GameZombie` class reference (the only `\bGame\b` hits are label strings in `TemplateDataMapperService.php:170-171` and `for-designers.md:72`). @HEAD one more hit, `app/Models/BotBuiltin.php:114` docblock "the six Chat Castle ones", added by 003ded76 (OL-2609-062) |
| C2 | CONFIRMED | `routes/` @3f1ff439 contains no `gamejam`, `room-builder` or `RoomBuilder` (git grep); `php artisan route:list --json` @HEAD: 412 routes, none with `gamejam` or `room-builder` in URI or name |
| C3 | CONFIRMED | `app/Models/BotBuiltin.php:35-51 @3f1ff439` - `DEFAULTS` has 15 entries, none of `join`, `p`, `h`, `a`, `s`, `castlehelp`. @HEAD 18 entries (`forgetme` OL-2609-099, `stack`/`tower` OL-2609-052), still none of the six |
| C4 | CONFIRMED | `database/migrations/2026_09_08_220000_drop_chat_castle.php:29-37 @3f1ff439` - `TABLES` in the stated order, dropped via `Schema::dropIfExists` at :43-45; :48 deletes `bot_builtins` rows `whereIn('command', VERBS)` with the six verbs at :39; :51-61 forgets `'gamejam.debug.'.$twitch_id` for every user with a non-null `twitch_id` (same key as `GamejamDebug::cacheKey()` @3f1ff439^ :58); `down()` :64-68 is empty. Unchanged @HEAD |
| C5 | CONTRADICTED | Tagged [test] but names no test, and none exists: `git grep -i -E 'drop_chat_castle\|game_zombies\|castle' -- tests` is empty @3f1ff439 and @HEAD. The statement describes a one-off run against a local database state that no longer exists |
| C6 | CONFIRMED | `app/Support/HelpCorpus.php:101-105 @3f1ff439` - `SECTION_EXTRAS` has one key `Live data` with one Integration Presets link; `SitemapController.php:36 @3f1ff439` is the only Vue help page entry. Same @HEAD (`HelpCorpus.php:101-105`, `SitemapController.php:49`) |
| C7 | CONFIRMED | `HelpLayout.vue` is deleted in the diff; `git grep HelpLayout 3f1ff439^` hits only `resources/js/pages/help/gamejam/Index.vue:3,94,443`; `IntegrationPresets.vue:5 @3f1ff439` imports `AppLayout` and the file is not in the diff |
| C8 | CONFIRMED | `public/help-index.json` is gitignored (`.gitignore:11`), so the shipped build cannot be read; the local build on disk (dated 2026-09-25) has zero matches for `gamejam\|chat castle\|castlehelp`, and `resources/help` @3f1ff439 contains none of those terms |
| C9 | CONFIRMED | `2026_04_18_120002_seed_gamejam_bot_commands.php:23-32 @3f1ff439` - `COMMANDS` has the April 14th eight; the diff removes `join`, `p`, `h`, `a`; `git show --name-status` reports the file as `M`, not renamed. Unchanged @HEAD |
| C10 | UNVERIFIABLE | tagged [unverified]; the bot repo is not in this checkout. The in-repo half ("the command map no longer lists those verbs") follows from C3/C4: `BotCommandMapController.php:49 @3f1ff439` reads `botBuiltins` rows only |

### Surface
Phantom: `docs/commit-guide.md` - not in the diff, and absent from the tree at 3f1ff439^, 3f1ff439 and HEAD (the path is gitignored by `/docs/*.*`, `.gitignore:18`). Phantom: `public/css classes.txt` - not in the diff (the claim itself says it was untracked; gitignored by `.gitignore:62`). Every path in `git show --stat` (1404 files) is otherwise covered; directory counts match (`public/rooms/` 1105, `public/tile-icons/` 171, `public/Platformer 2D Game Tileset/` 44, `app/Services/Gamejam/` 4, `resources/js/pages/gamejam/` 5, `resources/js/components/gamejam/` 3) except `resources/js/rooms/`, see Notes.

### Findings
- **F1** phantom path - Surface lists `docs/commit-guide.md` ("`gamejam` removed from the commit scopes"), but the file is gitignored (`.gitignore:18`), absent from the tree at 3f1ff439 and HEAD, and not in the diff, so the claimed edit is not part of the record; a new claim should drop it or say where that guide lives.
- **F2** phantom path - Surface lists `public/css classes.txt`, which is not in the diff (gitignored, `.gitignore:62`); the claim discloses it as untracked, but a disk-only deletion is not a Surface path and should be recorded outside Surface.
- **F3** mistagged, [test] with no test - C5 names no test file and none exists for `2026_09_08_220000_drop_chat_castle.php` @3f1ff439 or @HEAD; it records a one-off local run and should be restated as [unverified], or a migration test should be added if the behaviour is to be pinned.
- **F4** Unchanged names a nonexistent path - "`app/Models/BotRateLimitLog.php` ... untouched" names a file that does not exist @3f1ff439; the class is `App\Services\Bot\RateLimitLog` (`app/Services/Bot/RateLimitLog.php`), which this diff leaves with zero callers (its two users @3f1ff439^ were `GamejamAdminController.php:38` and `AppServiceProvider.php:167`, both removed; `git grep RateLimitLog` @3f1ff439 and @HEAD hits only its own definition). A new claim should correct the path and disclose that the class is now dead code.
- **F5** contradicts CLAUDE.md - the diff edits `resources/help/reference/Wheel of Fortune.md` (a depth-0 reference spec), removing "Same shape as `!join` for Gamejam.", while `CLAUDE.md:272-274` records "Past changelog entries were deliberately NOT rewritten by the rename. They record what was true when they were written. Same for the dated design specs in `resources/help/reference/*.md` (depth 0, not served ...)"; that line is stated about the Aug 2026 rename, but the claim's own Unchanged applies the same "history stands" reasoning to changelog entries and `docs/MILESTONES.md` and not to this spec. Decide whether the rule covers this file and record the answer.
- **F6** reverses an earlier claim without citing it - `OL-2609-021` C4 records "`SECTION_EXTRAS` lists `/help/integration-presets` under `Live data` and `/help/gamejam` under `Bot & chat`, and those are the only two entries"; this change removes the `Bot & chat` entry (`HelpCorpus.php` hunk) and C6 restates the constant without citing OL-2609-021 inline.

### Notes
- `resources/js/rooms/` is described as "5 room maps" but has 6 tracked files in the diff; the sixth is `resources/js/rooms/.gitkeep`, covered by the directory entry.
- C1 drift @HEAD (`BotBuiltin.php:114`) comes from OL-2609-062 and is a docblock describing the removal; not a finding.
- `2026_04_18_120002_seed_gamejam_bot_commands.php:22 @3f1ff439` still labels the list "as of 2026-04-18", though after this edit it no longer holds what DEFAULTS held on that date (the docblock at :7-11 explains the edit).
- No tests were run: no claim names a runnable test.
