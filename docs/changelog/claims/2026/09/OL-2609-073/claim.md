## OL-2609-073 - fix(integrations): derive event.formatted_amount for all five donation services, in the streamer's locale

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-073`

### Surface
- `app/Services/External/NormalizedExternalEvent.php` - `withFormattedAmount()` added
- `app/Http/Controllers/Api/ExternalWebhookController.php` - calls it after `normalizeEvent()`, before the event is stored
- `app/Services/External/Drivers/StreamLabsServiceDriver.php` - the `event.formatted_amount` pass-through removed
- `resources/help/reference/eventsub-tags/streamlabs-donation-event-tags.md` - entry reworded
- `resources/help/reference/eventsub-tags/ko-fi-donation-and-subscription-events.md` - entry added
- `resources/help/reference/eventsub-tags/all-buy-me-a-coffee-events.md` - entry added
- `resources/help/reference/eventsub-tags/all-throne-events.md` - entry added
- `resources/help/reference/eventsub-tags/fourthwall-donation-event-tags.md` - entry added
- `resources/help/pages/conditionals.md` - table row reworded
- `tests/Unit/FormattedDonationAmountTest.php` - new file
- `tests/Unit/StreamLabsServiceDriverTest.php` - the pass-through assertion removed
- `tests/Feature/ExternalWebhookTest.php` - one test added
- `tests/Feature/IntegrationEventTagDocsTest.php` - one test added

### Claims
- **C1** [code] `NormalizedExternalEvent::withFormattedAmount(string $locale)` returns a new instance whose `templateTags` carry `event.formatted_amount`, formatted by `NumberFormatter($locale, NumberFormatter::CURRENCY)::formatCurrency()` using the event's own `amount` and `currency`.
- **C2** [code] The currency passed to the formatter is the event's `currency`, never one implied by the locale.
- **C3** [code] `withFormattedAmount()` returns `$this` unchanged when `amount` is null or non-numeric, when `currency` is null, or when `currency` does not match `/^[A-Za-z]{3}$/`, so the tag is absent rather than empty.
- **C4** [code] `withFormattedAmount()` mutates nothing: the receiver's `templateTags` are unchanged after the call.
- **C5** [code] A value already present under `event.formatted_amount` is replaced by the derived one, because `array_merge` puts the derived entry last.
- **C6** [code] `ExternalWebhookController` calls `withFormattedAmount($user->locale)` on the result of `normalizeEvent()` before `ExternalEvent::create()`, so the stored `normalized_payload` carries the tag.
- **C7** [code] `StreamLabsServiceDriver::normalizeEvent()` no longer reads `formatted_amount` from the payload and no longer writes `event.formatted_amount`.
- **C8** [code] No class under `app/Services/External/Drivers/` writes an `event.formatted_amount` tag.
- **C9** [test] `FormattedDonationAmountTest` asserts en-US renders `$13.37`, nl-NL renders `€ 13,37` and fr-FR renders `13,37 €` for the same amount, with whitespace normalised.
- **C10** [test] `FormattedDonationAmountTest` asserts a USD donation to an nl-NL streamer contains `13,37` and no euro sign.
- **C11** [test] `ExternalWebhookTest` asserts a Ko-fi donation of 5.00 USD to an nl-NL streamer stores `US$ 5,00` in `normalized_payload['event.formatted_amount']`, proving the derivation reaches a service whose payload never carried one.
- **C12** [unverified] That test was run against a tree with the `withFormattedAmount()` call removed from `ExternalWebhookController` and failed; it passed again once restored.
- **C13** [test] `IntegrationEventTagDocsTest` asserts every file under `resources/help/reference/eventsub-tags/` that documents `[[[event.amount]]]` also documents `[[[event.formatted_amount]]]`.
- **C14** [code] All five donation reference pages document `[[[event.formatted_amount]]]`, and each describes it as locale-written and derived by Overlabels rather than supplied by the service.

### Unchanged
- `ExternalEventController::replay()` reads the stored `normalized_payload` rather than re-normalising, so it inherits the derived tag with no change; it is not in the diff.
- `SpeakableText::prepare()` already matched locale-formatted currency: its `GAP` constant names the non-breaking and narrow no-break spaces `NumberFormatter::CURRENCY` emits, and its `SYMBOLS` map covers the symbols ICU produces. It is not in the diff and its 19 tests pass unchanged.
- `PipeFormatter::currency()` still backs the `|currency` pipe and still resolves an absent argument through `LOCALE_CURRENCY_MAP`. It formats a value in the locale's own currency, which is a different question from this one, and is not in the diff.
- `BotCheckinController` and `BotTowerController` also build a `NormalizedExternalEvent`; neither carries an amount or a currency, so neither calls `withFormattedAmount()` and neither is in the diff.
- The `event.amount` and `event.currency` tags every donation driver emits are untouched, so a template written against those renders exactly as before.

### Risk
`event.formatted_amount` changes what it renders for StreamLabs. It was StreamLabs' own string, in their formatting, whatever locale the account ran; it is now the same money written in the streamer's locale, so an en-US account sees no change and an nl-NL account sees `€ 45,00` where it previously saw `€45`.

Templates on the other four donation services that previously rendered nothing for this tag now render a value.
