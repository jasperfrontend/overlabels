## Audit of OL-2609-073 - fix(integrations): derive event.formatted_amount for all five donation services, in the streamer's locale

**Audited:** 2026-09-25
**Commit:** 11a4fb7111efbf772193343a6a3c966746a5aa37 (single commit carrying `Changelog: OL-2609-073`)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/External/NormalizedExternalEvent.php:110-136 @11a4fb7` - `withFormattedAmount(string $locale): self` builds `new NumberFormatter($locale, NumberFormatter::CURRENCY)` and calls `formatCurrency((float) $this->amount, strtoupper($this->currency))` (line 121), returns `new self(...)` with the tag merged (line 135). Same method at `:92-117 @HEAD`; the constructor lost `supporterEmail`/`supporterEmailHash`/`privateMetadata` in OL-2609-097 |
| C2 | CONFIRMED | `NormalizedExternalEvent.php:121 @11a4fb7` - currency argument is `strtoupper($this->currency)`, nothing derived from `$locale`; same at `:103 @HEAD` |
| C3 | CONFIRMED | `NormalizedExternalEvent.php:112 @11a4fb7` - `$this->amount === null \|\| ! is_numeric(...)` returns `$this`; `:116` - `$this->currency === null \|\| ! preg_match('/^[A-Za-z]{3}$/', ...)` returns `$this`; same at `:94,:98 @HEAD` |
| C4 | CONFIRMED | `NormalizedExternalEvent.php:10 @11a4fb7` - `final readonly class`; method returns `new self` (line 127) and never assigns to `$this`; same @HEAD |
| C5 | CONFIRMED | `NormalizedExternalEvent.php:135 @11a4fb7` - `array_merge($this->templateTags, ['event.formatted_amount' => $formatted])`; same at `:117 @HEAD` |
| C6 | CONFIRMED | `app/Http/Controllers/Api/ExternalWebhookController.php:140-141 @11a4fb7` - `$driver->normalizeEvent($payload, $eventType)->withFormattedAmount($user->locale)`; `ExternalEvent::create` at line 157 writes `'normalized_payload' => $normalizedEvent->getTemplateTags()` (line 167). @HEAD call at line 141, create at 152 (lines shifted by OL-2609-097) |
| C7 | CONFIRMED | `git show 11a4fb7:app/Services/External/Drivers/StreamLabsServiceDriver.php \| grep formatted` - no match; diff removes the `$formattedAmount` read and the `event.formatted_amount` entry |
| C8 | CONFIRMED | `git grep "formatted_amount\|formattedAmount" 11a4fb7 -- app/` - matches only `NormalizedExternalEvent.php` and a docblock in `SpeakableText.php`; nothing under `Drivers/`. Same @HEAD |
| C9 | CONFIRMED | `tests/Unit/FormattedDonationAmountTest.php @11a4fb7` dataset has en-US/USD `$13.37`, nl-NL/EUR `€ 13,37`, fr-FR/EUR `13,37 €` (plus de-DE), compared after `preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', ...)`. `php artisan test --filter=FormattedDonationAmountTest`: 14 passed |
| C10 | CONFIRMED | same file, test "keeps the currency the donation arrived in" - USD at nl-NL, `toContain('13,37')` and `not->toContain('€')`; passed in the run above |
| C11 | CONFIRMED | `tests/Feature/ExternalWebhookTest.php:575-591 @11a4fb7` - Ko-fi payload, user locale set to nl-NL, asserts whitespace-normalised `normalized_payload['event.formatted_amount']` is `US$ 5,00`. `php artisan test --filter="stores a derived formatted amount"`: 1 passed |
| C12 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a removed tree is the case the tag is for |
| C13 | CONFIRMED | `tests/Feature/IntegrationEventTagDocsTest.php:111-127 @11a4fb7` - globs `help/reference/eventsub-tags/*.md`, collects files containing `[[[event.amount]]]` but not `[[[event.formatted_amount]]]`, expects none. `php artisan test --filter="documents the derived formatted amount"`: 1 passed |
| C14 | CONFIRMED | at @11a4fb7 all five pages (`ko-fi-donation-and-subscription-events.md`, `all-buy-me-a-coffee-events.md`, `all-throne-events.md`, `fourthwall-donation-event-tags.md`, `streamlabs-donation-event-tags.md`) carry the line "Amount and currency written in your locale ... :: derived by Overlabels from the amount and currency above, not supplied by the service". Present @HEAD (streamlabs page line 8; OL-2609-074 changed only its intro and note lines) |

### Surface
Complete.

### Findings
- **F1** false statement under Unchanged - the second Unchanged line says `SpeakableText`'s "`SYMBOLS` map covers the symbols ICU produces", but `app/Services/Tts/SpeakableText.php:54-59 @11a4fb7` lists only `€`, `$`, `£`, `¥`. ICU gives `US$` + U+00A0 + `5,00` for nl-NL/USD (bytes `555324c2a0352c3030`), the same value C11 asserts. Running the shipped `SpeakableText::prepare("Jo tipped " . that)` returns `Jo tipped US$ 5,00` unchanged. OL-2609-081 later added the qualified symbols and cites OL-2609-073, but it does not say it corrects this line. The reader should record the correction in a new claim that cites OL-2609-073 Unchanged and OL-2609-081.
- **F2** reverses an earlier claim without citing it - `docs/changelog/claims/2026/09/OL-2609-065/claim.md:29` records "`StreamLabsServiceDriver::normalizeEvent()` still writes `event.formatted_amount` through unchanged; the symbol is not stripped at the driver boundary, where it would also change what the overlay draws". C7 of this claim removes that pass-through, and the Risk section describes what the overlay now draws, but no line of `claim.md` cites OL-2609-065. Only the commit message mentions it. The reader should add the inline citation in a follow-up claim.

### Notes
- C11: the stored string separates `US$` and `5,00` with U+00A0, not an ASCII space. The test compares after normalising whitespace, as C9 says outright and C11 does not.
- Tests were run against the working tree at HEAD, not a checkout of 11a4fb7. None of the test files named has changed since 11a4fb7 (`git log 11a4fb7..HEAD` over them is empty).
- `SpeakableText`'s tests: 19 @11a4fb7, 23 @HEAD after OL-2609-081; `php artisan test --filter=SpeakableText` gives 23 passed. The shipped `SpeakableText.php:11-13 @11a4fb7` docblock still said the other four drivers expose no formatted amount; it was updated at HEAD (line 20 cites OL-2609-073).
- Changes to the audited files since 11a4fb7 all come from later claims: OL-2609-074 (Streamlabs spelling), OL-2609-096 (driver `raw` scrubbed), OL-2609-097 (supporter email fields removed from the DTO and the controller).
- `withFormattedAmount()` also returns `$this` when `formatCurrency()` returns false (`NormalizedExternalEvent.php:124 @11a4fb7`). C3 does not list this case and does not claim to be exhaustive.
