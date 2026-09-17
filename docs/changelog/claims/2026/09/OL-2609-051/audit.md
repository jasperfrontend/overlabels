## Audit of OL-2609-051 - feat(tower): the Chat Tower integration - !stack physics, blocks, controls, broadcast and settings page

**Audited:** 2026-09-17
**Commit:** 6abb44b8dc1cc0b005bbfafab57465a7792f53f9
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Tower/TowerPhysics.php:66-76` - `$u` clamped to `[0, 0.999999]`; `left` = `-(AIMED_MIN + u*(AIMED_MAX-AIMED_MIN))`, `right` = the negation, default = `-UNAIMED_MAX + u*2*UNAIMED_MAX`, rounded to 3 dp |
| C2 | CONFIRMED | `TowerPhysics.php:88-96` - `topples()` is `room() < 0`, `room()` is `round(FALL_LINE - (abs($xTop) + swayAmplitude($height)), 3)`; equivalence holds up to the 3 dp rounding |
| C3 | CONFIRMED | `tests/Unit/TowerPhysicsTest.php` - six tests cover `normalizeAim`, `offsetFor` unaimed/aimed bounds (C1), `swayAmplitude`, `room`/`topples` (C2), `leanSide`; `php artisan test --filter=TowerPhysicsTest` 6 passed, 2028 assertions |
| C4 | CONFIRMED | `app/Services/Tower/TowerService.php:52` `Cache::lock("tower:stack:{$user->id}", 5)->block(3, ...)`; `:53-56` reads `topFor`, adds `offsetFor`; `:96` deletes every row on topple and returns before `:113` `TowerBlock::create` |
| C5 | CONFIRMED | `TowerService.php:106-111` topple: `TowerUpdated::dispatch($user->twitch_id, $block, 0, true, [...])`; `:141` stack: `TowerUpdated::dispatch($user->twitch_id, $block, $position)` (`cleared` defaults false, `app/Events/TowerUpdated.php:38`) |
| C6 | CONFIRMED | `TowerService.php:61` `$newRecord = ! $topples && $position > $recordHeight`, written as `record` at `:122`; `:137` `'first_record_block' => $newRecord && ! ($top?->record ?? false)` |
| C7 | CONFIRMED | `TowerService.php:126-128` calls `writeRecordRoster` when `$newRecord`; `:248-272` looks up `OptionSet` slug `tower_record`, builds `$names[$login] ??= display_name` bottom to top, `ListItems::freshFromValues`, `ListUpdated::dispatchFor` |
| C8 | CONFIRMED (shipped) | Checked `git show 6abb44b8:app/Http/Controllers/Api/Internal/BotTowerController.php`: `:70-72` `reply: null` without enabled integration; `:90` `max(5, ... ?? 30)` and `:92` `Cache::add` cooldown; `:104` live gate returns the spoken refusal before `stack()`. The tree NOW has `max(1, ... ?? 5)` (`BotTowerController.php:94`, changed by OL-2609-061 / 49f31f43) |
| C9 | CONFIRMED | `BotTowerController.php` `replyFor()`: topple string, `first_record_block && $record > 0` string, `$height % 10 === 0` string, else `null` |
| C10 | CONFIRMED | `BotTowerController.php:78-83` - `status` branch runs `Cache::add("tower:status:...", 1, STATUS_COOLDOWN)` (`:27` = 10) and returns `$this->tower->status($user)` before the live gate at `:104` |
| C11 | CONFIRMED | `BotTowerController.php` `runPipeline()`: `ExternalEvent::create([... 'service' => 'tower' ...])`, `EventMeter::record`, `ExternalEventStored::dispatch`, `getControlUpdates` + `beforeControlUpdates` + `applyUpdates`, `alertService->dispatch`, `last_received_at` |
| C12 | CONFIRMED | `app/Services/External/Drivers/TowerServiceDriver.php:104-118` eleven entries; `:35-39` `PER_STREAM_CONTROL_KEYS` = the three named keys |
| C13 | CONFIRMED | `TowerServiceDriver.php:155-175` - returns unless `getEventType() === 'stack'`; writes each key only when `$height > (int) currentControlValue(...)` |
| C14 | CONFIRMED | `TowerServiceDriver.php:51-54` `verifyRequest()` returns `false` |
| C15 | CONFIRMED | `app/Services/StreamSessionService.php` `resetTowerControls()`: `whereIn('key', TowerServiceDriver::PER_STREAM_CONTROL_KEYS)`, `$control->resetValue($resetValue)` with `$preservedAt` passed to the broadcast; `clear()` called only when an enabled `tower` integration exists and `settings['tower_lifetime'] ?? 'per_stream'` is `per_stream` |
| C16 | CONFIRMED | `TowerService.php:178-210` `clear()`: deletes blocks, `resetValue()` over `STANDING_CONTROLS` (`tower_height`, `tower_lean`, `tower_room`), `TowerUpdated::dispatch($user->twitch_id, null, 0, true)` - `toppled` defaults null |
| C17 | CONFIRMED | `app/Http/Controllers/OverlayTemplateController.php` - `in_array('tower.count', $allowlist, true)` gate; `buildTowerData()` emits `tower.count` = `heightFor()` (uncapped) and `tower.{$i}.{$field}` over `windowFor($user, TowerBlock::WINDOW)` (which reverses a `orderByDesc->limit` query, `app/Models/TowerBlock.php:68-76`); `'tower_window' => TowerBlock::WINDOW` in the payload |
| C18 | CONTRADICTED (one half) | `tests/Feature/BotTowerTest.php` - 17 passed, 110 assertions. Asserts C5 (`:204`, `:275`), C6/C7 (`:320`, `:381`), C8, C9, C10 (`:408-431`), C15/C16 via `openSession()` (`:385-411`). For C4 it asserts row creation and deletion only; nothing asserts the `Cache::lock` serialization (`Cache::lock` does not appear in the file) |
| C19 | CONFIRMED | `tests/Feature/TowerOverlayRenderTest.php` `the window keeps the top of a tall tower and never the count` - seeds `WINDOW + 5`, asserts `tower.0.position` = `6`, last = `WINDOW + 5`, no `tower.WINDOW.position`; 3 passed |
| C20 | CONFIRMED | `tests/Feature/IntegrationProvisioningTest.php` `connecting tower provisions exactly the controls its driver declares` - `serviceControlKeys($user, 'tower')` equals `expectedControlKeys('tower')` after POST; 14 passed |
| C21 | CONFIRMED | `app/Http/Controllers/Settings/TowerIntegrationController.php` `disconnect()`: `deprovision($user, 'tower')`, `$integration->delete()`, no `TowerBlock` query |
| C22 | CONFIRMED | `BotTowerController.php` `normalizeColor()`: `preg_match('/^#[0-9a-fA-F]{6}$/', $raw) ? strtoupper($raw) : null` |

### Surface
Complete. All 31 non-exempt paths in `git show --stat` appear in Surface; no phantom paths.

### Findings
- **F1** test narrower than claim - C18 says `BotTowerTest` asserts C4, but C4's `Cache::lock("tower:stack:{$user->id}")` half has no assertion in `tests/Feature/BotTowerTest.php` (only the create / delete-all halves are asserted); either drop the lock from C4's coverage in C18 or add an assertion for it.
- **F2** claim superseded in the tree - C8's `min 5, default 30` is true of the shipped commit but not of the tree today: `BotTowerController.php:94` is `max(1, ... ?? 5)` and `TowerIntegrationController.php:59` validates `min:1`, changed by OL-2609-061 (49f31f43). Not a defect of this claim; a reader auditing against HEAD should read OL-2609-061 alongside.

### Notes
Unchanged section verified: `BotBuiltin`, `User`, `OverlayRenderer.vue`, `tagCompletions.ts`, `CheckinServiceDriver`, `BotCheckinController` are not in the diff. Migration uses `Schema::create('tower_blocks')` with no model references. No em dashes in the diff. No CLAUDE.md "do not" contradicted; the CLAUDE.md Chat Tower section matches the shipped shape.
