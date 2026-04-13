<?php

namespace Database\Factories;

use App\Models\TemplateTagJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class TemplateTagJobFactory extends Factory
{
    protected $model = TemplateTagJob::class;

    public function definition(): array
    {
        return [
            'job_type' => $this->faker->word(),
            'status' => $this->faker->word(),
            'job_id' => $this->faker->word(),
            'progress' => $this->faker->word(),
            'result' => $this->faker->word(),
            'error_message' => $this->faker->word(),
            'started_at' => Carbon::now(),
            'completed_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'user_id' => User::factory(),
        ];
    }
}
