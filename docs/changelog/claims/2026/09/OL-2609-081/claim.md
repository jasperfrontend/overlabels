## OL-2609-081 - fix(tts): speak ICU's qualified currency symbols, so "US$ 3,00" is three dollars

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-081`

### Surface
- `app/Services/Tts/SpeakableText.php` - `SYMBOLS` gains ICU's qualified and full-width forms; markers matched longest first; docblock updated
- `tests/Unit/SpeakableTextTest.php` - four tests added, one walking every app locale against every known currency

### Claims
- **C1** [code] `SpeakableText::SYMBOLS` maps `US$`, `$US` to `USD`; `CA$`, `C$`, `$CA` to `CAD`; `AU$`, `A$`, `$AU` to `AUD`; `JP¥`, `￥` to `JPY`; `£GB` to `GBP`; alongside the pre-existing `€`, `$`, `£`, `¥`.
- **C2** [code] `SpeakableText::prepare()` builds its marker alternation from `SYMBOLS` and `CURRENCIES` sorted by `mb_strlen` descending, so a multi-character marker is tried before a single-character one it contains.
- **C3** [code] `SpeakableText::CURRENCIES` is unchanged: `USD`, `EUR`, `GBP`, `JPY`, `CAD`, `AUD`.
- **C4** [code] `NumberFormatter('nl-NL', NumberFormatter::CURRENCY)->formatCurrency(3, 'USD')` yields `US$` followed by a no-break space and `3,00` on the local ICU, and `prepare('Jo Example tipped '.$that)` returns `Jo Example tipped 3 dollars`.
- **C5** [test] `SpeakableTextTest` asserts, for each of the ten locales `en-US`, `en-GB`, `nl-NL`, `nl-BE`, `de-DE`, `fr-FR`, `es-ES`, `pt-BR`, `ja-JP`, `ko-KR` and each of the six currencies in `CURRENCIES`, that `prepare()` of the ICU-formatted 13.37 yields the spoken words, naming the locale, code and formatted string on failure.
- **C6** [test] `SpeakableTextTest` asserts `13,37 $US`, `13,37 £GB`, `￥13` and `CA$13.37` are spoken as words, and that `BONUS$5` is left as written.
- **C7** [unverified] Against the pre-fix `SpeakableText.php` (stashed), three of the four added tests failed (the `BONUS$5` guard passed both ways) and the nineteen pre-existing ones passed; a scratch matrix of the same ten locales by six currencies showed 22 of 60 cells leaving the raw amount in the text before the fix and 0 after.
- **C8** [unverified] On prod, a Ko-fi test tip of $3 on Dutch settings was spoken by ElevenLabs as "three thousand dollar". Reported by Jasper, 2026-09-14.

### Unchanged
- `NormalizedExternalEvent::withFormattedAmount()` (OL-2609-073) still writes `event.formatted_amount` with `NumberFormatter::CURRENCY` in the streamer's locale; the displayed and chat-posted strings are not touched, only the sentence `TtsService` hands to `SpeakableText::prepare()`.
- `SpeakableText::splitAmount()` and `rewrite()` are not in the diff: the digits were never the problem, the unrecognised marker in front of them was.
- A currency outside `CURRENCIES` (a `pt-BR` `R$` amount, for example) is left as written, as before.

### Risk
None beyond the spoken line changing for the affected combinations: the twenty-two locale and currency pairings that were passed through verbatim are now spoken as words.
