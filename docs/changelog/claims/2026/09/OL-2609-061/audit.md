## Audit of OL-2609-061 - fix(tower): the per-viewer !stack cooldown goes down to 1s and starts at 5s

**Audited:** 2026-09-25
**Commit:** 49f31f43ba284fb4a99ab11dbb1089549ddd72d9 (only commit carrying the trailer)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/Settings/TowerIntegrationController.php:59 @49f31f4` - `'cooldown_seconds' => 'nullable\|integer\|min:1\|max:600'`; same at `:59 @HEAD` |
| C2 | CONFIRMED | `TowerIntegrationController.php:73 @49f31f4` - `(int) ($validated['cooldown_seconds'] ?? 5)`; same at `:73 @HEAD` |
| C3 | CONFIRMED | `TowerIntegrationController.php:44 @49f31f4` - `show()` sends `(int) ($settings['cooldown_seconds'] ?? 5)`; same at `:44 @HEAD` |
| C4 | CONFIRMED | `app/Http/Controllers/Api/Internal/BotTowerController.php:94 @49f31f4` - `$cooldown = max(1, (int) ($settings['cooldown_seconds'] ?? 5));`; unchanged expression at `:104 @HEAD` (line shift from OL-2609-099) |
| C5 | CONFIRMED | `BotTowerController.php:96 @49f31f4` - `Cache::add("tower:cooldown:{$user->id}:{$data['chatter_id']}", 1, $cooldown)`, `$cooldown` floored at 1 on `:94`; same at `:104-106 @HEAD` |
| C6 | CONFIRMED | `resources/js/pages/settings/integrations/tower.vue:161 @49f31f4` - `<Input id="cooldown_seconds" ... type="number" min="1" max="600" ...>`; same @HEAD |
| C7 | CONFIRMED | `tower.vue:40 @49f31f4` - `cooldown_seconds: props.integration.cooldown_seconds ?? 5`; same @HEAD |
| C8 | CONFIRMED | `tests/Feature/BotTowerTest.php:161-174 @49f31f4` - `connectTower(... ['cooldown_seconds' => 1])`, second `postStack()` asserts `['reply' => null]` and `TowerBlock::count()` 1, then `travel(2)->seconds()` and count 2. File unchanged to HEAD. `php artisan test --filter='BotTowerTest\|TowerCooldownSettingTest'` @HEAD: 21 passed |
| C9 | CONFIRMED | `tests/Feature/TowerCooldownSettingTest.php` "a one-second cooldown is accepted and stored" - `assertRedirect()->assertSessionHasNoErrors()`, stored `toBe(1)`; passed in the run above |
| C10 | CONFIRMED | same file, "zero is refused..." - `assertSessionHasErrors('cooldown_seconds')` and tower `ExternalIntegration` `exists()` `toBeFalse()`; passed |
| C11 | CONFIRMED | same file, "omitting the cooldown stores five seconds" - posts only `tower_lifetime`, stored `toBe(5)`; passed |
| C12 | CONFIRMED | same file, "the settings page reports five..." - `GET /settings/integrations/tower`, `where('integration.cooldown_seconds', 5)`; `beforeEach` creates no tower row; passed |
| C13 | UNVERIFIABLE | tagged [unverified] (fail-first run against a tree that no longer exists) |
| C14 | UNVERIFIABLE | tagged [unverified] (bot repo, out of tree) |

### Surface
Complete.

### Findings
- **F1** contradiction with the record (uncited) - the change moves the `!stack` cooldown floor from 5 to 1 and the default from 30 to 5, reversing what OL-2609-051 C8 recorded ("silently drops a chatter inside `settings.cooldown_seconds` (min 5, default 30)", `docs/changelog/claims/2026/09/OL-2609-051/claim.md:47`), but no line of this claim cites OL-2609-051. A later claim should state "supersedes OL-2609-051 C8" so a reader of 051 is pointed here.
- **F2** scope - the `CLAUDE.md` hunk (`CLAUDE.md:+417-418 @49f31f4`, now `CLAUDE.md:431-435 @HEAD`) says "`COMMAND_COOLDOWN_MS` is 1000 on the bot role in its `config/deploy.yml`", and the test docblock (`tests/Feature/TowerCooldownSettingTest.php:11 @49f31f4`) says "the bot's channel-wide window is 1s in production". No claim records that fact, not even as [unverified]. C14 gives only the 5000 default, and Risk says the 1s window is still waiting on a bot deploy. It should be recorded as an [unverified] claim, or the CLAUDE.md line should be brought in line with Risk.

### Notes
- Tests were run at HEAD, not at the shipped revision. Both test files are byte-identical between 49f31f4 and HEAD (`git diff --stat` empty). `public/build` was present.
- `BotTowerController::store()` @HEAD gains an erasure-suppression early return before the cooldown (OL-2609-099). The cooldown expression and `Cache::add` did not change.
- Unchanged lines hold @49f31f4: `BotCheckinController.php:85` is `max(5, ... ?? 30)`, `CheckinIntegrationController.php:59/:44/:86` have `min:5` / `?? 30`, and no `TowerPhysics`, `TowerPhysicsTest` or `TowerBlock` path is in the diff.
- OL-2609-051's audit (F2) and OL-2609-062 C-Unchanged already cite this change by ID.
