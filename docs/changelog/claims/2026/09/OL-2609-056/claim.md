## OL-2609-056 - docs(claude): the Chat Tower section

**Shipped:** 2026-09-10
**Commit:** `git log --grep=OL-2609-056`

### Surface
- `CLAUDE.md` - a `Chat Tower` section added before the donation integration controllers section

### Claims
- **C1** [code] `CLAUDE.md` contains a heading `### Chat Tower (Product #3, shipped Sept 10th 2026, OL-2609-051..055)` and no other file changed.
- **C2** [code] Every constant the section quotes matches `App\Services\Tower\TowerPhysics`: `UNAIMED_MAX` 1.0, `AIMED_MIN` 0.45, `AIMED_MAX` 1.6, `SWAY_PER_BLOCK` 0.055, `FALL_LINE` 4.0.
- **C3** [code] `TOPPLE_HOLD_MS` in `resources/js/utils/towerSlots.ts` is 3000 and `TowerBlock::WINDOW` is `User::FOREACH_CAP_MAX`, as the section states.
