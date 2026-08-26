<?php

use App\Support\Dsl;

/**
 * The shared DSL spec (resources/dsl/dsl.json) is the single source of truth for
 * the template language. These tests pin the lexical shape and lock every
 * divergence documented as D1-D7 in docs/design/overlabels-dsl-spec.md, so the
 * five formerly hand-maintained matchers cannot drift apart again.
 */
it('loads the shared spec', function () {
    $spec = Dsl::spec();

    expect($spec)->toBeArray()
        ->and($spec['version'])->toBe(1)
        ->and($spec['lexical'])->toBeArray()
        ->and($spec['formatters'])->toBeArray();
});

it('exposes every shipped formatter', function () {
    // The spec is the vocabulary of record. 11 formatters ship; the docs said 8
    // before this was written down.
    expect(Dsl::formatterNames())->toEqualCanonicalizing([
        'round', 'number', 'currency', 'date', 'duration', 'distance',
        'speed', 'uppercase', 'lowercase', 'login', 'mention',
    ]);

    expect(Dsl::isFormatter('duration'))->toBeTrue()
        ->and(Dsl::isFormatter('durtaion'))->toBeFalse();
});

it('orders comparison operators longest-first so >= beats >', function () {
    $ops = Dsl::comparisonOperators();

    expect(array_search('>=', $ops, true))->toBeLessThan(array_search('>', $ops, true))
        ->and(array_search('<=', $ops, true))->toBeLessThan(array_search('<', $ops, true))
        ->and(array_search('!=', $ops, true))->toBeLessThan(array_search('=', $ops, true));
});

// ---------------------------------------------------------------------------
// The canonical tag pattern
// ---------------------------------------------------------------------------

it('captures key, pipe and default', function (string $input, ?string $key, ?string $pipe, ?string $default) {
    preg_match(Dsl::tagPattern(), $input, $m);

    expect($m[1] ?? null)->toBe($key)
        ->and(($m[2] ?? '') !== '' ? $m[2] : null)->toBe($pipe)
        ->and(($m[3] ?? '') !== '' ? $m[3] : null)->toBe($default);
})->with([
    ['[[[followers_total]]]', 'followers_total', null, null],
    ['[[[c:kofi:donations_received]]]', 'c:kofi:donations_received', null, null],
    ['[[[followers_total|number]]]', 'followers_total', 'number', null],
    ['[[[uptime|duration:hh:mm:ss]]]', 'uptime', 'duration:hh:mm:ss', null],
    ['[[[followers_total ?? 0]]]', 'followers_total', null, '0'],
    ['[[[c:kofi:total|currency:EUR ?? nothing yet]]]', 'c:kofi:total', 'currency:EUR', 'nothing yet'],
    ['[[[started_at|date:dd-MM-yyyy HH:mm]]]', 'started_at', 'date:dd-MM-yyyy HH:mm', null],
]);

it('D1: accepts % in pipe args', function () {
    // The extractor allowed % and the renderers did not, so a percentage format
    // was allowlisted server-side then printed to the page as literal text.
    preg_match(Dsl::tagPattern(), '[[[progress|number:0.0%]]]', $m);

    expect($m[1])->toBe('progress')
        ->and($m[2])->toBe('number:0.0%');
});

it('D8: never captures trailing whitespace into the pipe', function () {
    // The greedy pipe class used to eat the space before `??`, handing the
    // formatter "EUR " instead of "EUR". PHP trimmed and survived; the TS
    // renderer did not, so Intl.NumberFormat rejected the currency code.
    preg_match(Dsl::tagPattern(), '[[[c:kofi:total|currency:EUR ?? none]]]', $m);

    expect($m[2])->toBe('currency:EUR')
        ->and($m[3])->toBe('none');
});

it('D8: still allows meaningful inner spaces in pipe args', function () {
    preg_match(Dsl::tagPattern(), '[[[started_at|date:dd-MM-yyyy HH:mm ?? never]]]', $m);

    expect($m[2])->toBe('date:dd-MM-yyyy HH:mm')
        ->and($m[3])->toBe('never');
});

it('tolerates padding around a tag body', function () {
    preg_match(Dsl::tagPattern(), '[[[followers_total|number ]]]', $m);

    expect($m[1])->toBe('followers_total')
        ->and($m[2])->toBe('number');
});

it('D2: requires a key to start with a word character', function () {
    // The renderers accepted a leading `:` that the extractor rejected, so the
    // tag substituted to empty and never entered the allowlist.
    expect(preg_match(Dsl::tagPattern(), '[[[:foo]]]'))->toBe(0)
        ->and(preg_match(Dsl::tagPattern(), '[[[-foo]]]'))->toBe(0)
        ->and(preg_match(Dsl::tagPattern(), '[[[foo]]]'))->toBe(1);
});

it('accepts hyphens inside a key but not as the first character', function () {
    preg_match(Dsl::tagPattern(), '[[[c:some-service:key]]]', $m);

    expect($m[1])->toBe('c:some-service:key');
});

it('keeps the key clean when extracting for the allowlist', function () {
    // tagKeyPattern must tolerate a pipe and a default while capturing only the
    // key, otherwise the real value is never fetched and the default always wins.
    preg_match_all(
        Dsl::tagKeyPattern(),
        '[[[a]]] [[[b|round:2]]] [[[c ?? fallback]]] [[[d|currency:EUR ?? none]]]',
        $m
    );

    expect($m[1])->toBe(['a', 'b', 'c', 'd']);
});

// ---------------------------------------------------------------------------
// Conditions
// ---------------------------------------------------------------------------

it('captures the referenced key from if and elseif', function (string $input, string $key) {
    preg_match(Dsl::conditionPattern(), $input, $m);

    expect($m[1] ?? null)->toBe($key);
})->with([
    ['[[[if:is_live]]]', 'is_live'],
    ['[[[if:event.bits >= 100]]]', 'event.bits'],
    ['[[[elseif:c:kofi:total > 0]]]', 'c:kofi:total'],
    ['[[[if:event.user_name = someone]]]', 'event.user_name'],
]);

it('D5: accepts hyphens in condition keys', function () {
    // Plain tags allowed hyphens; conditions did not, so `[[[if:my-tag = 1]]]`
    // never contributed `my-tag` to the allowlist even though `[[[my-tag]]]` did.
    preg_match(Dsl::conditionPattern(), '[[[if:my-tag = 1]]]', $m);

    expect($m[1])->toBe('my-tag');
});

it('D7: allows a lone ] inside a compared value', function () {
    preg_match(Dsl::conditionPattern(), '[[[if:label = a]b]]]', $m);

    expect($m[1])->toBe('label');
});

it('still refuses to swallow the closing bracket', function () {
    // The body must never consume `]]]` itself, or a condition would run past
    // the end of its own token.
    preg_match_all(Dsl::conditionPattern(), '[[[if:a = 1]]] middle [[[if:b = 2]]]', $m);

    expect($m[1])->toBe(['a', 'b']);
});

it('tokenises every block keyword with the same groups as the TypeScript engine', function () {
    $text = '[[[if:a > 1]]]x[[[elseif:b]]]y[[[else]]]z[[[endif]]][[[foreach:subs as s]]]q[[[endforeach]]]';
    preg_match_all(Dsl::blockTokenPattern(), $text, $m, PREG_SET_ORDER);

    expect(array_column($m, 1))->toBe(['if:a > 1', 'elseif:b', 'else', 'endif', 'foreach:subs as s', 'endforeach']);
    expect($m[0][2])->toBe('a > 1');
    expect($m[1][3])->toBe('b');
    expect($m[4][4])->toBe('subs as s');
});

it('splits a condition body into key, operator and value, longest operator first', function (string $body, array $expected) {
    preg_match(Dsl::conditionBodyPattern(), $body, $m);

    expect(array_slice($m, 1))->toBe($expected);
})->with([
    'gte' => ['c:wins >= 10', ['c:wins', '>=', '10']],
    'neq no spaces' => ['c:wins!=1', ['c:wins', '!=', '1']],
    'eq string' => ['bot:args.0 = hello there', ['bot:args.0', '=', 'hello there']],
    'hyphen key' => ['my-tag < 3', ['my-tag', '<', '3']],
]);

it('leaves a bare condition unmatched so it is evaluated for truthiness', function () {
    expect(preg_match(Dsl::conditionBodyPattern(), 'c:flag'))->toBe(0);
});
