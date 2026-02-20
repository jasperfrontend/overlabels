<?php

namespace Database\Factories;

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OverlayControlFactory extends Factory
{
    protected $model = OverlayControl::class;

    public function definition(): array
    {
        return [
            'overlay_template_id' => OverlayTemplate::factory(),
            'user_id' => User::factory(),
            'key' => $this->faker->unique()->lexify('control_???'),
            'label' => $this->faker->words(2, true),
            'type' => 'text',
            'value' => $this->faker->words(3, true),
            'config' => null,
            'sort_order' => 0,
        ];
    }

    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'value' => 'Hello World',
            'config' => null,
        ]);
    }

    public function counter(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'counter',
            'value' => '0',
            'config' => [
                'min' => 0,
                'max' => null,
                'step' => 1,
                'reset_value' => 0,
            ],
        ]);
    }

    public function timer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'timer',
            'value' => null,
            'config' => [
                'mode' => 'countup',
                'base_seconds' => 0,
                'offset_seconds' => 0,
                'running' => false,
                'started_at' => null,
            ],
        ]);
    }
}
