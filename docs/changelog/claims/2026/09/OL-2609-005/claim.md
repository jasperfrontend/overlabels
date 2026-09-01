## OL-2609-005 - feat(bot): !checkin builtin verb

**Shipped:** 2026-09-01
**Commit:** `git log --grep=OL-2609-005`

### Surface
- `app/Models/BotBuiltin.php` - `checkin` (everyone tier) added to `DEFAULTS`
- `database/migrations/2026_09_01_150000_backfill_checkin_builtin.php` - new: backfill for already-opted-in users
- `resources/help/pages/bot/commands.md` - `!checkin` row in the Miscellaneous table

### Claims
- **C1** [code] `BotBuiltin::DEFAULTS` contains `['command' => 'checkin', 'permission_level' => 'everyone']`, so `UserObserver`'s `seedDefaults()` gives every future bot opt-in the verb.
- **C2** [code] The backfill migration inserts a `bot_builtins` row per `bot_enabled` user via literal `DB::table` names and `insertOrIgnore`, skipping users who already claimed `checkin` in any of the five user-owned command tables, with an empty `down()`.
- **C3** [code] The command map (`BotCommandMapController`, not in this diff) serves the new verb as `type: 'builtin'` - no cross-repo contract change; the bot handler for it ships separately in the bot repo.
- **C4** [unverified] Ship order is app-first on purpose: with this deployed and the old bot running, the verb sits unread in the map (unknown verbs are silently dropped by the bot's dispatcher); the bot-repo commit adding the handler follows immediately after.

### Unchanged
- `BotCheckinController` and the checkin integration (OL-2609-004) are not in this diff; this change only makes the verb exist in the command map. Channels without the integration connected keep a silent `!checkin` (the endpoint replies null by design).

### Risk
Streamers who already own a custom command/alias named `checkin` are skipped by the backfill and
keep their own command; deleting it hands them the builtin.
