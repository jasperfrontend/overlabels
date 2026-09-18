## OL-2609-099 - feat(bot): !forgetme, and a one-off notice on a viewer's first interaction

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-099`

### Surface
- `app/Services/ViewerErasureService.php` - new: `isSuppressed()`, `erase()`, `NAME_CONTROL_KEYS`
- `app/Services/ViewerNoticeService.php` - new: `decorate()`, `forget()`
- `app/Http/Controllers/Api/Internal/BotForgetMeController.php` - new: the `!forgetme` endpoint
- `database/migrations/2026_09_18_180000_create_viewer_erasures_table.php` - new: `viewer_erasures` (twitch_id unique, erased_at)
- `database/migrations/2026_09_18_190000_backfill_forgetme_builtin.php` - new: seeds the builtin for existing opted-in accounts
- `app/Models/BotBuiltin.php` - `forgetme` added to `DEFAULTS`, everyone tier, `OWNER_PLATFORM`
- `routes/api.php` - `POST /internal/bot/forgetme`, no `{login}`; controller import
- `app/Http/Controllers/Api/Internal/BotCheckinController.php` - suppression check before the place is read; reply decorated with the notice
- `app/Http/Controllers/Api/Internal/BotTowerController.php` - suppression check before the cooldown; reply decorated when non-null
- `app/Services/Lists/ListAppendService.php` - suppression check before the history row is written; `ViewerErasureService` injected
- `resources/help/pages/viewers.md` - documents `!forgetme` as the primary route, email as the alternative
- `resources/help/pages/bot/commands.md` - new `!forgetme` section and a line in the ownership note
- `tests/Feature/ViewerErasureTest.php`, `tests/Feature/ViewerNoticeTest.php` - new files

Bot repository (`overlabels-bot`, committed separately, not pushed):
- `src/commands/forgetme.js` - new handler
- `src/commands/handlers.js` - registered
- `src/overlabelsApi.js` - `postForgetMe()`
- `test/forgetme.test.js` - new, 5 tests

### Claims
- **C1** [code] `POST /api/internal/bot/forgetme` takes no `{login}` segment. Every other bot command endpoint does. The erasure is platform-wide because the viewer is addressing Overlabels, not one channel.
- **C2** [code] `ViewerErasureService::erase()` deletes from `checkins` (`chatter_twitch_id`), `tower_blocks` (`chatter_twitch_id`), `list_append_history` (`chatter_id`), `twitch_events` (`event_data->>'user_id'`) and `external_events` (`raw_payload->>'chatter_id'`), all keyed on the Twitch id alone.
- **C3** [code] `erase()` does NOT modify `option_sets.items`. A streamer's List is their own content and is deliberately out of reach; the reply says to ask them.
- **C4** [code] Controls are blanked only when the key is in `NAME_CONTROL_KEYS`, `source_managed` is true, and the value matches the login or display name exactly after trim and lowercase. A partial match would blank a different viewer whose name contains theirs.
- **C5** [code] Blanking uses `OverlayControl::writeValue()`, not `update()`, because `update()` refuses `source_managed` rows by design.
- **C6** [code] `erase()` records the Twitch id in `viewer_erasures` via `updateOrInsert`, so asking twice is not an error and does not produce a second row.
- **C7** [code] `viewer_erasures` holds a Twitch id and a timestamp and nothing else: no name, no login, no channel, no reason.
- **C8** [code] `BotCheckinController::store()`, `BotTowerController::store()` and `ListAppendService` all consult `isSuppressed()` before writing anything. Checkin and Tower return a null reply; the list append returns `['fired' => false, 'reason' => 'viewer_erased']`.
- **C9** [code] `BotBuiltin::DEFAULTS` declares `forgetme` at `everyone` tier with `owner => OWNER_PLATFORM`, so `BotBuiltin::isEditable('forgetme')` is false and `BotBuiltinsController::update()` aborts 403 on it via the existing `editable()` gate. No change was needed in that controller.
- **C10** [code] The backfill migration inserts a `forgetme` row for every `bot_enabled` user with `insertOrIgnore`, uses `DB::table()` literals and references no Eloquent model. Unlike the tower backfill it does not skip users who own a colliding command name, because builtins outrank every user-owned type in the command map and that is the intended precedence here.
- **C11** [code] `ViewerNoticeService::decorate()` appends the notice at most once per Twitch id per 90 days, gated by `Cache::add()`, which is atomic so two simultaneous commands cannot both append.
- **C12** [code] `decorate()` returns the reply unchanged when the chatter id is null or empty, when the reply is empty, and when appending would take the reply past 480 characters.
- **C13** [code] `BotForgetMeController` calls `ViewerNoticeService::forget()`, so a viewer who returns later is told again rather than treated as already informed.
- **C14** [code] The controller logs per-surface counts only. No viewer name, login or id is written to the log.
- **C15** [test] `ViewerErasureTest` asserts erasure across all five tables, that another viewer's rows survive, that a managed name control is blanked while a streamer's own control holding the same string is not, that suppression is recorded, that the call is idempotent, that it answers when nothing was found, that it points at the streamer for lists, that it 403s without the internal secret, that the DEFAULTS entry is platform-owned and everyone-tier, that it is not editable, and that opting into the bot seeds it.
- **C16** [test] `ViewerNoticeTest` asserts first-time append, silence on the second, per-viewer independence, no decoration without a chatter id, no decoration of an empty reply, no decoration past the length ceiling, and re-notification after `forget()`.
- **C17** [test] The bot's `test/forgetme.test.js` asserts the request goes to a URL with no channel in it, carries exactly the three identity fields, speaks the backend's reply, and speaks a fallback when the API fails. `npm test` in that repo passes 83 tests.

### Unchanged
- `BotCommandMapController` is not in the diff. Builtins are already published to the map as `type: builtin`, so a new one needs no change to the map shape and no `type` discriminator change, which means none of the four-step cross-repo rollout.
- `BotBuiltinsController` is not in the diff. Ownership is derived from `DEFAULTS` via `ownerOf()` / `isEditable()`, so declaring the new builtin `OWNER_PLATFORM` is what freezes it; the write path already refused non-editable commands.
- No consent gate. The reply still works the first time and the notice rides alongside it. A gate would require an acceptance record for every viewer who ever typed in an Overlabels channel, including those who ignored it, which is a larger and more permanent viewer registry than the data it would exist to justify.
- `latest_chat_message` is not in `NAME_CONTROL_KEYS`. It holds a message rather than a name, and matching a chat line against a display name would be a string search over arbitrary text.
- The 90-day prunes added in OL-2609-097 stay. `!forgetme` is the immediate route; the sweeps are the backstop for everyone who never asks.

### Risk
The builtin reaches existing accounts through the backfill migration and new ones through `DEFAULTS`.
Both must deploy together; shipping one without the other leaves the command silent for one group of
accounts with no error anywhere, which has happened twice before on this codebase.

The bot repository change deploys independently. Until it does, `!forgetme` is in the command map and
the bot has no handler for it, so the command is silent rather than broken. Deploy the bot first or
alongside.

A viewer who erases themselves is silently inert for check-ins and chat games afterwards. That is the
intent, and the viewer page says so, but a streamer seeing a regular's `!checkin` do nothing has no
indication why. There is deliberately no message for that: announcing the suppression would undo it.
