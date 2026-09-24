## Audit of OL-2609-011 - fix(bot): PHP distance pipe now honors the km input contract

**Audited:** 2026-09-24
**Commit:** 9d94a380718c2f09a1ef987bc9fba95a71098791
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Messages/PipeFormatter.php:142-158 @9d94a38` - non-numeric passthrough, empty args passthrough, `strtolower($args) === 'mi' ? $km / 1.609344 : $km`; same order as `resources/js/utils/formatters.ts:317-326 @9d94a38` (`isNaN` -> `!args` -> `unit === 'mi' ? km / 1.609344 : km`). @HEAD the method is unchanged at `PipeFormatter.php:247-263` and `formatDistance` unchanged at `formatters.ts:317-326` |
| C2 | CONFIRMED | `PipeFormatter.php:154-157 @9d94a38` - `new NumberFormatter($locale, NumberFormatter::DECIMAL)`, `MAX_FRACTION_DIGITS` 2; old `return (string) round($converted, 2)` at `PipeFormatter.php:145 @9d94a38^`. Both example outputs asserted in `tests/Unit/PipeFormatterDistanceTest.php:17-18,28 @9d94a38` and pass; `resources/help/pages/formatting.md:143 @9d94a38^` shows `11,61` for nl-NL. Unchanged @HEAD |
| C3 | CONFIRMED | `PipeFormatter.php:144-149 @9d94a38` - non-numeric and `$args === ''` both return `$value`. Removed: `strtolower($args === '' ? 'km' : $args)` at `:135 @9d94a38^`, `'m'`/`'ft'` arms at `:139,141 @9d94a38^`. `git grep -E "distance:(m\|ft)\b" 9d94a38^ -- resources docs app` returns nothing; `formatDistance` has no m/ft branch. Unchanged @HEAD |
| C4 | CONFIRMED | `tests/Unit/PipeFormatterDistanceTest.php @9d94a38` has six tests covering km passthrough, mi conversion, nl-NL locale, no args, unknown unit (`distance:m`), non-numeric. `php artisan test --filter=PipeFormatterDistanceTest`: 6 passed (7 assertions). File identical @HEAD |
| C5 | CONFIRMED | `PipeFormatter.php:138 @9d94a38^` - `'km' => $meters / 1000`; km-stored controls: `GpsServiceDriver.php:139,145 @9d94a38^` (labels "km"), `CheckinServiceDriver.php:134,180 @9d94a38^` (from `distance` km payload); callers `BotCommandResolver.php:124` and `AlertMessageRenderer.php:117 @9d94a38^`. Test re-pinned at `tests/Feature/BotCommandsApiTest.php:383-403 @9d94a38`: fixture `8.7`, expectations `8.7`/`5.41`, same as the old ones; `--filter='resolver applies the distance formatter'` 1 passed (2 assertions) |
| C6 | UNVERIFIABLE | tagged [unverified]; mistagged, see F1 |

### Surface
Complete.

### Findings
- **F1** mistagged, checkable as [code] - C6 is tagged [unverified], but both halves can be checked in the repo: `docs/changelog/changelog-2026-04.md:682` reads "New `|distance:km` and `|distance:mi` pipes. Input assumed km", and the first PHP implementation, `app/Services/Expressions/ExpressionFormatter.php @0df0dd92` (2026-05-10), already had `'km' => $meters / 1000`. A remedy claim should restate C6 as [code] citing those two places.
- **F2** contradiction with the record, uncited - this change fixes a divergence that earlier claims recorded, and cites neither of them inline as `claims-guide.md` "Reference other IDs inline" requires. `OL-2609-009/claim.md:25` C1 says "`|distance:km` / `|distance:mi` already existed in `formatters.ts` with input-assumed-km, mirrored by `PipeFormatter::distance()` in PHP", which the parent tree (`PipeFormatter.php:138 @9d94a38^`) shows was false. `OL-2609-010/claim.md:23` records "it assumes input in meters ... left for a separate decision", and this commit is that decision. A remedy claim should record "corrects OL-2609-009 C1" and "resolves the divergence recorded in OL-2609-010 Unchanged".

### Notes
- The Unchanged line "`PipeFormatter` still has no `speed` formatter" was true @9d94a38. `speed()` was added about 20 minutes later in OL-2609-012 (`b4be107`) and is at `PipeFormatter.php:265 @HEAD`.
- C1's "line for line" is about control flow. `is_numeric('')` is false but JS `Number('')` is 0, so an empty value with a unit returns `''` in PHP and `"0"` in JS. Going by CLAUDE.md, the overlay blanks `''` before any formatter runs, so this may never be visible. Not tested.
- The tests ran against HEAD. Neither test file changed between 9d94a38 and HEAD, and `distance()` did not change either.
