## OL-2609-011 - fix(bot): PHP distance pipe now honors the km input contract

**Shipped:** 2026-09-02
**Commit:** `git log --grep=OL-2609-011`

### Surface
- `app/Services/Messages/PipeFormatter.php` - `distance()` rewritten to the km contract, locale-aware
- `tests/Feature/BotCommandsApiTest.php` - the resolver distance test re-pinned to km input
- `tests/Unit/PipeFormatterDistanceTest.php` - new file (6 tests)

### Claims
- **C1** [code] `PipeFormatter::distance()` treats input as kilometers: km (and any unknown unit) is a passthrough, `mi` divides by 1.609344, matching `formatters.ts` `formatDistance()` line for line.
- **C2** [code] Output is locale-formatted via `NumberFormatter::DECIMAL` with `MAX_FRACTION_DIGITS` 2, replacing the bare `round(, 2)` cast - so `12345.678|distance:km` renders `12,345.68` in en-US and `11.61` renders `11,61` in nl-NL, as the /help/formatting table has always shown.
- **C3** [code] No unit argument returns the raw value unchanged and non-numeric input passes through, both mirroring the JS side; the previous default-to-km-on-empty and the `m`/`ft` units (documented nowhere, absent from JS) are removed.
- **C4** [test] `PipeFormatterDistanceTest` pins passthrough, mi conversion, locale formatting, the no-args and unknown-unit behaviors, and non-numeric passthrough.
- **C5** [code] The prior behavior divided by 1000 (input assumed meters), so every server-side `|distance:` applied to a km-stored control (GPS `distance`/`session_distance`, checkin `latest_checkin_distance`/`farthest_checkin_this_stream`) produced a value 1000x too small in bot replies and alert TTS/chat; the resolver feature test pinned that divergence with an "8704 meters" fixture no real control stores, and is re-pinned on `8.7` km with the same expected outputs.
- **C6** [unverified] The April 2026 changelog entry for the distance pipes announced "Input assumed km" at ship time; the PHP side violated that published contract from its first commit.

### Unchanged
- `formatters.ts` is not in the diff - it was the contract, not the bug.
- `PipeFormatter` still has no `speed` formatter (the JS side does); known, recorded, and not
  smuggled into this fix.
- `resources/help/reference/Bot-Expressions.md` (a dated, unserved design spec) keeps its old
  examples, per the standing rule that historical documents record what was true when written.

### Risk
Any bot command or alert message whose author had compensated for the bug (piping a value
pre-multiplied by 1000) now speaks a value 1000x larger. No such usage is known; the known
usages were silently wrong and are now right.
