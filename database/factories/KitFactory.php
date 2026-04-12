<?php

namespace Database\Factories;

use App\Models\Kit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kit>
 */
class KitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'thumbnail' => null,
            'is_public' => $this->faker->boolean(70),
            'forked_from_id' => null,
            'fork_count' => 0,
        ];
    }
}
