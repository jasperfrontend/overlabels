## OL-2609-047 - feat(products): the Follower Bowling lane is redesigned to the hero's look

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-047`

### Surface
- `resources/recipes/follower_bowling/lane.md` - the `html` and `css` fenced blocks replaced; front matter, description and the 21-control table untouched

### Claims
- **C1** [code] The document still parses to 21 controls, 20 of type `expression` and one boolean `gobowl`, and the `:root` block still binds every one of `bowl_t`, `bowl_age`, `ball_x`, `ball_y`, `ball_pulse`, `ball_on`, `bowl_show`, `bowler_show` and `pin_0` to `pin_9` to the same custom property names as before (`--bt`, `--age`, `--bx`, `--by`, `--pulse`, `--on`, `--show`, `--bowler`, `--pin0` to `--pin9`).
- **C2** [code] The markup keeps `[[[if:c:gobowl]]]` around the whole widget, `data-last-throw="[[[c:list:lane:last_removed_at]]]"` on `.lane-widget`, the `c:list:lane` foreach for the queue, the `channel_followers` foreach with `[[[if:loop.index <= 9]]]` for the pins, the ten stand-in blocks from OL-2609-046, `[[[c:list:lane:last_removed]]]` for the bowler, and the STRIKE / GUTTER / N PINS conditional for the score.
- **C3** [code] The layout is the design's: a 1840 by 440 strip at the bottom of the 1920 by 1080 canvas, a 340 px queue panel and a 1460 px lane 40 px apart, the ten pin slots at the design's offsets inside a rack at left 1072 top 66, the ball 84 px at top 178 travelling from x 224 by `--bx` times 850 px, the head pin with a pink ring and the rest violet.
- **C4** [code] The queue numbers its rows with a CSS counter, not with `[[[loop.index]]]`, which is zero-based; it shows the first three in line (`loop.index <= 2`).
- **C5** [code] The mods line reads `!fbfirst` and `!fbdraw` inside a pink `mods` chip; no `!list lane` text remains in the markup.
- **C6** [code] No JavaScript, no external stylesheet and no font import is added; the fonts are `Albert Sans` and `JetBrains Mono` with system fallbacks, the same names the previous lane used.
- **C7** [test] `ProductBowlingTest` (10 tests) passes against the new document, including the stand-in assertions and the 21-control install.
- **C8** [unverified] On `overlabels.test` on 2026-09-09 the new lane, created as a temporary overlay on the author's account with `gobowl` on and rendered through the overlay route with a real token, showed the queue panel, the lane with gutters, foul line and arrows, ten real follower pins in the design's rack with a pink head pin, and the mods chip; the temporary overlay and token were deleted afterwards.

### Unchanged
- The 20 expression controls and their formulas are not in the diff; the redesign changes what the numbers paint, not the numbers.
- The manifest, the installer and every product page are not in the diff.

### Risk
Existing Follower Bowling installs keep the lane they were installed with; the new look reaches them through uninstall and install. The lane is 440 px tall against the old 260 px and sits 60 px from the bottom, so a stream that had something in that band will see it covered.
