## Audit of OL-2609-033 - docs(help): the math guide documents clamp() with the value first, matching the engine

**Audited:** 2026-09-25
**Commit:** 40229cfac45f63dea281e642cf8445ec869f5010
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/lib/expression-engine/engine.mjs:226 @40229cf` - `clamp: (args) => Math.min(Math.max(toNum(args[0]), toNum(args[1])), toNum(args[2]))`: value first, lower bound second, upper bound third. Identical at `:226 @HEAD`. The claim's quote leaves out the `toNum()` wrappers (see Notes) |
| C2 | CONFIRMED | `resources/help/pages/math.md:45 @40229cf` - `clamp(x, lo, hi)`; the only `clamp(` calls in the file, `:249`, `:336`, `:349`, `:372 @40229cf`, all pass the expression first with `0` as the second argument. `resources/help/pages/expressions.md:79,231 @65d56950` (the commit that added the file) reads `clamp(x, min, max)`, and it is the same in all four commits that touch the file (65d56950, 3c3250b5, b587784b, 2ee9d622). No commit after 40229cf touches `math.md`, `expressions.md` or `engine.mjs` |
| C3 | UNVERIFIABLE | tagged [unverified]. Its first two sentences can be checked in-repo, see F1 |

### Surface
Complete.

### Findings
- **F1** mistagged, checkable as [code] - C3 is tagged [unverified], but its first sentence (all four rewritten examples used `0` as the lower bound) can be read off the diff of 40229cf to `resources/help/pages/math.md` (the removed lines at old `:249`, `:336`, `:349`, `:372` each begin `clamp(0, ...`). Its second sentence (`clamp(0, e, hi)` equals `clamp(e, 0, hi)`) follows from `engine.mjs:226 @40229cf`, because the first two arguments only ever feed a two-argument `Math.max`. Only the "no shipped overlay changes behaviour" half needs anything from outside the repo. It is also a compound claim. A follow-up claim should split it into the [code] halves and the [unverified] half.

### Notes
- C1 quotes the engine as `Math.min(Math.max(args[0], args[1]), args[2])`. The source at `engine.mjs:226 @40229cf` wraps each argument in `toNum()`. The argument order the claim is about is correct.
- No tests were run. The claim makes no [test] claims.
- Unchanged is correct: `engine.mjs`, `expressions.md` and `resources/dsl/dsl.json` are not in `git show --stat 40229cf`, and `dsl.json @40229cf` never mentions `clamp`.
- This change touches only `resources/help/` and needed no claim under the path rule. It has one anyway, which the guide allows.
