## Audit of OL-2609-081 - fix(tts): speak ICU's qualified currency symbols, so "US$ 3,00" is three dollars

**Audited:** 2026-09-25
**Commit:** 8caf850bfef224bae6d8aac47efd46224371f141
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Tts/SpeakableText.php:70-86 @8caf850` - `SYMBOLS` holds `€`,`$`,`£`,`¥` plus `￥`,`US$`,`$US`,`CA$`,`C$`,`$CA`,`AU$`,`A$`,`$AU`,`JP¥`,`£GB`, each mapped to the stated code; file unchanged @HEAD (`git log 8caf850..HEAD -- app/Services/Tts/SpeakableText.php` is empty) |
| C2 | CONFIRMED | `app/Services/Tts/SpeakableText.php:104-109 @8caf850` - `$markers = array_merge(array_keys(self::SYMBOLS), array_keys(self::CURRENCIES))`, `usort` by `mb_strlen($b) <=> mb_strlen($a)`, then `preg_quote`d into `$money`; same @HEAD |
| C3 | CONFIRMED | `app/Services/Tts/SpeakableText.php:53-60 @8caf850` - keys `USD`,`EUR`,`GBP`,`JPY`,`CAD`,`AUD`; no diff hunk touches the constant; same @HEAD |
| C4 | CONFIRMED | local ICU 74.2: `formatCurrency(3,'USD')` for `nl-NL` is bytes `555324c2a0332c3030` (`US$` U+00A0 `3,00`); `php artisan tinker` `SpeakableText::prepare('Jo Example tipped '.$f)` returned `Jo Example tipped 3 dollars` (@HEAD, file identical to @8caf850) |
| C5 | CONFIRMED | `tests/Unit/SpeakableTextTest.php:129-148 @8caf850` - loops the ten named locales by the six `CURRENCIES` codes, formats 13.37, asserts the spoken words with message `"{$locale} {$code} formats as {$formatted}"`; `php artisan test --filter=SpeakableTextTest` - 23 passed (97 assertions) |
| C6 | CONFIRMED | `tests/Unit/SpeakableTextTest.php:150-155 @8caf850` asserts `13,37 $US`, `13,37 £GB`, `￥13`, `CA$13.37` as words; `:157-159` asserts `BONUS$5` unchanged; both passed in the run above |
| C7 | UNVERIFIABLE | tagged [unverified] (fail-first run against a stashed tree and a scratch matrix outside the repo) |
| C8 | UNVERIFIABLE | tagged [unverified] (prod observation) |

### Surface
Complete.

### Findings
- **F1** contradiction with the record, not cited - `docs/changelog/claims/2026/09/OL-2609-073/claim.md:39` states under Unchanged that `SpeakableText`'s "`SYMBOLS` map covers the symbols ICU produces". This change exists because that was false, and it adds the missing ICU symbols (`SpeakableText.php:75-85 @8caf850`). OL-2609-081 cites OL-2609-073 only in its first Unchanged line (about `withFormattedAmount()`) and never says it corrects that 073 statement. The reader should add a new claim that says "corrects OL-2609-073 Unchanged (line 2)" and cites OL-2609-081. OL-2609-073 audit F1 flags the same gap from the 073 side.

### Notes
- Unchanged lines hold: the diff touches only `SpeakableText.php` and `SpeakableTextTest.php`, so `NormalizedExternalEvent::withFormattedAmount()` and `TtsService` are not in it. Inside `SpeakableText.php` the hunks stop at `prepare()` line 112 @8caf850, so `rewrite()` (`:135`) and `splitAmount()` (`:188`) are untouched. `prepare('Jo tipped R$ 13,37')` (pt-BR/BRL) comes back unchanged.
- C7's fail-first half could be re-run from the parent commit. The guide places fail-first runs under [unverified], so this is not a mistag.
