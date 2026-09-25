## Audit of OL-2609-052 - feat(bot): the stack and tower builtins, for new opt-ins and backfilled for existing ones

**Audited:** 2026-09-25
**Commit:** 9e0f9058451017b759b7911bd124b5e2c73ee7e6
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Models/BotBuiltin.php:51-52 @9e0f905` - `['command' => 'stack', 'permission_level' => 'everyone']` and `['command' => 'tower', 'permission_level' => 'everyone']` in `DEFAULTS` (line 35); @HEAD lines 82-83 carry both plus `'owner' => self::OWNER_PRODUCT, 'service' => 'tower'` (OL-2609-062, commit 003ded76) |
| C2 | CONFIRMED | `database/migrations/2026_09_10_110000_backfill_tower_builtins.php:48-49 @9e0f905` - `DB::table('users')->where('bot_enabled', true)`; `:74` - `DB::table('bot_builtins')->insertOrIgnore($rows)`; the file imports only `Migration`, `DB` and `Log` and references no `App\Models` class; file identical @HEAD (`git diff 9e0f905 HEAD` empty) |
| C3 | CONFIRMED | `...backfill_tower_builtins.php:34-39 @9e0f905` - `USER_OWNED_TABLES` lists the five named tables; `:95-96` - `where('user_id', ...)->where('command', ...)->exists()` per table, and a hit skips the pair (`:55-58`). All five tables have `user_id` and `command` columns @9e0f905 (`bot_commands` via `2026_05_10_000000_create_bot_expressions_table.php:13-14` renamed in `2026_08_15_160000_...:40`; the other four in their create migrations) |
| C4 | CONFIRMED | `...backfill_tower_builtins.php:83 @9e0f905` - `down()` body is comments only; same @HEAD |
| C5 | UNVERIFIABLE | tagged [unverified]; names a file in the separate bot repo, which is not in this checkout |

### Surface
Complete.

### Findings
None.

### Notes
- No `[test]` claims, so no tests were run.
- Unchanged lines checked: `BotTowerController` and `BotCommandMapController` both exist @9e0f905 under `app/Http/Controllers/Api/Internal/` and neither is in the diff.
- The migration also logs, via `Log::info` (`:77-80 @9e0f905`), how many rows it seeded and a list of skipped `user_id:command` pairs. No claim mentions this, but it sits inside the new file that Surface discloses.
- `BotBuiltin::seedDefaults()` (`app/Models/BotBuiltin.php:59-67 @9e0f905`, not in the diff) does not apply the migration's skip for user-owned commands, so a new opt-in with a custom `stack` still gets the builtin row.
- `stack`/`tower` became `OWNER_PRODUCT` and non-editable in OL-2609-062, which discloses the change.
