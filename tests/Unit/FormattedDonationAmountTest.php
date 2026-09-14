<?php

use App\Services\External\NormalizedExternalEvent;

/**
 * `event.formatted_amount` used to be StreamLabs' own string, passed through by
 * their driver and by no other. Two things were wrong with that: it was the one
 * donation tag the other four services could not offer, and the formatting was
 * StreamLabs' rather than the reader's, so a Dutch streamer was shown "$13.37"
 * whatever their locale said.
 *
 * It is derived now, from the amount and currency every donation driver already
 * emits. The currency is always the DONATION's; only the punctuation and
 * placement follow the streamer.
 */
function donationEvent(?string $amount, ?string $currency, array $tags = []): NormalizedExternalEvent
{
    return new NormalizedExternalEvent(
        service: 'streamlabs',
        eventType: 'donation',
        messageId: 'evt_1',
        fromName: 'TestDonor',
        message: 'Great stream!',
        amount: $amount,
        currency: $currency,
        templateTags: $tags,
        raw: [],
    );
}

it('writes the donation currency in the streamer locale', function (string $locale, string $currency, string $expected) {
    $tags = donationEvent('13.37', $currency)->withFormattedAmount($locale)->getTemplateTags();

    // Compared with the spaces normalised: ICU emits a non-breaking or narrow
    // no-break space for several locales, which is correct and invisible.
    $actual = preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $tags['event.formatted_amount']);

    expect($actual)->toBe($expected);
})->with([
    ['en-US', 'USD', '$13.37'],
    ['nl-NL', 'EUR', '€ 13,37'],
    ['fr-FR', 'EUR', '13,37 €'],
    ['de-DE', 'EUR', '13,37 €'],
]);

it('keeps the currency the donation arrived in, not the one the locale implies', function () {
    // The half of this that matters: a dollar tip to a Dutch streamer is still
    // dollars. Only the punctuation is Dutch.
    $tags = donationEvent('13.37', 'USD')->withFormattedAmount('nl-NL')->getTemplateTags();

    expect($tags['event.formatted_amount'])->toContain('13,37')
        ->and($tags['event.formatted_amount'])->not->toContain('€');
});

it('adds nothing when there is no amount to format', function (?string $amount, ?string $currency) {
    $tags = donationEvent($amount, $currency)->withFormattedAmount('en-US')->getTemplateTags();

    // An absent tag renders as nothing, which is the platform's answer for a
    // value that does not exist.
    expect($tags)->not->toHaveKey('event.formatted_amount');
})->with([
    [null, 'USD'],
    ['', 'USD'],
    ['not a number', 'USD'],
    ['13.37', null],
    ['13.37', ''],
    ['13.37', 'DOLLARS'],
]);

it('leaves every other tag alone', function () {
    $event = donationEvent('5.00', 'USD', ['event.from_name' => 'TestDonor', 'event.source' => 'StreamLabs']);

    $tags = $event->withFormattedAmount('en-US')->getTemplateTags();

    expect($tags['event.from_name'])->toBe('TestDonor')
        ->and($tags['event.source'])->toBe('StreamLabs')
        ->and($tags)->toHaveKey('event.formatted_amount');
});

it('replaces a value a service supplied itself', function () {
    // StreamLabs still sends formatted_amount in its payload. Its driver no
    // longer reads it, but if any service ever writes the tag directly, the
    // derived value is the one that survives.
    $event = donationEvent('13.37', 'EUR', ['event.formatted_amount' => '$13.37']);

    expect($event->withFormattedAmount('nl-NL')->getTemplateTags()['event.formatted_amount'])->not->toBe('$13.37');
});

it('does not mutate the event it was called on', function () {
    $event = donationEvent('5.00', 'USD');

    $event->withFormattedAmount('en-US');

    expect($event->getTemplateTags())->not->toHaveKey('event.formatted_amount');
});
