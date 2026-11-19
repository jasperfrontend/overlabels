<?php

use App\Support\AngleKnob;

/**
 * `config.angle_colors` as a recipe is held to it: a number control naming
 * two color controls of the same overlay.
 */
function angleControls(mixed $colors, string $type = 'number'): array
{
    return [
        ['key' => 'tint', 'type' => 'color', 'config' => null],
        ['key' => 'tint_2', 'type' => 'color', 'config' => null],
        ['key' => 'size', 'type' => 'number', 'config' => null],
        ['key' => 'angle', 'type' => $type, 'config' => ['min' => 0, 'max' => 360, 'angle_colors' => $colors]],
    ];
}

it('accepts a number control naming two color controls of the overlay', function () {
    expect(AngleKnob::problems(angleControls(['tint', 'tint_2'])))->toBe([]);
});

it('ignores a control that declares nothing', function () {
    expect(AngleKnob::problems([['key' => 'angle', 'type' => 'number', 'config' => ['min' => 0]]]))->toBe([]);
});

it('refuses angle_colors on anything but a number', function () {
    expect(AngleKnob::problems(angleControls(['tint', 'tint_2'], 'text')))
        ->toBe(['Control "angle" is a text control; only a number control can declare angle_colors.']);
});

it('refuses anything but a list of two keys', function () {
    $message = ['Control "angle" has an angle_colors that is not a list of two control keys.'];

    expect(AngleKnob::problems(angleControls(['tint'])))->toBe($message)
        ->and(AngleKnob::problems(angleControls(['tint', 'tint_2', 'size'])))->toBe($message)
        ->and(AngleKnob::problems(angleControls('tint,tint_2')))->toBe($message)
        ->and(AngleKnob::problems(angleControls(['a' => 'tint', 'b' => 'tint_2'])))->toBe($message);
});

it('refuses a key the overlay does not declare, and a key that is not a color', function () {
    expect(AngleKnob::problems(angleControls(['tint', 'glow'])))
        ->toBe(['Control "angle" paints "glow", which is not a control of this overlay.'])
        ->and(AngleKnob::problems(angleControls(['tint', 'size'])))
        ->toBe(['Control "angle" paints "size", a number control; angle_colors names color controls.']);
});
