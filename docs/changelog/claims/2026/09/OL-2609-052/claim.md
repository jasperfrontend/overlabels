## OL-2609-052 - feat(bot): the stack and tower builtins, for new opt-ins and backfilled for existing ones

**Shipped:** 2026-09-10
**Commit:** `git log --grep=OL-2609-052`

### Surface
- `app/Models/BotBuiltin.php` - `stack` and `tower` appended to `DEFAULTS` at `everyone`
- `database/migrations/2026_09_10_110000_backfill_tower_builtins.php` - new file, seeds both rows for every `bot_enabled` user

### Claims
- **C1** [code] `BotBuiltin::DEFAULTS` contains `['command' => 'stack', 'permission_level' => 'everyone']` and `['command' => 'tower', 'permission_level' => 'everyone']`.
- **C2** [code] The backfill migration inserts a `bot_builtins` row per (`bot_enabled` user, command) for `stack` and `tower` with `insertOrIgnore`, using `DB::table()` and no Eloquent model.
- **C3** [code] The migration skips a (user, command) pair when any of `bot_commands`, `bot_aliases`, `recipe_chat_triggers`, `list_appenders`, `list_meta_commands` already holds that command for that user.
- **C4** [code] The migration's `down()` is a no-op.
- **C5** [unverified] The bot repo commit shipped alongside registers `stack` and `tower` handlers in `src/commands/handlers.js` posting to `/api/internal/bot/tower/{login}` with `action` `stack` / `status`; its suite passes at 78 tests.

### Unchanged
- `BotTowerController` (OL-2609-051) is the endpoint these verbs reach and is not in the diff.
- `BotCommandMapController` derives the command map from `bot_builtins` rows and needs no change for a new builtin.

### Risk
Every opted-in channel gains `!stack` and `!tower` in its command map on deploy. Both reply nothing until the channel connects the Chat Tower integration, so nothing is heard in chat before a streamer opts in. Between this deploy and the bot deploy that follows it, a `!stack` typed in chat logs a bot-side "no handler" warning and does nothing.
