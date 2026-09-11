## OL-2609-065 - fix(tts): rewrite currency amounts into words before ElevenLabs sees them

**Shipped:** 2026-09-11
**Commit:** `git log --grep=OL-2609-065`

### Surface
- `app/Services/Tts/SpeakableText.php` - new file, `prepare()` rewrites currency amounts in a TTS sentence
- `app/Services/Tts/TtsService.php` - `synthesize()` calls `SpeakableText::prepare()`; class docblock notes it
- `tests/Unit/SpeakableTextTest.php` - new file

### Claims
- **C1** [code] `SpeakableText::prepare()` is a pure static taking one string and returning one string; it reads no config, no database and no request state.
- **C2** [code] `TtsService::synthesize()` calls `SpeakableText::prepare()` after the `trim()`/empty-string guard and before `cacheKey()`, so the cache key hashes the prepared text and two inputs that speak identically share one mp3 file.
- **C3** [code] `SpeakableText::prepare()` is the only call site of that class in `app/`.
- **C4** [code] `SpeakableText::CURRENCIES` has exactly the keys `USD`, `EUR`, `GBP`, `JPY`, `CAD`, `AUD`, and `SpeakableText::SYMBOLS` maps `€`, `$`, `£`, `¥` onto four of those codes.
- **C5** [code] A currency code or symbol not present in those two constants leaves its amount unrewritten, because `SpeakableText::rewrite()` returns null for an unknown code and the callback falls back to `$m[0]`.
- **C6** [code] `SpeakableText::splitAmount()` returns null rather than guessing when a digit run carries a separator followed by neither one, two nor three digits, and `rewrite()` propagates that null so the original text survives.
- **C7** [code] The third `preg_replace` in `prepare()` inserts a space between any three-uppercase-letter run and digits glued to it, including codes absent from `CURRENCIES`.
- **C8** [code] `SpeakableText::GAP` names U+00A0 and U+202F explicitly rather than relying on `\s`.
- **C9** [test] `SpeakableTextTest` has 19 tests covering: the reported euro sentence, symbol-glued and ISO-code-glued amounts, suffix placement, non-breaking spaces, singular/plural units and subunits, suppressed zero subunits, sub-unit-only amounts, a subunit-less currency, both separator conventions, a refused ambiguous shape, unchanged non-money text, a code inside a word, a bare symbol, an unlisted code, and empty input.
- **C10** [unverified] StreamLabs' `formatted_amount` field carries the currency symbol glued to the digits; the repo's own fixtures at `tests/Unit/StreamLabsServiceDriverTest.php` and `tests/Feature/StreamLabsWebhookTest.php` use `$13.37` and `$5.00`.
- **C11** [unverified] ElevenLabs' normalization guidance recommends expanding monetary amounts into spoken words before synthesis, and records that `eleven_multilingual_v2` reads `$1,000,000` correctly unaided.
- **C12** [unverified] How `eleven_multilingual_v2` pronounces either the old or the new string has not been confirmed by listening. Two mp3s were synthesized against the configured voice and model for comparison and are not in the repo.

### Unchanged
- `AlertMessageRenderer::render()` and `renderAlert()` produce the TTS sentence and are not in the diff; the rewrite happens downstream of them, inside `TtsService`, so the `tts` mute gate, the conditionals pass and the single tag pass all behave exactly as before.
- `AlertMessageRenderer::renderMessage()` feeds the bot's chat line from the same tag data and is not in the diff, so a chat message still shows `€84` while the spoken line says "84 euros".
- `PipeFormatter::currency()` is the other place money is turned into a string, mirrors `formatters.ts` bug-for-bug by contract, and is not in the diff.
- `StreamLabsServiceDriver::normalizeEvent()` still writes `event.formatted_amount` through unchanged; the symbol is not stripped at the driver boundary, where it would also change what the overlay draws.
- `config/services.php` is not in the diff: `ELEVENLABS_MODEL_ID` still defaults to `eleven_multilingual_v2`, and no `apply_text_normalization` field was added to the request body in `TtsService::synthesize()`.

### Risk
Existing cached mp3s under `storage/app/public/tts/` are keyed on the pre-rewrite text and are now
unreachable; any sentence containing an amount is synthesized once more on its next use. The
`tts:cleanup` schedule in `routes/console.php` deletes them on its next weekly run past their
seventh day.
