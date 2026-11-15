## OL-2611-015 - feat(tower): test mode, so a streamer can try !stack offline without counting anything

**Shipped:** 2026-11-15
**Commit:** `git log --grep=OL-2611-015`

### Surface
- `app/Http/Controllers/Api/Internal/BotTowerController.php` - offline test-mode branch in `store()`, `TEST_MODE_CONTROLS`, new `runTestPipeline()`
- `app/Services/Tower/TowerService.php` - `stack()` takes `bool $testing`; a test block is never a record block
- `app/Http/Controllers/Settings/TowerIntegrationController.php` - `test_mode` prop in `show()`, new `setTestMode()`, class doc comment updated
- `routes/settings.php` - `PATCH settings/integrations/tower/test-mode`, named `settings.integrations.tower.test-mode`
- `resources/js/pages/settings/integrations/tower.vue` - test mode switch and copy, shown when connected
- `tests/Feature/BotTowerTest.php` - six test-mode tests

### Claims
- **C1** [code] `BotTowerController::store()` treats a stack as a test when `StreamSessionService::isLive($user)` is false and the tower `ExternalIntegration` has `test_mode` true.
- **C2** [code] A test stack uses a per-viewer `Cache::add` cooldown of 1 second regardless of `settings.cooldown_seconds`.
- **C3** [code] An offline stack with `test_mode` false still returns the "only stands while the stream is live" reply and stacks nothing.
- **C4** [code] A test stack calls `runTestPipeline()` and never `runPipeline()`.
- **C5** [code] `runTestPipeline()` creates no `ExternalEvent`, does not call `EventMeter::record()`, does not dispatch `ExternalEventStored`, does not call `beforeControlUpdates()` and does not write `last_received_at`.
- **C6** [code] `runTestPipeline()` applies only the control updates whose keys are in `BotTowerController::TEST_MODE_CONTROLS` (`tower_height`, `tower_lean`, `tower_room`, `last_stacker_name`, `last_topple_by`, `last_topple_height`).
- **C7** [code] `runTestPipeline()` calls `ExternalAlertService::dispatch()` with no stored event.
- **C8** [code] `TowerService::stack()` sets `$newRecord` false when `$testing` is true, so the block's `record` is false and `writeRecordRoster()` is not called.
- **C9** [code] `TowerIntegrationController::setTestMode()` calls `TowerService::clear()` only when `test_mode` goes from true to false.
- **C10** [test] `BotTowerTest` "in test mode an offline stack lands but nothing is counted or stored" asserts one block, `tower_height` 1, `last_stacker_name` set, the four count and record controls at 0, no `ExternalEvent` and a null `last_received_at`.
- **C11** [test] `BotTowerTest` "in test mode the cooldown is one second whatever the setting says" asserts a second stack inside one second is dropped and one after two seconds lands, with `cooldown_seconds` 30.
- **C12** [test] `BotTowerTest` "a test-mode tower past the record sets no record and leaves the roster alone" asserts `tallest_tower_record` stays 2, no record blocks, and the `tower_record` list unchanged.
- **C13** [unverified] The test in C12 failed with the `! $testing` guard removed from `TowerService::stack()`, and passed with it restored.
- **C14** [test] `BotTowerTest` "a test-mode topple shows on the overlay controls without counting" asserts `last_topple_by` and `last_topple_height` are written and `topples_this_stream` stays 0.
- **C15** [test] `BotTowerTest` "test mode is ignored while live, so a live stack counts as normal" asserts the streamer's 30-second cooldown applies and the stack is counted and stored.
- **C16** [test] `BotTowerTest` "switching test mode off knocks the test tower down, switching it on does not" asserts the blocks survive the switch on and are gone after the switch off.
- **C17** [code] The switch in `tower.vue` sends `router.patch` to `/settings/integrations/tower/test-mode` with the negated `integration.test_mode`, and uses the same yellow classes as `TestModeToggle.vue`.

### Unchanged
- `TestModeToggle.vue` is not used and not modified: its copy promises duplicate-event acceptance and a donation-total reset, neither of which applies to the tower.
- The `test_mode` column on `external_integrations` already existed for the donation services; no migration is in the diff.
- `TowerService::clear()` is called, not modified; it resets `tower_height`, `tower_lean` and `tower_room` and leaves the `last_*` controls alone.

### Risk
Test blocks are real `tower_blocks` rows. A persistent tower standing when test mode is switched on
has the test blocks stacked onto it, and switching test mode off clears the whole tower. The
`last_stacker_name`, `last_topple_by` and `last_topple_height` values written in test mode remain
until the next real stack or topple.
