## OL-2611-016 - feat(tower): full-screen redesign and Coyote Time for whoever topples it

**Shipped:** 2026-11-15
**Commit:** `git log --grep=OL-2611-016`

### Surface
- `resources/recipes/chat-tower/tower.md` - the product overlay replaced: full-screen layout, new topple sequence, `cam` control removed, `sway` control redefined
- `app/Http/Controllers/Api/Internal/BotTowerController.php` - `SETTLE_SECONDS` constant, a settle-window check in `store()` and the cache write that opens it
- `tests/Feature/BotTowerTest.php` - one new test for the settle window
- `tests/Feature/ProductTowerTest.php` - installed overlay control counts lowered from 11/10 to 10/9

### Claims
- **C1** [code] The `tower.md` CSS gives `html, body` a fixed 1920x1080 size and `body` the opaque background `var(--field-deep)`; the overlay no longer renders on a transparent canvas.
- **C2** [code] `tower.md` contains no element with class `chip`, `legend` or `hint`; the `!stack` commands render as `<span class="cmd">` text.
- **C3** [code] `tower.md` declares 10 controls; `cam` is not among them and no `[[[c:cam]]]` tag appears in its source.
- **C4** [code] The `.cam` transform in `tower.md` is computed from `var(--count)` and `var(--rows)` (the `tower.count` data tag on `.stage`), not from a control.
- **C5** [code] The `sway` control's expression in `tower.md` is `sin(2 * PI * now_ms() / 2600)`, and `.block`'s transform is `translateX(calc(0.055 * var(--p) * var(--wave) * var(--unit)))`, with no reference to `c.tower.tower_height`.
- **C6** [code] The falling blocks animate with `@keyframes fall` / `fade` under `.stage[data-falling="1"] .block`, not with a CSS `transition`.
- **C7** [code] `.stage[data-falling="1"] .block:last-child` runs `coyote-flash` at 0.9s with fill mode `forwards` and `coyote-drop` at 1.9s.
- **C8** [code] The `.banner` animation in `tower.md` has a 2.4s delay.
- **C9** [code] The banner's first line is `[[[c:tower:last_topple_by]]]` in `.banner-name`, followed by "toppled the tower at" and `[[[c:tower:last_topple_height]]]`.
- **C10** [code] `.empty`, `.record-flash` and `.banner` each sit inside their own always-rendered wrapper (`#slot-empty`, `#slot-record`, `#slot-banner`), with the `[[[if]]]` inside the wrapper.
- **C11** [code] `.stage` carries `data-banner="[[[c:banner]]]"`, and `.stage[data-banner="1"] .empty` is `display: none`.
- **C12** [code] The Height status reads "tower collapsed!" when `c:banner` is truthy, ahead of every other branch.
- **C13** [code] `.score` has `text-align: center`.
- **C14** [code] `BotTowerController::SETTLE_SECONDS` is 9.
- **C15** [code] `BotTowerController::store()` puts `tower:settling:{user_id}` in the cache for `SETTLE_SECONDS` when the stack's facts have `type` `topple`, in live and test mode alike.
- **C16** [code] `store()` returns `{"reply": null}` while `tower:settling:{user_id}` is present, and that check runs before the per-viewer cooldown `Cache::add`.
- **C17** [code] The `status` action (`!tower`) returns before the settle-window check and is not affected by it.
- **C18** [test] `BotTowerTest` "stacks are ignored while a topple plays out, then land again" asserts a stack 5 seconds after a topple lands no block, and the same viewer's stack 10 seconds after the topple lands one with a 30 second cooldown configured.
- **C19** [unverified] C18's test failed against the tree before the controller change ("Failed asserting that 1 is identical to 0").
- **C20** [test] `ProductTowerTest` asserts an installed tower overlay has 10 controls, 9 of them expressions.
- **C21** [unverified] On a local copy in Firefox, a 25-block topple held 60 fps (worst frame 17 ms), the culprit flashed red at 0.97-1.12s and 1.37-1.52s, and the banner stayed at full opacity from 2.5s until it left at 8.7s.

### Unchanged
- `TowerPhysics` decides when a tower falls and is not in the diff: the fall lines are still 4 block widths either side of the base, and the visual sway amplitude still equals its `0.055 * height`.
- The overlay's `banner` control expression (`topple_age < 9`), the value `SETTLE_SECONDS` is set to match, is unchanged.
- `TOPPLE_HOLD_MS` in the renderer, the three seconds every timing in the new sequence fits inside, is unchanged; no renderer or `towerSlots.ts` file is in the diff.
- Overlays already installed from the recipe are not migrated; `tower.md` is read at install time only.

### Risk
Existing installs keep the old overlay until reinstalled or re-imported. During the nine seconds after
a topple a viewer's `!stack` is dropped with no reply, so it has to be typed again.
