## OL-2609-027 - feat(bot): !ol title set|show|resume|off drives the living title from chat

**Shipped:** 2026-09-07
**Commit:** `git log --grep=OL-2609-027`

### Surface
- `app/Services/Bot/BotChatAdminService.php` - `LivingTitleService` injected; four `title:*` arms in `dispatch()`, a `title`-specific default, four private methods, a `title` help topic
- `app/Services/LivingTitleService.php` - `hasScope()` added
- `tests/Feature/BotChatAdminTest.php` - five `title` tests added
- `resources/help/pages/bot/commands.md` - four `!ol title` rows in the subverb table; `title` added to the `!ol help` row

### Claims
- **C1** [code] `BotChatAdminService::dispatch()` matches `title:set`, `title:show`, `title:resume` and `title:off`, and returns exactly `do you mean !ol title [set, show, resume, off]` for any other action on subject `title`.
- **C2** [code] `titleSet()` returns a reply starting `error: ` and saves nothing when `LivingTitleService::problem()` returns a sentence for the payload.
- **C3** [code] `titleSet()` with a non-empty accepted payload sets `living_title.enabled` true, stores the payload as `living_title.template`, clears `paused`, `paused_title` and `last_error`, and calls `LivingTitleService::schedule($owner, 0)`.
- **C4** [code] `titleOff()` sets `enabled` false and `last_written` null, mirroring `LivingTitleController::update()` when saving disabled.
- **C5** [code] `titleResume()` calls `LivingTitleService::resume()` only when `enabled` is true and `paused` is true.
- **C6** [code] `BotChatAdminController` is not in the diff; the `title` subject passes its existing `subject` validation (`string|max:20`) and its uniform moderator-or-broadcaster gate.
- **C7** [test] `BotChatAdminTest` "title set saves the template, switches it on and renders at once" asserts the template is stored, `enabled` is true, the reply starts `title template saved, rendering as: Road to 2K |`, and `SyncLivingTitle` was pushed once.
- **C8** [test] `BotChatAdminTest` "title set without text, a bare title, and an unknown verb each hint at the verbs" pins C1's hint for an empty action and for `road`, and the usage line for `set` with no text.
- **C9** [test] `BotChatAdminTest` "title obeys the same mod-or-broadcaster gate as every other !ol verb" posts as a VIP and asserts `{queued: false, reason: gate}` with the template unchanged.
- **C10** [unverified] The bot at `overlabels-bot` forwards an unknown `!ol` subject as `help`, so `!ol title` reaches this code only after the bot learns the `title` subject (its own commit; app first, bot second, nothing existing changes so no compatibility alias is needed).

### Unchanged
- `LivingTitleService::problem()`, `schedule()`, `resume()` and `preview()` are the same methods the settings page calls and are not in the diff; the chat verbs are a second caller, not a second implementation.
- `BotBuiltin::DEFAULTS` is not in the diff: `!ol` is already a builtin (`ol`, moderator), and `title` is a subverb of it, so no new builtin and no backfill migration.
- `BotChatAdminController::PERMISSION_LEVEL` stays `moderator` for every subject; a mod setting the title was decided as acceptable.
