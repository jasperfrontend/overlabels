<?php

namespace App\Support;

/**
 * `config.angle_colors`: a number control that turns a two-color gradient.
 *
 * The control names the two color controls of the same overlay its angle
 * turns, first color first (`["bubble_color", "bubble_color_2"]`). The shared
 * knob then draws a circle painted with their live values and a line to drag
 * instead of a slider. The angle names where the first color sits, clockwise
 * from twelve o'clock, which is the overlay's `calc(180deg + angle)`.
 *
 * Like `enabled_if`, it only changes how the knob is drawn: nothing reads it
 * on stream. The frontend twin is `resources/js/utils/angleKnob.ts`, which
 * falls back to the plain slider on anything malformed; this is the rule a
 * recipe is held to so a product never ships one.
 */
class AngleKnob
{
    /**
     * Every problem with the `angle_colors` declarations of one overlay's
     * controls, one sentence each.
     *
     * @param  list<array{key: string, type: string, config?: array<string, mixed>|null}>  $controls
     * @return list<string>
     */
    public static function problems(array $controls): array
    {
        $declared = [];
        foreach ($controls as $control) {
            $declared[$control['key']] = $control;
        }

        $problems = [];

        foreach ($declared as $key => $control) {
            $config = $control['config'] ?? null;
            if (! is_array($config) || ! array_key_exists('angle_colors', $config)) {
                continue;
            }

            if ($control['type'] !== 'number') {
                $problems[] = "Control \"{$key}\" is a {$control['type']} control; only a number control can declare angle_colors.";

                continue;
            }

            $colors = $config['angle_colors'];
            if (! is_array($colors) || ! array_is_list($colors) || count($colors) !== 2 || ! is_string($colors[0]) || ! is_string($colors[1])) {
                $problems[] = "Control \"{$key}\" has an angle_colors that is not a list of two control keys.";

                continue;
            }

            foreach ($colors as $color) {
                if (! isset($declared[$color])) {
                    $problems[] = "Control \"{$key}\" paints \"{$color}\", which is not a control of this overlay.";
                } elseif ($declared[$color]['type'] !== 'color') {
                    $problems[] = "Control \"{$key}\" paints \"{$color}\", a {$declared[$color]['type']} control; angle_colors names color controls.";
                }
            }
        }

        return $problems;
    }
}
