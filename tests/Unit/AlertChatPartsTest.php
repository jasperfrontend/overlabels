<?php

use App\Services\Messages\AlertMessageRenderer;

test('a message that fits is one part, trimmed', function () {
    expect(AlertMessageRenderer::chatParts('  hello chat  '))->toBe(['hello chat']);
});

test('null or blank gives no parts', function () {
    expect(AlertMessageRenderer::chatParts(null))->toBe([])
        ->and(AlertMessageRenderer::chatParts('   '))->toBe([]);
});

test('exactly 500 characters is one part', function () {
    $text = str_repeat('a', 500);

    expect(AlertMessageRenderer::chatParts($text))->toBe([$text]);
});

test('a long message splits at the last space that fits', function () {
    $first = str_repeat('a', 495).' bbbb'; // 500 chars, ends on a word
    $text = $first.' cccc';

    expect(AlertMessageRenderer::chatParts($text))->toBe([$first, 'cccc']);
});

test('a single word longer than a message is cut hard', function () {
    $text = str_repeat('x', 600);

    expect(AlertMessageRenderer::chatParts($text))->toBe([str_repeat('x', 500), str_repeat('x', 100)]);
});

test('every part fits, and output past three parts is dropped', function () {
    $text = trim(str_repeat('word ', 500)); // 2499 chars

    $parts = AlertMessageRenderer::chatParts($text);

    expect($parts)->toHaveCount(3);
    foreach ($parts as $part) {
        expect(mb_strlen($part))->toBeLessThanOrEqual(500)
            ->and($part)->toStartWith('word')
            ->and($part)->toEndWith('word');
    }
});

test('length is counted in characters, not bytes', function () {
    // 'é' is two bytes; 400 of them is 800 bytes but only 400 characters.
    $text = str_repeat('é', 400);

    expect(AlertMessageRenderer::chatParts($text))->toBe([$text]);
});
