## OL-2609-012 - fix(bot): full PHP/JS pipe formatter parity - speed added, five formatters re-aligned

**Shipped:** 2026-09-02
**Commit:** `git log --grep=OL-2609-012`

### Surface
- `app/Services/Messages/PipeFormatter.php` - `speed()` added; `round()`, `number()`, `currency()`, `date()`, `duration()` re-aligned to formatters.ts; `LOCALE_CURRENCY_MAP` ported; class docblock states the contract
- `tests/Unit/PipeFormatterParityTest.php` - new file (18 tests)

### Claims
- **C1** [code] `speed()` exists: input m/s, `kmh` multiplies by 3.6, `mph` converts on top, unknown units fall back to kmh, no args passes through, locale-formatted with max one fraction digit - previously the name was unknown and `|speed:` passed raw m/s into bot replies and alert TTS.
- **C2** [code] `round()` uses toFixed semantics: padded to the precision (`5|round:2` is `5.00`) and junk or negative precision returns the value unchanged, replacing the bare `round()` cast that neither padded nor rejected.
- **C3** [code] `number()` with no (or unparseable) precision uses ICU's natural formatting - the same engine and defaults as Intl - replacing the forced zero-decimal rounding that turned `1234.5` into `1,235`.
- **C4** [code] `currency()` mirrors the JS quirk: no-args resolves through the ported `LOCALE_CURRENCY_MAP`, whose values are symbols Intl rejects, so mapped locales fall back to a plain two-decimal number exactly like the JS catch; unmapped locales fall back to USD; malformed codes take the same plain fallback.
- **C5** [code] `date()` gains the locale parameter: the no-args default and the four presets (`short`/`long`/`date`/`time`) format via `IntlDatePatternGenerator` skeletons - locale-correct like Intl - where presets previously fell through to `Carbon::format()` reading the preset letters as format characters; the custom-token map drops the `yy` token JS never had.
- **C6** [code] `duration()` keeps a negative sign and implements the no-args auto format (`1:30`, `2:15:07`, `1d 1h 1m`) instead of clamping to zero and forcing `hh:mm:ss`; the pattern path is unchanged.
- **C7** [test] `PipeFormatterParityTest` pins all of C1-C6 across en-US and nl-NL, with structural date assertions where ICU versions disagree on cosmetic whitespace.
- **C8** [code] `formatters.ts` is not modified anywhere in this change: JS is the contract, PHP moved.

### Unchanged
- The distance pipe (OL-2609-011) and `login`/`mention`/`uppercase`/`lowercase` already mirrored and are untouched by the re-alignment; distance keeps its own test file.
- The 125 tests across the formatter's consumers (`BotCommandsApiTest`, `AlertBotMessageTest`, `AlertTtsMessageTest`, `BotListTagsTest`, `BotRandCounterTagsTest`) pass without modification - no pinned expectation relied on the divergent behaviors.
- The date TIMEZONE divergence remains and is documented in the class docblock: the browser formats in the streamer's machine-local time, the server in the app timezone, because the server cannot know the viewer's clock.

### Risk
Server-side output changes for anyone who piped through the divergent paths: `|number` on
decimals no longer rounds to integers, `|round:N` pads, `|duration` without a pattern
auto-formats, `|date` presets produce real dates instead of garbage. All movements are toward
what the same template already showed in the overlay. The no-args `|currency` quirk (plain
number for mapped locales) is now faithfully mirrored rather than accidentally correct on one
side; fixing the quirk itself means fixing the JS symbol map first, in lockstep - recorded,
not done here.
