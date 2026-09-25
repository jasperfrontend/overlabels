## Audit of OL-2609-062 - feat(bot): built-in commands get a switch and a tier, per channel

**Audited:** 2026-09-25
**Commit:** 003ded76da25315ce71085d79f8da9e49273801b
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Models/BotBuiltin.php:63-79 @003ded7` - all 17 entries carry `owner` of `OWNER_USER`, `OWNER_PRODUCT` or `OWNER_PLATFORM`; @HEAD an 18th entry `forgetme` (`app/Models/BotBuiltin.php:76 @HEAD`) also carries one (OL-2609-099) |
| C2 | CONFIRMED | `app/Models/BotBuiltin.php:77-79 @003ded7` - `checkin`/`stack`/`tower` are the only `OWNER_PRODUCT` entries, `service` `checkin`/`tower`/`tower`; same @HEAD |
| C3 | CONFIRMED | `app/Models/BotBuiltin.php:71-72 @003ded7` - `enablecontrols`, `disablecontrols` are the only `OWNER_PLATFORM` entries; @HEAD `forgetme` is a third (`app/Models/BotBuiltin.php:76 @HEAD`, OL-2609-099 C9) |
| C4 | CONTRADICTED (half) | Compound. `ol`, `followage`, `accountage` are `OWNER_USER` at `app/Models/BotBuiltin.php:73-75 @003ded7`: CONFIRMED. "the six control verbs": CONTRADICTED - `DEFAULTS` declares eight control verbs (`control`, `set`, `increment`, `decrement`, `reset`, `enable`, `disable`, `toggle`, lines 63-70 @003ded7), all `OWNER_USER`, matching the eight-row Controls table in `resources/help/pages/bot/commands.md:31-40 @003ded7`; `ping` (line 76 @003ded7) is also `OWNER_USER` and unnamed |
| C5 | CONFIRMED | `git show --stat 003ded7` lists no `database/` path; `ownerOf()` at `app/Models/BotBuiltin.php:113-115 @003ded7` reads `declaration($command)['owner']`, and `declaration()` (lines 97-106) scans `DEFAULTS` by `command` |
| C6 | CONFIRMED | `app/Models/BotBuiltin.php:115 @003ded7` - `?? self::OWNER_USER` when `declaration()` returns null; unchanged @HEAD |
| C7 | CONFIRMED | `app/Http/Controllers/Settings/BotBuiltinsController.php:53-61 @003ded7` - validates `permission_level` `in:` `implode(',', BotBuiltin::PERMISSION_LEVELS)`, `update()` writes `enabled` and `permission_level` only; file unchanged @HEAD |
| C8 | CONFIRMED | `BotBuiltinsController.php:50 @003ded7` aborts 404 on `user_id` mismatch; line 51 aborts 403 on `! editable()` before validation, independent of which fields are sent |
| C9 | CONFIRMED | `BotBuiltinsController.php:27-40 @003ded7` - `BotBuiltin::where('user_id', $user->id)->...->get()->map(...)`; no reference to `DEFAULTS` in the controller |
| C10 | CONFIRMED | `resources/js/pages/settings/bot/builtins/Index.vue:43-45 @003ded7` groups on `owner` `user`/`product`/`overlabels`; `<select>` (116) and `Switch` (126) appear only in the `yours` block; product and platform blocks render text only (150-159, 176-182) |
| C11 | CONFIRMED | `Index.vue:50-60 @003ded7` `save()` calls `router.patch`; select binds `:value="builtin.permission_level"` (117), Switch `:checked="builtin.enabled"` (126), `builtin` iterated from `filtered`, which `useCollectionFilter.ts @003ded7` returns as `items()` / `items().filter(...)` over `props.builtins` - no copy. @HEAD `Index.vue` differs only in the bot-off banner link (OL-2609-069 C16) |
| C12 | CONTRADICTED | `tests/Feature/Settings/BotBuiltinsPageTest.php:46-53 @003ded7` (identical @HEAD, passes): asserts row count equals `count(BotBuiltin::DEFAULTS)` (not that each command appears), `owner` and `editable` for `followage`, `stack`, `enablecontrols`, and `service` for `stack` only; `service` is not asserted for `followage` or `enablecontrols` |
| C13 | CONFIRMED | `BotBuiltinsPageTest.php:56-66` - PATCH `followage` with `enabled=false`, `moderator`; asserts both on `fresh()`. Passed |
| C14 | CONFIRMED | `BotBuiltinsPageTest.php:70-78` - `followage` present in `/api/internal/bot/commands` channel entries before, absent after switching off. Passed |
| C15 | CONFIRMED | `BotBuiltinsPageTest.php:80-90` - `stack` PATCH `assertForbidden()`, `enabled` true, tier `everyone`. Passed |
| C16 | CONFIRMED | `BotBuiltinsPageTest.php:92-102` - `enablecontrols` PATCH `assertForbidden()`, tier `broadcaster` (and `enabled` true). Passed |
| C17 | CONFIRMED | `BotBuiltinsPageTest.php:104-117` - other user's `followage` PATCH `assertNotFound()`, `enabled` still true (the only field re-read; the PATCH sends `enabled=false`, so any write would flip it). Passed |
| C18 | CONFIRMED | `BotBuiltinsPageTest.php:119-126` - `permission_level=partner` gives `assertSessionHasErrors('permission_level')`, tier unchanged. Passed |
| C19 | CONFIRMED | `BotBuiltinsPageTest.php:131-145` - every entry has `owner` in the three constants; product `service` `toBeIn(ExternalServiceRegistry::services())`; non-product `not->toHaveKey('service')`. Passed |
| C20 | UNVERIFIABLE | tagged [unverified] |

Tests run: `php artisan test --filter=BotBuiltinsPageTest` @HEAD - 8 passed, 90 assertions. The test file has no commits after 003ded7.

### Surface
Complete.

### Findings
- **F1** inaccurate record - C4 says "the six control verbs" are `OWNER_USER`, but `app/Models/BotBuiltin.php:63-70 @003ded7` declares eight (`control` through `toggle`), and `ping` (line 76) is also `OWNER_USER` and named nowhere in C1-C4; a new claim should state the full `OWNER_USER` set (12 commands @003ded7).
- **F2** test narrower than claim - C12 says `BotBuiltinsPageTest` asserts one entry per `DEFAULTS` command and `service` for `followage`, `stack` and `enablecontrols`; `tests/Feature/Settings/BotBuiltinsPageTest.php:46-53 @003ded7` asserts only a count match and `service` for `stack` only. Either restate the claim or add the assertions (command set equality; `service` null for `followage` and `enablecontrols`).
- **F3** scope - the diff also rewrites the `seedDefaults()` docblock at `app/Models/BotBuiltin.php:83 @003ded7` ("Idempotent —" to "Idempotent -"), which neither the Surface line for that file nor any claim mentions; comment-only, record it or leave it.

### Notes
- @HEAD `DEFAULTS` has an 18th entry, `forgetme`, `OWNER_PLATFORM` (OL-2609-099 C9), so C3's "only" no longer holds at HEAD. OL-2609-099 discloses the addition but does not cite OL-2609-062 C3 inline; that is for 099's audit.
- `CLAUDE.md` @HEAD still names only `!enablecontrols`/`!disablecontrols` as `OWNER_PLATFORM` in the bullet this commit added; it omits `forgetme`.
- Unchanged lines checked: `BotCommandMapController` (`app/Http/Controllers/Api/Internal/BotCommandMapController.php:49 @003ded7`), `seedDefaults()` body (writes `permission_level`, `enabled` only, lines 86-94), and the four `DEFAULTS` readers (read `command` only) are not in the diff. The bot-repo line cannot be checked here.
