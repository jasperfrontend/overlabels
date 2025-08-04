<?php

namespace Database\Factories;

use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class OverlayTemplateFactory extends Factory
{
    protected $model = OverlayTemplate::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->slug(),
            'name' => $this->faker->name(),
            'description' => $this->faker->text(),
            'html' => $this->faker->word(),
            'css' => $this->faker->word(),
            'js' => $this->faker->word(),
            'is_public' => $this->faker->boolean(),
            'version' => $this->faker->randomNumber(),
            'template_tags' => $this->faker->words(),
            'metadata' => $this->faker->words(),
            'view_count' => $this->faker->randomNumber(),
            'fork_count' => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'owner_id' => User::factory(),
            'fork_of_id' => OverlayTemplate::factory(),
        ];
    }
}
