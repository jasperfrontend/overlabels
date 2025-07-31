<?php

namespace Database\Factories;

use App\Models\OverlayHash;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class OverlayHashFactory extends Factory
{
    protected $model = OverlayHash::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->slug(),
            'updated_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'metadata' => $this->faker->words(),
            'allowed_ips' => $this->faker->words(),
            'expires_at' => Carbon::now(),
            'access_count' => $this->faker->randomNumber(),
            'last_accessed_at' => Carbon::now(),
            'is_active' => $this->faker->boolean(),
            'description' => $this->faker->text(),
            'overlay_name' => $this->faker->name(),
            'hash_key' => $this->faker->word(),

            'user_id' => User::factory(),
        ];
    }
}
