<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class UserTemplateFactory extends Factory
{
    protected $model = UserTemplate::class;

    public function definition(): array
    {
        return [
            'updated_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'status' => $this->faker->word(),
            'used_tags' => $this->faker->words(),
            'css_content' => $this->faker->word(),
            'html_content' => $this->faker->word(),
            'description' => $this->faker->text(),
            'name' => $this->faker->name(),

            'user_id' => User::factory(),
        ];
    }
}
