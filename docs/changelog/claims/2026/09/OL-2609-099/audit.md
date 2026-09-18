## Audit of OL-2609-099 - feat(bot): !forgetme, and a one-off notice on a viewer's first interaction

**Audited:** 2026-09-18
**Commit:** 6790c532
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONTRADICTED | a) CONFIRMED: `routes/api.php:242 @6790c53` - `Route::post('/forgetme', ...)` inside the `/internal/bot` prefix, no `{login}`; same @HEAD. b) CONTRADICTED: "Every other bot command endpoint does" is false - `routes/api.php:237-239 @6790c53` registers `/manage`, `/followage` and `/accountage` with no `{login}` segment either, and `BotAccountageController` @6790c53 takes no channel in the body at all ("this command does not need channel_login"). |
| C2 | CONFIRMED | `app/Services/ViewerErasureService.php:71-80 @6790c53` - `Checkin` on `chatter_twitch_id`, `TowerBlock` on `chatter_twitch_id`, `ListAppendHistory` on `chatter_id`, `TwitchEvent` `whereRaw("event_data->>'user_id' = ?")`, `ExternalEvent` `whereRaw("raw_payload->>'chatter_id' = ?")`, all bound to `$twitchId` only; same @HEAD |
| C3 | CONFIRMED | `git grep option_sets app/Services/ViewerErasureService.php` returns nothing @6790c53 and @HEAD; `BotForgetMeController.php:70 @6790c53` reply contains "Anything on a streamer's own list, ask them." |
| C4 | CONFIRMED | `ViewerErasureService.php:105-108,122-134 @6790c53` - `whereIn('key', self::NAME_CONTROL_KEYS)` + `where('source_managed', true)`, then `matchesName()` compares `mb_strtolower(trim(...))` for equality, not containment |
| C5 | CONFIRMED / CONTRADICTED | a) CONFIRMED: `ViewerErasureService.php:113 @6790c53` - `$control->writeValue('')`. b) CONTRADICTED: the `update()` that refuses `source_managed` is `OverlayControlController::update()` (`app/Http/Controllers/OverlayControlController.php:197 @HEAD`, `abort(403, ...)`); the model has no `update()` override, so `OverlayControl`'s own `update()` would not refuse anything. See F3. |
| C6 | CONFIRMED | `ViewerErasureService.php:84-87 @6790c53` - `DB::table('viewer_erasures')->updateOrInsert(['twitch_id' => ...], ['erased_at' => now()])`; `ViewerErasureTest` "is idempotent" asserts one row after two calls, passes |
| C7 | CONFIRMED | `database/migrations/2026_09_18_180000_create_viewer_erasures_table.php @6790c53` - `id()`, `string('twitch_id')->unique()`, `timestamp('erased_at')`, nothing else |
| C8 | CONFIRMED | `BotCheckinController.php:83 @6790c53` (before args parsing, place resolution and the pin upsert, returns `['reply' => null]`); `BotTowerController.php:92 @6790c53` (immediately before the cooldown, returns `['reply' => null]`); `ListAppendService.php:134-135 @6790c53` returns `['fired' => false, 'reason' => 'viewer_erased']` before `$list->update()` and `ListAppendHistory::create()` at 143-152 |
| C9 | CONFIRMED | `BotBuiltin.php:76 @6790c53` - `['command' => 'forgetme', 'permission_level' => 'everyone', 'owner' => self::OWNER_PLATFORM]`; `BotBuiltin.php:128-131` `isEditable()` is `ownerOf() === OWNER_USER`; `BotBuiltinsController::update()` line 51 `abort_unless($botBuiltin->editable(), 403, ...)` and is not in the diff |
| C10 | CONFIRMED | `2026_09_18_190000_backfill_forgetme_builtin.php @6790c53` - `DB::table('users')->where('bot_enabled', true)->chunkById(...)` then `DB::table('bot_builtins')->insertOrIgnore($rows)`; no `App\Models` import; no query against `bot_commands` / `bot_aliases` |
| C11 | CONFIRMED | `ViewerNoticeService.php:30,58 @6790c53` - `SEEN_TTL_SECONDS = 90 * 24 * 60 * 60`, gate is `if (! Cache::add($key, true, self::SEEN_TTL_SECONDS)) return $reply;`, key `viewer:notified:<id>` |
| C12 | CONFIRMED | `ViewerNoticeService.php:46-52 @6790c53` - returns `$reply` when `$twitchId === null \|\| === '' \|\| $reply === ''`, and when `mb_strlen($reply) + mb_strlen(NOTICE) > 480` |
| C13 | CONFIRMED | `BotForgetMeController.php:45 @6790c53` - `$notices->forget($data['chatter_id'])`; `ViewerNoticeTest` "tells a viewer again after they have asked to be forgotten" passes |
| C14 | CONFIRMED | `BotForgetMeController.php:49 @6790c53` - `Log::info('Viewer erasure completed', $removed)`; `$removed` is the `array<string,int>` built at `ViewerErasureService.php:69-82`; no other log call in the file |
| C15 | CONTRADICTED | `tests/Feature/ViewerErasureTest.php @6790c53` - 12 tests, `php artisan test --filter=ViewerErasure` passes (12 passed, 28 assertions). Every listed assertion exists EXCEPT "erasure across all five tables": the first test asserts `checkins`, `tower_blocks`, `twitch_events` and `external_events` only (lines 80-84); `list_append_history` is never created or counted in the file, and `git grep ListAppendHistory` over `tests/` @6790c53 finds it only in `ListAppenderTest.php`. See F2. |
| C16 | CONFIRMED | `tests/Feature/ViewerNoticeTest.php @6790c53` - 7 tests matching the seven listed assertions; `php artisan test --filter=ViewerNotice` passes (7 passed, 10 assertions) |
| C17 | CONFIRMED | `C:\Users\jmstu\PhpstormProjects\overlabels-bot\test\forgetme.test.js` @3baa5dc - asserts the URL is `http://overlabels.test/api/internal/bot/forgetme` and excludes the channel login, `deepEqual` on exactly `chatter_id` / `chatter_login` / `chatter_display_name`, speaks the backend `reply`, and replies matching `/try again\|privacy@overlabels\.com/i` on a 500. `npm test` in that repo: 83 tests, 83 pass, 0 fail. |

### Surface
Complete. All 16 paths in `git show --stat 6790c532` are listed except `docs/changelog/claims/2026/09/OL-2609-099/claim.md` and `docs/changelog/changelog-2026-09.md`, both exempt.

### Findings
- **F1** false claim - C1's second sentence is wrong: `routes/api.php:237-239 @6790c53` shows `/manage`, `/followage` and `/accountage` are bot command endpoints with no `{login}` segment, so `/forgetme` is not the only one; the same sentence appears in the commit message and in the bot repo's `test/forgetme.test.js` comment. The no-`{login}` design is fine; only the uniqueness statement is false.
- **F2** overstated test claim - C15 says `ViewerErasureTest` asserts erasure "across all five tables"; `tests/Feature/ViewerErasureTest.php:35-84 @6790c53` covers four. `list_append_history` deletion (`ViewerErasureService.php:73`) has no test at the commit or @HEAD; a regression there would be silent.
- **F3** mis-cited symbol - C5's reason names `update()` as refusing `source_managed` rows; that guard is `OverlayControlController::update()` line 197 and `setValue()` line 357 @HEAD, both HTTP entry points. Eloquent's `OverlayControl::update()` has no such guard, so the stated reason does not hold for the symbol as written; the code choice (`writeValue()`, which also moves `updated_at`) is unaffected.
- **F4** undisclosed limitation - the diff ships the promise "we won't store you again" (`BotForgetMeController.php:68-69 @6790c53`) and "stops storing them in future" (`resources/help/pages/bot/commands.md`, `/forgetme` table row @6790c53), but `git grep isSuppressed HEAD -- app` returns only the four files of this change: `TwitchEventSubController.php:546 @HEAD` re-creates `twitch_events` rows carrying `event_data.user_id` on the next follow/sub/cheer, and `BotChatStatsController.php:55 @HEAD` rewrites `latest_chatter_name` - both surfaces `erase()` clears. No claim, Unchanged line or Risk line records this boundary; Risk only says the viewer is "inert for check-ins and chat games".

### Notes
- `git diff 6790c532 HEAD` touches no path of this change; every `[code]` claim reads the same @HEAD.
- OL-2609-098 C6 ("No `!forgetme` command, chat notice or self-service endpoint exists") is superseded by this commit, which is disclosed by its Surface line on `resources/help/pages/viewers.md`.
- `BotTowerController.php:82-88 @6790c53` writes the `tower:status:<user>:<chatter_id>` cooldown key before the suppression check, on the `action=status` branch. Nothing viewer-scoped is persisted there, so it is outside C8's "before writing anything", but it is the one place an erased viewer's id still reaches a store.
