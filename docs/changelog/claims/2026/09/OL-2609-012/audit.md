## Audit of OL-2609-012 - fix(bot): full PHP/JS pipe formatter parity - speed added, five formatters re-aligned

**Audited:** 2026-09-24
**Commit:** b4be107cae18bfa8b90445bd474ca0c64e72e77e
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Messages/PipeFormatter.php:271-287 @b4be107` - `speed()`: `* 3.6`, `/ 1.609344` only when unit is `mph`, `$args === ''` returns `$value`, `NumberFormatter::DECIMAL` with `MAX_FRACTION_DIGITS` 1. Parent `@b4be107^` `apply()` match has no `speed` arm, so it hit `default => $value`. No later commit touches the file (`git diff b4be107 HEAD` empty); same @HEAD |
| C2 | CONFIRMED | `PipeFormatter.php:78-96 @b4be107` - junk (`/^[+-]?\d/` fails) or negative precision returns `$value`; result is `number_format(..., $precision, '.', '')`, which pads. Replaces `(string) round(...)` @b4be107^. Same @HEAD |
| C3 | CONFIRMED | `PipeFormatter.php:104-117 @b4be107` - `FRACTION_DIGITS` set only for a valid non-negative precision; otherwise ICU default. Probe: `1234.5678\|number` en-US gives `1,234.568` in PHP and in Node `Intl.NumberFormat` |
| C4 | CONFIRMED | `PipeFormatter.php:35-47, 128-145 @b4be107` - map ported verbatim from `resources/js/utils/formatters.ts:37-48 @b4be107`; non-`[A-Za-z]{3}` code returns `number_format(..., 2)`; unmapped falls to `'USD'`. Probe: `12.34\|currency` de-DE -> `12.34`, `12.34\|currency:EURO` -> `12.34` |
| C5 | CONFIRMED | `PipeFormatter.php:155-193 @b4be107` - `date()` takes `$locale`; `''`/`short`/`long`/`date`/`time` go through `IntlDatePatternGenerator::getBestPattern()`; parent sent presets to `translateDateFormat()` + `Carbon::format()`. `translateDateFormat()` map at `:199-206` no longer has `'yy'`. The consequence of dropping `yy` is wrong, see F1 |
| C6 | CONFIRMED | `PipeFormatter.php:298-341 @b4be107` - `floor`, sign kept, `durationAuto()` gives `1:30` / `2:15:07` / `1d 1h 1m`; `durationPattern()` body is the parent's decomposition moved over unchanged |
| C7 | CONTRADICTED | `tests/Unit/PipeFormatterParityTest.php @b4be107` exists and all 21 tests pass (`php artisan test --filter=PipeFormatterParityTest`, 21 passed, 34 assertions). It does NOT pin: `date:short` or `date:long` (neither preset appears), the `yy` removal (no `yy` pattern), the malformed-code currency fallback (no invalid code), or the pattern path with a negative value. nl-NL is only asserted for `number` (`:70`), no-code `currency` (`:85`) and `date:date` (`:126`); speed, explicit-code currency, the date default and `date:time` are en-US only |
| C8 | CONFIRMED | `git show --stat b4be107` lists three paths and `resources/js/utils/formatters.ts` is not one of them |

### Surface
Complete. The Surface line for `tests/Unit/PipeFormatterParityTest.php` says "18 tests" but the file has 21 (see F2).

### Findings
- **F1** Scope (false code comment, behaviour regression) - `app/Services/Messages/PipeFormatter.php:197-198 @b4be107` says a bare `yy` "stays literal here" as it does in JS, but the translated pattern is passed to `Carbon::format()`, where `y` is a format character: `1788261900\|date:dd-MM-yy` returns `01-09-2626` in PHP (probe run at this commit, which matches HEAD), while `formatters.ts:284-290` leaves `yy` literally (`01-09-yy`), and the parent revision gave `01-09-26`. So this change moved `yy` further from the JS contract. The same applies to any letter outside the six tokens. Either escape the non-token characters before `format()` or correct the comment, and add a test.
- **F2** Surface inaccuracy - the Surface line gives `PipeFormatterParityTest.php` as "(18 tests)", but the file has 21 `test()` calls @b4be107 and the run reports 21 passed. The commit message says 18 as well. Correct the count in a new claim.
- **F3** Test narrower than claim - C7 says the test pins all of C1-C6 across en-US and nl-NL, but it has no case for `date:short`, `date:long`, the `yy` token, a malformed currency code, or a negative duration with a pattern. Speed, explicit-code currency, the date default and `date:time` are asserted in en-US only. Add those cases or narrow the claim.

### Notes
- Ran the five consumer suites named under Unchanged (`BotCommandsApiTest|AlertBotMessageTest|AlertTtsMessageTest|BotListTagsTest|BotRandCounterTagsTest`) @HEAD: 125 passed, 477 assertions. None of those files is in the diff.
- `round()` uses `number_format`, which rounds differently from `toFixed` at binary edges: `1.005\|round:2` is `1.01` in PHP and `(1.005).toFixed(2)` is `1.00` in Node. C2 defines "toFixed semantics" as padding and the junk/negative rule, and both of those hold.
- OL-2609-011 Unchanged recorded "`PipeFormatter` still has no `speed` formatter". This change adds it. This is the deferred work being done, not a rule being reversed.
- No commit after b4be107 touches `PipeFormatter.php` or the test file, so there is no HEAD drift.
