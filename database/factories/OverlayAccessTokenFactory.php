<?php

namespace Database\Factories;

use App\Models\OverlayAccessToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OverlayAccessTokenFactory extends Factory
{
    protected $model = OverlayAccessToken::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'token_hash' => Str::random(10),
            'token_prefix' => Str::random(10),
            'is_active' => $this->faker->boolean(),
            'expires_at' => Carbon::now(),
            'access_count' => $this->faker->randomNumber(),
            'last_used_at' => Carbon::now(),
            'allowed_ips' => $this->faker->words(),
            'metadata' => $this->faker->words(),
            'abilities' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'user_id' => User::factory(),
        ];
    }
}
