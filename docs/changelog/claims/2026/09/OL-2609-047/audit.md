## Audit of OL-2609-047 - feat(products): the Follower Bowling lane is redesigned to the hero's look

**Audited:** 2026-09-25
**Commit:** 9a0a868990d5e4a53126010677f49afd7e8cf9fb
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/follower_bowling/lane.md:538-562 @9a0a868` - controls table says "These 21", 20 rows `expression`, `c:gobowl` `boolean`; `:root` at `:88-106 @9a0a868` binds `--bt` to `--pin9` exactly as listed, and the diff's first `css` hunk starts after `--pin9`, so the binding lines were not touched. Same content @HEAD at `resources/recipes/follower-bowling/lane.md` (renamed by OL-2609-114) |
| C2 | CONFIRMED | `lane.md @9a0a868`: `[[[if:c:gobowl]]]` at :38 and `[[[endif]]]` at :80; `data-last-throw` on `.lane-widget` at :39; `foreach:c:list:lane` at :47; `channel_followers` foreach with `loop.index <= 9` at :63; ten `pin-filler` blocks N=0..9 at :64-73; `[[[c:list:lane:last_removed]]]` at :75; STRIKE!/GUTTER/PINS conditional at :76. Unchanged @HEAD |
| C3 | CONFIRMED | `lane.md @9a0a868`: `.lane-widget` left 40, bottom 60, 1840x440 at :146-149 (html/body 1920x1080 at :131-132); columns `340px 1460px`, gap 40 at :151-152; `.pins` left 1072 top 66 at :410-411; ball top 178, `left: calc(224px + var(--bx) * 850px)`, width 84 at :366-368; head pin `border-color: var(--pink-deep)` at :452-453, other pins `rgb(167 139 250 / 0.9)` (= `--violet`) at :435. Unchanged @HEAD |
| C4 | CONFIRMED | `lane.md @9a0a868`: `counter-reset: place` :199, `counter-increment: place` :206, `content: counter(place)` :210; `.waiting-index` span is empty in markup at :47; `loop.index <= 2` at :47. Zero-based: `resources/js/composables/useConditionalTemplates.ts:293-305 @9a0a868` sets `first: index === 0` and `scoped['loop.index'] = index` |
| C5 | CONFIRMED | `lane.md:77 @9a0a868` - `<span class="chip chip-mods">mods</span>` then `!fbfirst &middot; !fbdraw`; `.chip-mods` color `var(--pink)` at :275-281; `!list lane` does not occur in the `html` block (:38-80). Unchanged @HEAD |
| C6 | CONTRADICTED (compound) | First half CONFIRMED: the diff adds no `<script>`, `<link>` or `@import` (`head` block :25-31 not in any hunk); fonts are `"Albert Sans", system-ui, sans-serif` at :139 and `--mono: "JetBrains Mono", ui-monospace, ...` at :120 @9a0a868. Second half CONTRADICTED: "the same names the previous lane used" is false - `lane.md @9a0a868^` contains no `JetBrains Mono` anywhere; its only font is `"Albert Sans"` at :119 |
| C7 | CONFIRMED | `tests/Feature/ProductBowlingTest.php @9a0a868` has 10 `it()` tests, including the 21-control install (:70-79) and the stand-in test (:159-170, 10 `pin pin-filler`). `php artisan test --filter=ProductBowlingTest` @HEAD: 10 passed (98 assertions). @HEAD the lane's `html`/`css` are byte-identical to @9a0a868 and the test differs only in slug/path renames (OL-2609-114) |
| C8 | UNVERIFIABLE | tagged [unverified]; a browser render on `overlabels.test`, not checkable in-repo |

### Surface
Complete.

### Findings
- **F1** compound claim, half false - C6 says the fonts are "the same names the previous lane used", but `resources/recipes/follower_bowling/lane.md @9a0a868^` uses only `"Albert Sans"` (:119) and `JetBrains Mono` is new in this commit (`lane.md:120 @9a0a868`); the reader should record in a new claim that `JetBrains Mono` was introduced here, and that no font import loads it (the `head` block imports Albert Sans only).

### Notes
- Running the test at the shipped tree (a `git archive` of 9a0a868 using the current vendor) did not boot: first a stale provider in `bootstrap/cache`, then Pest refused the scratch path as a namespace. So C7 was run @HEAD only, where the lane `html`/`css` and the test assertions are the same as shipped.
- The post-ship changes to the lane are disclosed by later claims: the file was renamed to `follower-bowling/` (OL-2609-114), and its `head` moved from Google Fonts to Bunny (OL-2609-119). Neither change touches `html` or `css`.
- C3's "the design's offsets" / "the design's" points at a design that is not in the repo. Only the numbers the claim states were checked.
- `resources/js/utils/tagCompletions.ts:112 @9a0a868` and @HEAD describe `loop.index` as "starting at 1", but the engine (`useConditionalTemplates.ts:293-305`) and `renderTemplate.test.ts:206-213` make it zero-based. This commit did not add that line; it came before.
- The lane description (`lane.md:9 @9a0a868`) still tells mods to run `!list lane pop first` / `!list lane draw`. C5 covers only the markup, so this is not a finding.
