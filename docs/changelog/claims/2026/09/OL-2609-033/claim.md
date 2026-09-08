## OL-2609-033 - docs(help): the math guide documents clamp() with the value first, matching the engine

**Shipped:** 2026-09-08
**Commit:** `git log --grep=OL-2609-033`

### Surface
- `resources/help/pages/math.md` - the function table row and four examples reordered to `clamp(x, lo, hi)`

### Claims
- **C1** [code] `resources/js/lib/expression-engine/engine.mjs` defines `clamp` as `Math.min(Math.max(args[0], args[1]), args[2])`: the value is the first argument, the lower bound the second, the upper bound the third.
- **C2** [code] `math.md` now lists the signature as `clamp(x, lo, hi)`, the same order `expressions.md` has documented since it was written, and every `clamp(` call in its examples passes the expression first.
- **C3** [unverified] Every one of the four rewritten examples used `0` as its lower bound. Because `Math.max` is commutative, `clamp(0, e, hi)` and `clamp(e, 0, hi)` evaluate identically, so the old examples produced correct values while teaching the wrong signature. No shipped overlay changes behaviour.

### Unchanged
- The engine, `expressions.md` and `resources/dsl/dsl.json` are not in the diff.
