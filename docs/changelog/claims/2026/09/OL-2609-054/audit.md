## Audit of OL-2609-054 - feat(overlay): a topple holds the fallen blocks for three seconds with tower.falling set, so a template can bring them down

**Audited:** 2026-09-25
**Commit:** b9c3f93efa721209d3d77522c2e8c6edcaedd58b
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/utils/towerSlots.ts:120-134 @b9c3f93` - non-`tower.` keys copied, then `tower.count` and blocks, then `extras` written as `tower.<key>`; `extras` defaults to `{}` (`:117`), so a call without it leaves no extra keys after the drop. Same @HEAD (no diff b9c3f93..HEAD on this file) |
| C2 | CONFIRMED | `resources/js/utils/towerSlots.ts:93-104 @b9c3f93` - returns `toppled_by`/`toppled_login`/`toppled_height` from `by`/`login`/`height`, `toppled_record` `'1'`/`''` from `record_tower`, `falling` `'1'`/`''`; non-object input becomes `{}` so every field is `''`. Keys match the server payload `app/Services/Tower/TowerService.php:109-114 @b9c3f93`. Same @HEAD |
| C3 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:1169-1185 @b9c3f93` - on `cleared && toppled`, `blocksFromData()` + `appendBlock(culprit)`, `withTowerSlots(..., toppleSlots(event.toppled, true))`, `setTimeout(..., TOPPLE_HOLD_MS)`; `TOPPLE_HOLD_MS = 3000` at `towerSlots.ts:87 @b9c3f93`. Identical @HEAD (`OverlayRenderer.vue:1336-1384`) |
| C4 | CONFIRMED | `OverlayRenderer.vue:1182 @b9c3f93` - timer writes `withTowerSlots(data, [], 0, toppleSlots(event.toppled, false))`; the next plain stack writes `withTowerSlots(data.value, blocks, height)` with no extras (`:1198`), which drops them. Same @HEAD |
| C5 | CONFIRMED | `OverlayRenderer.vue:1163-1167 @b9c3f93` - pending `toppleHold` is cleared and data rewritten with `[]`, height 0, `{ ...toppleSlotsFromData(data), falling: '' }` before either branch runs. Same @HEAD |
| C6 | CONFIRMED | `OverlayRenderer.vue:1191-1198 @b9c3f93` - `cleared` without `toppled` falls through to `blocks = []`, no block appended, `withTowerSlots(data.value, blocks, height)` with no extras. Same @HEAD |
| C7 | CONFIRMED | `resources/js/utils/towerSlots.test.ts @b9c3f93` - "writes extras under the prefix and drops them..." asserts extras written, overwritten on a later write (`falling` `'1'` -> `''`), and absent after a call without extras (C1); asserts `toppled_by`, `toppled_height`, `toppled_record`, `falling`, and `toppled_login` via `toppleSlotsFromData` (C2). The malformed case tested is `null` only. `npm test -- resources/js/utils/towerSlots.test.ts`: 12 passed. Test file unchanged @HEAD |

### Surface
Complete.

### Findings
- **F1** contradiction with the record - `OverlayRenderer.vue:1169-1176 @b9c3f93` appends `event.block` on a `cleared` topple and writes `tower.count` from the culprit's `position` instead of `event.height`, which reverses OL-2609-053 C5 ("starts from an empty window when `event.cleared` is true, does not append `event.block` in that case, and writes `tower.count` from `event.height`") for the topple case, and this claim does not cite OL-2609-053 C5 inline (its only OL-2609-053 reference is the Unchanged line about signatures); a follow-up claim should record that it narrows OL-2609-053 C5 to non-topple clears.

### Notes
- `TowerUpdated`, `appendBlock()`, `blocksFromData()` and `clampTowerWindow()` (Unchanged) are not modified in the diff; `app/Events/TowerUpdated.php` is not in `git show --stat`.
- `handleTowerUpdated()` and `towerSlots.ts` are byte-identical between b9c3f93 and HEAD; no drift.
- OL-2609-056 C3 later restates `TOPPLE_HOLD_MS` = 3000; consistent with this claim.
