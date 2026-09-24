## Audit of OL-2609-005 - feat(bot): !checkin builtin verb

**Audited:** 2026-09-25
**Commit:** 1f0cefb5c0c7a9f4946caead320c5ae1a41534bf
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Models/BotBuiltin.php:56 @1f0cefb5` - `['command' => 'checkin', 'permission_level' => 'everyone']` in `DEFAULTS`; `seedDefaults()` is defined on `BotBuiltin` (`:63-70 @1f0cefb5`, `firstOrCreate` per DEFAULTS entry), not on `UserObserver`, which calls it at `app/Observers/UserObserver.php:18,25 @1f0cefb5` on `bot_enabled` becoming true and on create. @HEAD `:81` the entry also carries `'owner' => self::OWNER_PRODUCT, 'service' => 'checkin'` (OL-2609-062) |
| C2 | CONFIRMED | `database/migrations/2026_09_01_150000_backfill_checkin_builtin.php @1f0cefb5` - `DB::table('users')->where('bot_enabled', true)` `:49-50`; `DB::table('bot_builtins')->insertOrIgnore($rows)` `:73`; `USER_OWNED_TABLES` `:35-41` = `bot_commands`, `bot_aliases`, `recipe_chat_triggers`, `list_appenders`, `list_meta_commands`, each checked on `user_id` + `command` `:95` (all five tables have both columns per their create migrations, `bot_commands` via the `bot_expressions` rename in `2026_08_15_160000`); `down()` `:82-87` is comment-only; no `App\Models` import. File unchanged at HEAD (`git log 1f0cefb5..HEAD` on the path is empty) |
| C3 | CONFIRMED (first half) / UNVERIFIABLE (second half) | Compound. Map half: `app/Http/Controllers/Api/Internal/BotCommandMapController.php:49,85-89 @1f0cefb5` - every enabled `bot_builtins` row is emitted with `'type' => 'builtin'`, a pre-existing value, and the file is not in the diff; @HEAD `:89` still `'type' => 'builtin'`. "The bot handler for it ships separately in the bot repo" is a statement about another repository and cannot be checked here - see F1 |
| C4 | UNVERIFIABLE | tagged [unverified]; concerns ship order and the bot repo's dispatcher, neither in this repo |

### Surface
Complete.

### Findings
- **F1** compound claim, half not checkable as [code] - C3 joins an in-repo assertion (the command map serves `checkin` as `type: 'builtin'`, confirmed at `BotCommandMapController.php:89 @1f0cefb5`) with "the bot handler for it ships separately in the bot repo", which no file in this repo can confirm; the reader should split the bot-repo half into its own `[unverified]` claim in any restatement.

### Notes
- Unchanged line confirmed: `BotCheckinController` is not in the diff, and `BotCheckinController.php:70-72 @1f0cefb5` returns `['reply' => null]` when no enabled checkin integration exists (`:75-77 @HEAD`; `store()` also refuses while offline since OL-2609-010).
- `resources/help/pages/bot/commands.md` `!checkin` row @HEAD `:230` adds "only works while the stream is live" (later commit, docs-only path).
- Pre-existing, not in this diff: the same help table lists `!ping` as Everyone while `BotBuiltin::DEFAULTS` gives it `moderator` (`:55 @1f0cefb5`).
- No tests were run: the claim names no test and no claim is tagged [test].
