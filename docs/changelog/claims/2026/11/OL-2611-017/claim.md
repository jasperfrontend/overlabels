## OL-2611-017 - docs(claims): record the show_hint and roster changes OL-2611-016 left out

**Shipped:** 2026-11-15
**Commit:** `git log --grep=OL-2611-017`

Remedies OL-2611-016 audit F1, F2.

### Surface
- `docs/changelog/claims/2026/11/OL-2611-016/remedy.md` - remedy of the OL-2611-016 audit

### Claims
- **C1** [code] `resources/recipes/chat-tower/tower.md` declares `show_hint` as a boolean control labelled "Show how to play", default `1` (adds to OL-2611-016, audit F1).
- **C2** [code] The only `[[[if:c:show_hint]]]` in `tower.md`'s html wraps the `.intro` panel (eyebrow, "Type !stack in chat" heading, how-to-play line); no `!stack` chip, `.legend` or `.hint` element depends on it (adds to OL-2611-016, audit F1).
- **C3** [code] The `c:show_hint` control detail in `tower.md` describes it as "The how-to-play text on the left" (adds to OL-2611-016, audit F1).
- **C4** [code] The record roster in `tower.md` renders a `c:list:tower_record` entry only while `loop.index <= 4`; `loop.index` is zero-based (`renderTemplate.test.ts` "exposes loop.index, loop.first, loop.last and loop.count"), so it lists at most five names (adds to OL-2611-016, audit F2; corrects the four-name count in audit F2).
- **C5** [code] The roster's count line "`[[[c:list:tower_record:count]]]` builders in all" renders only when `c:list:tower_record:count > 5` (adds to OL-2611-016, audit F2).
- **C6** [code] Per C4 and C5, a record held by exactly five builders shows all five names and no count line; the count line first appears at six builders, alongside the first five names (adds to OL-2611-016, audit F2).

### Unchanged
- `tower.md` itself is not in this diff: C1 to C6 describe the overlay exactly as OL-2611-016 shipped it, and neither the roster thresholds nor the `show_hint` gate were changed to match anything.
