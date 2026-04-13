<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserEventsubSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class UserEventsubSubscriptionFactory extends Factory
{
    protected $model = UserEventsubSubscription::class;

    public function definition(): array
    {
        return [
            'twitch_subscription_id' => $this->faker->word(),
            'event_type' => $this->faker->word(),
            'version' => $this->faker->word(),
            'status' => $this->faker->word(),
            'condition' => $this->faker->word(),
            'callback_url' => $this->faker->url(),
            'twitch_created_at' => Carbon::now(),
            'last_verified_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'user_id' => User::factory(),
        ];
    }
}
