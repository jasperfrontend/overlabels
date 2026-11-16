## OL-2611-021 - feat(bot): !forgetme asks first, and !unforgetme is the way back

**Shipped:** 2026-11-17
**Commit:** `git log --grep=OL-2611-021`

### Surface
- `app/Http/Controllers/Api/Internal/BotForgetMeController.php` - accepts `answer`; erases only on `confirm` after an ask held in cache; `ASK_REPLY`, `CONFIRM_WORD`, `CONFIRM_WINDOW` constants
- `app/Http/Controllers/Api/Internal/BotUnforgetMeController.php` - new: the same two steps, lifting the suppression
- `app/Services/ViewerErasureService.php` - new `unerase()`
- `app/Models/BotBuiltin.php` - `unforgetme` added to `DEFAULTS`
- `routes/api.php` - `POST /api/internal/bot/unforgetme`
- `database/migrations/2026_11_17_120000_backfill_unforgetme_builtin.php` - new: seeds `unforgetme` for every bot-enabled account
- `resources/help/pages/viewers.md` - the confirm step, and a "Changed your mind?" section on `!unforgetme`
- `resources/help/pages/bot/commands.md` - `!unforgetme` row and the confirm step
- `tests/Feature/ViewerErasureTest.php` - `forgetMe()` helper now asks then confirms; 13 new tests

### Claims
- **C1** [code] `BotForgetMeController::handle()` calls `ViewerErasureService::erase()` only when `answer`, trimmed and lowercased, equals `confirm` AND `Cache::pull('forgetme:pending:'.$chatter_id)` returns truthy.
- **C2** [code] Every other `!forgetme` request puts `forgetme:pending:<chatter_id>` in the cache for `CONFIRM_WINDOW` (120) seconds and returns `ASK_REPLY` with `removed` null.
- **C3** [code] `BotForgetMeController::ASK_REPLY` contains `!forgetme confirm` and `!unforgetme`.
- **C4** [code] `BotUnforgetMeController::handle()` returns `lifted: false` and a "Nothing to undo" reply, without touching the cache, when `ViewerErasureService::isSuppressed()` is false.
- **C5** [code] For a suppressed viewer, `BotUnforgetMeController::handle()` calls `unerase()` only on `confirm` after its own ask, keyed `unforgetme:pending:<chatter_id>`, separate from the `forgetme:` key.
- **C6** [code] `ViewerErasureService::unerase()` deletes the viewer's `viewer_erasures` row and touches no other table.
- **C7** [code] `BotBuiltin::DEFAULTS` declares `unforgetme` at `everyone` tier with `owner` `OWNER_PLATFORM`.
- **C8** [code] The backfill migration inserts `unforgetme` rows for users with `bot_enabled` true via `DB::table()` and `insertOrIgnore`, and references no model.
- **C9** [code] No code path tells a suppressed viewer they are suppressed; `BotCheckinController`, `BotTowerController` and `ListAppendService` are not in the diff.
- **C10** [test] `ViewerErasureTest` "only asks on a bare forgetme, and deletes nothing", "asks rather than erases on a confirm with no ask before it", "does not let one viewer confirm another viewer ask" and "lets the ask expire" assert no erasure in each case.
- **C11** [test] `ViewerErasureTest` "lifts the suppression after an ask and a confirm, restoring nothing", "does not lift on a confirm with no ask before it" and "keeps a forgetme ask and an unforgetme ask apart" cover the `!unforgetme` steps.
- **C12** [test] `ViewerErasureTest` "declares unforgetme as a platform-owned everyone-tier builtin" and "seeds unforgetme for an account that opts into the bot".
- **C13** [unverified] With both `if (! $confirming || ! Cache::pull($pendingKey))` guards disabled, 8 of the new tests failed; with them restored all 33 in the file passed.
- **C14** [unverified] The bot side shipped first as overlabels-bot commit 606e348: both commands forward `args[0]` as `answer` and nothing else of the message; its suite passed 90/90.

### Unchanged
- The erasure itself: `ViewerErasureService::erase()` deletes the same tables and blanks the same `NAME_CONTROL_KEYS` as before; only when it is reached changed.
- The ingest checks that ignore a suppressed viewer (`isSuppressed()` callers in `BotCheckinController`, `BotTowerController`, `BotChatStatsController`, `ListAppendService`, `TwitchEventSubController`) are not in the diff and stay silent.
- The reply after a confirmed `!forgetme` (`replyFor()`) is unchanged and does not mention `!unforgetme`.

### Risk
Deploying runs the backfill migration. A bot older than 606e348 sends no `answer`, so against this app
every `!forgetme` only asks and can never be confirmed; the bot must be deployed first.
