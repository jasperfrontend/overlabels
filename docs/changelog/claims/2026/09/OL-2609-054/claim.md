## OL-2609-054 - feat(overlay): a topple holds the fallen blocks for three seconds with tower.falling set, so a template can bring them down

**Shipped:** 2026-09-10
**Commit:** `git log --grep=OL-2609-054`

### Surface
- `resources/js/utils/towerSlots.ts` - `TOPPLE_HOLD_MS`, `toppleSlots()`, `toppleSlotsFromData()`, `withTowerSlots()` gains an `extras` argument
- `resources/js/utils/towerSlots.test.ts` - extras round-trip and malformed-payload cases
- `resources/js/components/OverlayRenderer.vue` - `handleTowerUpdated()` holds a toppled tower before clearing it

### Claims
- **C1** [code] `withTowerSlots()` writes each `extras` entry under the `tower.` prefix after the drop, and a later call without `extras` removes them.
- **C2** [code] `toppleSlots(toppled, falling)` returns `toppled_by`, `toppled_login`, `toppled_height`, `toppled_record` from the broadcast's `toppled` array and `falling` as `'1'` or `''`, degrading to empty strings on a malformed payload.
- **C3** [code] On a `tower.updated` event with `cleared` and `toppled`, `handleTowerUpdated()` appends the culprit block to the current window, writes `tower.falling = '1'` with the topple slots, and schedules the clear `TOPPLE_HOLD_MS` (3000) later.
- **C4** [code] The scheduled clear writes an empty window, height 0, and the topple slots with `falling = ''`, so `tower.toppled_by` and `tower.toppled_height` survive the clear until the next stack.
- **C5** [code] Any `tower.updated` event arriving while the hold timer is pending cancels the timer and settles the rubble (empty window, `falling = ''`) before it is applied.
- **C6** [code] A `cleared` event without `toppled` (go-live reset, settings reset) clears immediately and carries no topple slots.
- **C7** [test] `towerSlots.test.ts` asserts C1 and C2.

### Unchanged
- `TowerUpdated::broadcastWith()` (OL-2609-051) already carries `block`, `cleared` and `toppled` on a topple; the server side is not in the diff.
- `appendBlock()`, `blocksFromData()` and `clampTowerWindow()` (OL-2609-053) keep their signatures; the hold is layered on top of them in the renderer.

### Risk
For three seconds after a topple, `tower.count` and the block slots show the fallen tower with `tower.falling` set. A template that ignores `tower.falling` shows the blocks standing for those three seconds before they vanish.
