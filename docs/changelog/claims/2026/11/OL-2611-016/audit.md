## Audit of OL-2611-016 - feat(tower): full-screen redesign and Coyote Time for whoever topples it

**Audited:** 2026-11-15
**Commit:** 04f5e79cf09251aed15ece246aa85c5e8264de58
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/chat-tower/tower.md:139 @04f5e79` - `html, body { width: 1920px; height: 1080px; ... }`; `:141` `body { background: var(--field-deep); }` (`.stage` also opaque at `:147`); the old `background: transparent` on `html, body` is gone. HEAD = 04f5e79 |
| C2 | CONFIRMED | `tower.md @04f5e79` - `grep -nE 'class="[^"]*\b(chip\|legend\|hint)\b'` and `grep -nE '\.(chip\|legend\|hint)\b'` return nothing; `:41-42` render `!stack`, `!stack left`, `!stack right` as `<span class="cmd">` |
| C3 | CONFIRMED | `tower.md:505-518 @04f5e79` - "These 10 are defined", ten rows, no `c:cam`; `grep -n 'c:cam\|--cam\|c\.cam'` returns nothing |
| C4 | CONFIRMED | `tower.md:312 @04f5e79` - `transform: translateY(calc(max(0, var(--count) - var(--rows)) * var(--unit)))`; `--count` set from `[[[tower.count]]]` on `.stage` at `:37`, `--rows: 13` at `:135` |
| C5 | CONFIRMED | `tower.md:525 @04f5e79` - `sin(2 * PI * now_ms() / 2600)`; `:329` `transform: translateX(calc(0.055 * var(--p) * var(--wave) * var(--unit)))`; neither references `c.tower.tower_height` (`c:amp` at `:523` still does, unclaimed and unaffected) |
| C6 | CONFIRMED | `tower.md:373-386 @04f5e79` - `.stage[data-falling="1"] .block` runs `fall` and `fade` keyframes; no `transition` in that rule or in `.block` (`:319-349`); the three `transition`s in the file are on `.fallline`, `.gauge-pin`, `.cam` (`:225`, `:296`, `:313`) |
| C7 | CONFIRMED | `tower.md:389-394 @04f5e79` - `coyote-flash 0.7s linear 0.9s forwards`, `coyote-drop 0.55s cubic-bezier(...) 1.9s both` |
| C8 | CONFIRMED | `tower.md:454 @04f5e79` - `animation: banner-in 0.45s cubic-bezier(0.2, 0.9, 0.3, 1.4) 2.4s both` |
| C9 | CONFIRMED | `tower.md:84-85 @04f5e79` - `.banner-name` holds `[[[c:tower:last_topple_by]]]`, next `.banner-mid` "toppled the tower at <b>[[[c:tower:last_topple_height]]]</b>" |
| C10 | CONFIRMED | `tower.md:66-89 @04f5e79` - `#slot-empty`, `#slot-record`, `#slot-banner` are unconditional `div.slot`s inside `.field`, each with its `[[[if]]]` inside |
| C11 | CONFIRMED | `tower.md:37 @04f5e79` - `data-banner="[[[c:banner]]]"` on `.stage`; `:423` `.stage[data-banner="1"] .empty { display: none; }` |
| C12 | CONFIRMED | `tower.md:96 @04f5e79` - first branch is `[[[if:c:banner]]]tower collapsed!`, before `tower.count = 0` and both `c:danger` branches |
| C13 | CONFIRMED | `tower.md:183 @04f5e79` - `.score { ... text-align: center; }` |
| C14 | CONFIRMED | `app/Http/Controllers/Api/Internal/BotTowerController.php:38 @04f5e79` - `public const int SETTLE_SECONDS = 9;` |
| C15 | CONFIRMED | `BotTowerController.php:164-166 @04f5e79` - `Cache::put("tower:settling:{$user->id}", 1, self::SETTLE_SECONDS)` when `$facts['type'] === 'topple'`, before the `if ($testing)` branch at `:168`; `app/Services/Tower/TowerService.php:94-117 @04f5e79` returns `type` `topple` regardless of `$testing` |
| C16 | CONFIRMED | `BotTowerController.php:122-124 @04f5e79` - `Cache::has` returns `['reply' => null]`; the cooldown `Cache::add` is at `:143` |
| C17 | CONFIRMED | `BotTowerController.php:105-111 @04f5e79` - the `status` branch returns before the settle check at `:122` |
| C18 | CONFIRMED | `tests/Feature/BotTowerTest.php:390-408 @04f5e79` - `cooldown_seconds` 30, topple, `travel(5)`, viewer `222` gets `reply` null and count 0, `travel(5)`, same viewer lands count 1. `php artisan test --filter="stacks are ignored while a topple plays out"`: 1 passed (7 assertions) |
| C19 | UNVERIFIABLE | tagged [unverified] |
| C20 | CONFIRMED | `tests/Feature/ProductTowerTest.php:80-81 @04f5e79` - `toBe(10)` controls, `toBe(9)` of type `expression`. `php artisan test --filter="installs the tower overlay, connects the integration"`: 1 passed (14 assertions) |
| C21 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** Scope - `tower.md:518 @04f5e79` relabels the `show_hint` control from "Show the command hints" to "Show how to play" and `:38-44` repurposes it to gate the left-hand `.intro` panel instead of the `!stack` chips; no claim or Surface line records the change to an installed control, so add a claim for it.
- **F2** Scope - `tower.md:101-102 @04f5e79` changes the record roster from up to 3 names with "builders" past 4 (`loop.index <= 3`, `count > 4`) to up to 4 names with "builders in all" past 5 (`<= 4`, `> 5`), so a five-builder record shows four names and no count line; no claim records it, so add a claim for it.

### Notes
- HEAD is 04f5e79, so every row above holds at HEAD too. `php artisan test tests/Feature/BotTowerTest.php tests/Feature/ProductTowerTest.php`: 30 passed.
- OL-2609-055 C2 ("ten expression controls plus one boolean `show_hint`") is superseded by this change (nine plus one), which does not cite it.
- CLAUDE.md Chat Tower bullet "so a CSS transition can tumble the blocks" no longer matches the overlay (keyframes, C6), and its "Two cooldowns pace `!stack`" bullet does not mention the new settle window. Neither is a prohibition, so neither is a finding.
- Unchanged line 1 says the fall lines are "4 block widths" from the base; `TowerPhysics.php:27,30` has `BLOCK_WIDTH` and `FALL_LINE` both 4.0 units, so 4 units is one block width. `tower.md:325` and the `c:sway` description at `:524` say "0.055 block widths" for what the CSS moves as 0.055 units.
- The settle window drops `!stack` silently, which OL-2609-010 C2 gave as the reason the checkin offline gate replies instead. The claim's Risk section says this openly.
