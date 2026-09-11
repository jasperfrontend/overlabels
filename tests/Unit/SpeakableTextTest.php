<?php

use App\Services\Tts\SpeakableText;

// ──────────────────────────────────────────────────────────────────────────────
// The reported bug: StreamLabs' formatted_amount glues the symbol to the digits
// ──────────────────────────────────────────────────────────────────────────────

test('the euro sentence that started this is spoken as words', function () {
    expect(SpeakableText::prepare('Kevin just donated €84 through StreamLabs'))
        ->toBe('Kevin just donated 84 euros through StreamLabs');
});

test('a symbol glued to digits becomes words', function () {
    expect(SpeakableText::prepare('€84'))->toBe('84 euros');
    expect(SpeakableText::prepare('$13.37'))->toBe('13 dollars and 37 cents');
    expect(SpeakableText::prepare('£1,001.32'))->toBe('1001 pounds and 32 pence');
    expect(SpeakableText::prepare('¥1,000'))->toBe('1000 yen');
});

test('an ISO code glued to digits becomes words', function () {
    // The shape the other four donation drivers produce, because they expose
    // event.currency + event.amount and no formatted amount.
    expect(SpeakableText::prepare('worth EUR84!'))->toBe('worth 84 euros!');
    expect(SpeakableText::prepare('USD 5.00'))->toBe('5 dollars');
});

test('the amount may trail its currency', function () {
    expect(SpeakableText::prepare('84€'))->toBe('84 euros');
    expect(SpeakableText::prepare('84,50 EUR'))->toBe('84 euros and 50 cents');
});

test('a non-breaking space between symbol and digits still matches', function () {
    expect(SpeakableText::prepare("€\u{00A0}84"))->toBe('84 euros');
    expect(SpeakableText::prepare("84\u{202F}€"))->toBe('84 euros');
});

// ──────────────────────────────────────────────────────────────────────────────
// Singular, subunits, zero
// ──────────────────────────────────────────────────────────────────────────────

test('one unit is singular', function () {
    expect(SpeakableText::prepare('€1'))->toBe('1 euro');
    expect(SpeakableText::prepare('$1.01'))->toBe('1 dollar and 1 cent');
});

test('zero subunits are not spoken', function () {
    expect(SpeakableText::prepare('€84.00'))->toBe('84 euros');
    expect(SpeakableText::prepare('$0.00'))->toBe('0 dollars');
});

test('a single fraction digit is tenths, not units', function () {
    expect(SpeakableText::prepare('€84.5'))->toBe('84 euros and 50 cents');
});

test('an amount under one unit is spoken as subunits alone', function () {
    expect(SpeakableText::prepare('€0.50'))->toBe('50 cents');
    expect(SpeakableText::prepare('£0.01'))->toBe('1 penny');
});

test('a currency with no subunit ignores one', function () {
    expect(SpeakableText::prepare('¥500.25'))->toBe('500 yen');
});

// ──────────────────────────────────────────────────────────────────────────────
// Separator conventions
// ──────────────────────────────────────────────────────────────────────────────

test('the last of two separators is the decimal point', function () {
    expect(SpeakableText::prepare('$1,234.56'))->toBe('1234 dollars and 56 cents');
    expect(SpeakableText::prepare('€1.234,56'))->toBe('1234 euros and 56 cents');
});

test('a lone separator before three digits is grouping in either convention', function () {
    expect(SpeakableText::prepare('$1,234'))->toBe('1234 dollars');
    expect(SpeakableText::prepare('€1.234'))->toBe('1234 euros');
});

test('a trailing separator is the sentence, not the number', function () {
    expect(SpeakableText::prepare('Thanks for the €84.'))->toBe('Thanks for the 84 euros.');
});

test('a shape we cannot read with confidence is left alone', function () {
    expect(SpeakableText::prepare('€1,2345'))->toBe('€1,2345');
});

// ──────────────────────────────────────────────────────────────────────────────
// What it must not touch
// ──────────────────────────────────────────────────────────────────────────────

test('text without money is unchanged', function () {
    $line = 'Kevin just raided us with 84 viewers!';

    expect(SpeakableText::prepare($line))->toBe($line);
});

test('a currency code inside a word is not a currency', function () {
    expect(SpeakableText::prepare('GRANDEUR84'))->toBe('GRANDEUR84');
    expect(SpeakableText::prepare('EUROPE 84'))->toBe('EUROPE 84');
});

test('a bare symbol with no amount is left alone', function () {
    expect(SpeakableText::prepare('thanks for the €'))->toBe('thanks for the €');
});

test('an unlisted currency keeps its code but loses the glue', function () {
    // No words for it, but the number alone is readable.
    expect(SpeakableText::prepare('SEK84'))->toBe('SEK 84');
});

test('empty text stays empty', function () {
    expect(SpeakableText::prepare(''))->toBe('');
});
