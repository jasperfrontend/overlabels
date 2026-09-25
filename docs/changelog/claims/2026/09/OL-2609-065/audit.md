## Audit of OL-2609-065 - fix(tts): rewrite currency amounts into words before ElevenLabs sees them

**Audited:** 2026-09-25
**Commit:** dd336ca5c0ceb14a5468afbbce4c2d2430e13007
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Tts/SpeakableText.php:69 @dd336ca` - `public static function prepare(string $text): string`; no `config()`, `DB`, or request access anywhere in the file @dd336ca. @HEAD still pure; marker list now sorted with `usort` (OL-2609-081) |
| C2 | CONFIRMED | `app/Services/Tts/TtsService.php:37-40 @dd336ca` trim/empty guard, `:42` `SpeakableText::prepare($text)`, `:55` `cacheKey($text, ...)`. @HEAD `cacheKey()` is an HMAC with a `$scope` argument (OL-2609-097 C26), still taken after `prepare()` at `:43` |
| C3 | CONFIRMED | `git grep SpeakableText dd336ca -- app/` - the only call is `TtsService.php:42`; same @HEAD (`TtsService.php:43`) |
| C4 | CONFIRMED | `SpeakableText.php:44-51 @dd336ca` keys `USD, EUR, GBP, JPY, CAD, AUD`; `:54-59` maps `€`→EUR, `$`→USD, `£`→GBP, `¥`→JPY. @HEAD `SYMBOLS` has 15 entries (OL-2609-081 C1); `CURRENCIES` unchanged |
| C5 | CONTRADICTED (causal half) | Effect half CONFIRMED: an unlisted code is not rewritten (`SEK 84.50` → `SEK 84.50`, run against the file @dd336ca). The "because" half is false: `$money` at `SpeakableText.php:75-78 @dd336ca` is built only from `SYMBOLS` and `CURRENCIES` keys, so an unlisted code never matches the patterns at `:81`/`:88` and never reaches `rewrite()`; the `isset(self::CURRENCIES[$code])` null return at `:107` is unreachable, since every `SYMBOLS` value is a `CURRENCIES` key. Same @HEAD |
| C6 | CONTRADICTED (first half) | `splitAmount()` returns null only in the single-separator-kind branch (`SpeakableText.php:169-172 @dd336ca`); with both `.` and `,` present, `:164` takes the last separator as the decimal point and never returns null. `prepare('€1.2345,67')` returns `12345 euros and 67 cents` @dd336ca, where `.` is followed by four digits. Second half CONFIRMED: `rewrite()` returns null at `:113` and the callbacks at `:83`/`:90` fall back to `$m[0]` (`€1,2345` stays `€1,2345`) |
| C7 | CONFIRMED | `SpeakableText.php:97 @dd336ca` `preg_replace('/(?<![\p{L}\p{N}])([A-Z]{3})(?=\d)/u', '$1 ', ...)`, with no reference to `CURRENCIES`; `CHF84` → `CHF 84`. Same @HEAD |
| C8 | CONFIRMED | `SpeakableText.php:67 @dd336ca` `GAP = '[\s\x{00A0}\x{202F}]*'`; same @HEAD |
| C9 | CONFIRMED | `tests/Unit/SpeakableTextTest.php @dd336ca` has 19 `test()` calls covering every listed case (lines 9-113). The shipped test file, run against the shipped class in a scratch harness: 19 passed, 0 failed. `php artisan test --filter=SpeakableTextTest` @HEAD: 23 passed (97 assertions); 4 tests added by OL-2609-081 |
| C10 | UNVERIFIABLE | tagged [unverified]; its second half names two in-repo fixture files (see F1) |
| C11 | UNVERIFIABLE | tagged [unverified] |
| C12 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** mistagged, checkable as [code] - C10's second half ("the repo's own fixtures at `tests/Unit/StreamLabsServiceDriverTest.php` and `tests/Feature/StreamLabsWebhookTest.php` use `$13.37` and `$5.00`") names two files in the repo but is tagged [unverified]. A follow-up claim should split it off and tag it [code].
- **F2** compound claim, halves differ - C5 gives the wrong mechanism. `rewrite()`'s unknown-code null at `SpeakableText.php:107 @dd336ca` is unreachable, and the regex alternation built at `:75-78` is what actually leaves unlisted codes alone. A follow-up claim should restate C5 with the alternation as the cause.
- **F3** claim overstates behaviour - C6 says `splitAmount()` returns null whenever a separator is followed by neither one, two nor three digits, but with both separator kinds present it never returns null (`SpeakableText.php:163-164 @dd336ca`; `€1.2345,67` → `12345 euros and 67 cents`). A follow-up claim should limit C6 to the single-separator case, or the code should be changed to refuse that shape if refusal was intended.

### Notes
- Every Unchanged symbol (`AlertMessageRenderer::render/renderAlert/renderMessage`, `PipeFormatter::currency()`, `StreamLabsServiceDriver::normalizeEvent()`, `config/services.php`) is absent from the diff. `config/services.php:133 @dd336ca` defaults `model_id` to `eleven_multilingual_v2`.
- The Unchanged line about `formatted_amount` from the StreamLabs driver describes the tree @dd336ca only; OL-2609-073 later made `formatted_amount` a `NumberFormatter::CURRENCY` string for every service (cited in OL-2609-081).
- The Risk statement matches `routes/console.php:272-281 @dd336ca` (weekly `tts:cleanup`). @HEAD the mp3 key was changed again by OL-2609-097.
- The Unchanged line's "mirrors `formatters.ts` bug-for-bug by contract" is an assertion placed outside Claims and was not checked.
