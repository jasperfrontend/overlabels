## Audit of OL-2609-027 - feat(bot): !ol title set|show|resume|off drives the living title from chat

**Audited:** 2026-09-24
**Commit:** 9b0468397c62fd42aad2bafdcac58ebfaf4a02c0
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Bot/BotChatAdminService.php:72-78 @9b04683` - arms `title:set`, `title:show`, `title:resume`, `title:off`; `default => $subject === 'title' ? 'do you mean !ol title [set, show, resume, off]' : ...`. Same at `:72-76 @HEAD` |
| C2 | CONFIRMED | `app/Services/Bot/BotChatAdminService.php:99-103 @9b04683` - `problem()` non-null returns `'error: '.$problem` before any `setPreference()` (first write at `:105`). Unchanged in shape @HEAD |
| C3 | CONFIRMED | `app/Services/Bot/BotChatAdminService.php:105-112 @9b04683` - sets `enabled` true, `template`, `paused` false, `paused_title` null, `last_error` null, `save()`, then `schedule($owner, 0)`. @HEAD these writes go through `LivingTitleService::apply()` and also null `last_written` (OL-2609-028 C2) |
| C4 | CONFIRMED | `app/Services/Bot/BotChatAdminService.php:174-177 @9b04683` - `enabled` false, `last_written` null (also `paused` false, `paused_title` null); `LivingTitleController.php:66,74 @9b04683` sets the same two on a disabled save. @HEAD written via `apply()` (OL-2609-028) |
| C5 | CONFIRMED | `app/Services/Bot/BotChatAdminService.php:151-165 @9b04683` - returns early when `! enabled` (`:155`) or `! paused` (`:159`); `resume()` at `:163`. Same @HEAD `:153-165` |
| C6 | CONFIRMED | Controller absent from `git show --stat 9b04683`; `app/Http/Controllers/Api/Internal/BotChatAdminController.php:48 @9b04683` `'subject' => 'required|string|max:20'`, `:68` `BotChatGate::hasPermission(self::PERMISSION_LEVEL, ...)`, `:29` `PERMISSION_LEVEL = 'moderator'`. Same @HEAD |
| C7 | CONFIRMED | `tests/Feature/BotChatAdminTest.php:447-464 @9b04683` asserts template (`:459`), `enabled` true (`:460`), reply `toStartWith('title template saved, rendering as: Road to 2K |')` (`:462`), `assertPushed(SyncLivingTitle::class, 1)` (`:463`). `php artisan test --filter=title tests/Feature/BotChatAdminTest.php` @HEAD: 7 passed, 32 assertions; test file unchanged since 9b04683 |
| C8 | CONFIRMED | `tests/Feature/BotChatAdminTest.php:482-491 @9b04683` - dataset `bare` (`''`) and `unknown verb` (`road`) expect the hint, `set without text` expects `usage: !ol title set <text>`; all three dataset rows passed in the run above |
| C9 | CONFIRMED | `tests/Feature/BotChatAdminTest.php:524-532 @9b04683` - `badges => ['vip']`, `assertJson(['queued' => false, 'reason' => 'gate'])`, template `toBe('')`; passed in the run above |
| C10 | UNVERIFIABLE | tagged [unverified]; concerns the separate bot repo, not checkable here |

### Surface
Complete.

### Findings
- **F1** contradiction with the record - `CLAUDE.md:306-307 @9b04683` (unchanged @HEAD) says a foreign-title pause ends only one way: "resume is the streamer's click or a save", and `LivingTitleService::resume()` is documented at `app/Services/LivingTitleService.php:297 @9b04683` as "The streamer's explicit 'carry on'". This change lets any moderator end the pause from chat (`!ol title resume`, `BotChatAdminService.php:163 @9b04683`) and overwrite the template with `!ol title set` (`:105-112`), all behind the uniform `moderator` gate. The claim's Unchanged line says mod access "was decided as acceptable" but never cites or amends the CLAUDE.md rule, and no later claim or CLAUDE.md edit does. Someone should either change rule (2) in CLAUDE.md to say a moderator's `!ol title resume|set` also ends a pause, or restrict those verbs to the broadcaster.

### Notes
- `titleSet()` and `titleOff()` @HEAD write through `LivingTitleService::apply()` and `titleSet()`/`resume()` also clear `last_written`, disclosed by OL-2609-028 (C2). OL-2609-028 says its own C7 test fails on the OL-2609-027 tree.
- `titleOff()` @9b04683 does not clear `last_error` or touch `template`, while `LivingTitleController::update()` clears `last_error` on every save (`:72`). C4 claims only the two fields it names, so this is recorded here and not as a finding.
- The same hunk that added the `title` help topic (`BotChatAdminService.php:435 @9b04683`) also changed the default `!ol help` line (`:436`) to list `title`. Surface covers this as "a `title` help topic".
- Tests were run once @HEAD on a clean tree; they passed with no build step.
