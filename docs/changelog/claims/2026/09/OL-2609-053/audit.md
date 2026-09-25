## Audit of OL-2609-053 - feat(overlay): the tower iterable follows tower.updated deltas, and the editor completes it

**Audited:** 2026-09-25
**Commit:** d947b22d139e9a73a1a1664a667798f1e149ec95
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/utils/towerSlots.ts:24 @d947b22` - `BLOCK_FIELDS = ['name', 'login', 'color', 'position', 'offset', 'x', 'record', 'at']`; `app/Models/TowerBlock.php:90-103 @d947b22` - `toBlockArray()` returns exactly those eight keys; same at @HEAD (`towerSlots.ts:24`) |
| C2 | CONFIRMED | `resources/js/utils/towerSlots.ts:33-37 @d947b22` - `Math.floor(Number(value))`, `!Number.isFinite(n) \|\| n < 1` returns `DEFAULT_TOWER_WINDOW` (50); no path returns 1 for NaN/0/negative; unchanged @HEAD |
| C3 | CONFIRMED | `resources/js/utils/towerSlots.ts:74-78 @d947b22` - filters out the equal `position`, sorts ascending by `Number(position)`, `slice(-Math.max(1, cap))`; unchanged @HEAD |
| C4 | CONFIRMED | `resources/js/utils/towerSlots.ts:85-100 @d947b22` - copies only keys not starting with `tower.`, then writes `tower.count` and the window; @HEAD `withTowerSlots()` (line 113) gained an `extras` argument (OL-2609-054 C1), drop behaviour unchanged |
| C5 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:1151-1158 @d947b22` - `event?.cleared ? []`, `event?.cleared ? null : toBlock(...)`, `withTowerSlots(data.value, blocks, height)` with `height` from `event.height`; @HEAD a `cleared` + `toppled` event now appends the culprit and writes count from the top block (`OverlayRenderer.vue:1353-1370 @HEAD`, OL-2609-054 C3) |
| C6 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:895 @d947b22` - `towerWindow.value = clampTowerWindow(json.tower_window)`; `:1045` and `:1050 @d947b22` - `.checkins.updated` and `.tower.updated` both on `channel` in `setupAlertListener()`; same @HEAD (`:1073`, `:1235`) |
| C7 | CONTRADICTED | `resources/js/utils/towerSlots.test.ts @d947b22` passes (see Notes) but is narrower than claimed: `clampTowerWindow` is tested with `undefined`, `'nope'`, `0`, `9999`, `25`, `'10'` and never with a negative number; every `appendBlock` case appends a block whose position is at or above every existing one, so the ascending sort in C3 is never exercised (an unsorted push passes all three cases). Trim-from-front, replace-at-position, C4 and both `toBlock` rejections are asserted |
| C8 | CONFIRMED | `resources/js/utils/tagCompletions.ts:162 @d947b22` - `tower` field list equals C1; `:179` - `{ label: 'tower', alias: 'block' }` in `ITERABLES`; `:425-429` - `!tower` bang with `[[[foreach:tower as block]]]`; same @HEAD |
| C9 (a) | CONFIRMED | `resources/js/utils/tagCompletions.test.ts:159 @d947b22` - `toEqual([... '!checkins', '!tower', '!followed' ...])`; passes |
| C9 (b) | CONTRADICTED | `resources/js/utils/tagCompletions.test.ts:164-167 @d947b22` - the list gaining `tower` asserts only that some bang snippet template contains `[[[foreach:tower as `; the iterable-completion test (`:55-62`, `suggest('[[[foreach:')`) checks `chat`, `channel_followers`, `goals`, `c:list:donors`, `subscribers` and not `tower`. Same @HEAD |

### Surface
Complete.

### Findings
- **F1** compound claim - C9 is two assertions with different verdicts; the half "`tower` is among the completed iterables" is not tested (`tagCompletions.test.ts:55-62 @d947b22` never looks for `tower` in `suggest('[[[foreach:')`); add `tower` to that `arrayContaining` list or restate C9 in a new claim.
- **F2** test narrower than claim - C7 says `towerSlots.test.ts` asserts C2 and C3, but no case passes a negative number to `clampTowerWindow` and no case appends a block below an existing position (`towerSlots.test.ts:17-65 @d947b22`); add both cases or restate C7.
- **F3** scope - `clampTowerWindow()` also caps the window at `DEFAULT_TOWER_WINDOW` (`towerSlots.ts:36 @d947b22`, `Math.min(n, DEFAULT_TOWER_WINDOW)`), so a server `tower_window` above 50 (`OverlayTemplateController.php:982` sends `TowerBlock::WINDOW`) is silently lowered; no claim discloses the ceiling or its coupling to `TowerBlock::WINDOW`.
- **F4** false record line - Risk says "a template without a `foreach:tower` block receives no `tower.*` keys", but `handleTowerUpdated()` (`OverlayRenderer.vue:1142-1159 @d947b22`) has no template check and writes `tower.count` plus the window into `data` for every overlay listening on the channel; restate the Risk in a new claim.

### Notes
- Ran `npm test -- resources/js/utils/towerSlots.test.ts resources/js/utils/tagCompletions.test.ts`: 41/41 passed @HEAD; the same two files at @d947b22 (temporary worktree, removed) passed 39/39.
- C4 and C5 have since been extended by OL-2609-054 (topple hold, `extras` argument); that claim discloses it. No `audit.md` exists for OL-2609-054 yet.
- Later commits OL-2609-109, -119, -120, -122 touched `OverlayRenderer.vue` but not the tower lines checked above.
- Unchanged lines hold: `checkinSlots.ts`, `handleCheckinsUpdated()`, `useTwitchChat`, `chatSlots.ts` are not in the diff; OL-2609-051 C22 exists and names `BotTowerController::normalizeColor()`.
