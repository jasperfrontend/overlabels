<?php

use App\Support\ControlConditions;

/**
 * `config.enabled_if` as a recipe is held to it: every condition reads a
 * knob of the same overlay, in the `[[[if:...]]]` grammar.
 */
function conditionControls(array $enabledIf, array $overrides = []): array
{
    return [
        ['key' => 'look', 'type' => 'text', 'config' => ['choices' => [['value' => 'bubble'], ['value' => 'plain']]]],
        ['key' => 'speed', 'type' => 'number', 'config' => ['min' => 1, 'max' => 10]],
        ['key' => 'shadow', 'type' => 'boolean', 'config' => null],
        ['key' => 'hits', 'type' => 'counter', 'config' => null],
        array_merge(['key' => 'tint', 'type' => 'color', 'config' => ['enabled_if' => $enabledIf]], $overrides),
    ];
}

it('reads one condition or a list, and nothing else', function () {
    expect(ControlConditions::of(['enabled_if' => 'c:look != plain']))->toBe(['c:look != plain'])
        ->and(ControlConditions::of(['enabled_if' => [' c:look != plain', 'c:speed > 4']]))->toBe(['c:look != plain', 'c:speed > 4'])
        ->and(ControlConditions::of(['enabled_if' => []]))->toBeNull()
        ->and(ControlConditions::of(['enabled_if' => ['look' => 'plain']]))->toBeNull()
        ->and(ControlConditions::of(['enabled_if' => ['c:look != plain', 3]]))->toBeNull()
        ->and(ControlConditions::of(['enabled_if' => ['c:look != plain', '']]))->toBeNull()
        ->and(ControlConditions::of(['enabled_if' => ['c:look != plain', '   ']]))->toBeNull()
        ->and(ControlConditions::of(['enabled_if' => '']))->toBeNull()
        ->and(ControlConditions::of(['min' => 1]))->toBeNull()
        ->and(ControlConditions::of(null))->toBeNull();
});

it('accepts conditions on knobs in every operator form the if tag has', function () {
    expect(ControlConditions::problems(conditionControls(['c:look != plain', 'c:look = bubble', 'c:speed >= 4', 'c:shadow'])))->toBe([]);
});

it('refuses a key the overlay does not declare, or one written without c:', function () {
    expect(ControlConditions::problems(conditionControls(['c:glow = on'])))
        ->toBe(['Control "tint" depends on "c:glow", which is not a control of this overlay. Write it as c:<key>.'])
        ->and(ControlConditions::problems(conditionControls(['look != plain'])))
        ->toBe(['Control "tint" depends on "look", which is not a control of this overlay. Write it as c:<key>.']);
});

it('refuses reading a control with no knob, and reading itself', function () {
    expect(ControlConditions::problems(conditionControls(['c:hits > 3'])))
        ->toBe(['Control "tint" depends on "hits", a counter control; a condition may only read text, number, boolean and color controls.'])
        ->and(ControlConditions::problems(conditionControls(['c:tint != #fff'])))
        ->toBe(['Control "tint" depends on itself.']);
});

it('refuses enabled_if on a control with no knob to grey out', function () {
    expect(ControlConditions::problems(conditionControls(['c:look != plain'], ['type' => 'counter'])))
        ->toBe(['Control "tint" is a counter control; only text, number, boolean and color controls can declare enabled_if.']);
});

it('refuses a value that is not one of the choices, which is a typo, with = and with !=', function () {
    expect(ControlConditions::problems(conditionControls(['c:look != plian'])))
        ->toBe(['Control "tint" compares look to "plian", which is not one of its choices (bubble, plain).'])
        ->and(ControlConditions::problems(conditionControls(['c:look = plian'])))
        ->toBe(['Control "tint" compares look to "plian", which is not one of its choices (bubble, plain).']);
});

it('refuses ==, which the if grammar reads as = against "= value"', function () {
    expect(ControlConditions::problems(conditionControls(['c:look == plain'])))
        ->toBe(['Control "tint" writes "c:look == plain"; equality is a single =, as in [[[if:...]]].']);
});

it('refuses a malformed declaration', function () {
    expect(ControlConditions::problems(conditionControls([])))
        ->toBe(['Control "tint" has an enabled_if that is not a condition or a list of conditions.']);
});
