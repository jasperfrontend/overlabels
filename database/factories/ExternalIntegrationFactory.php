<?php

namespace Database\Factories;

use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExternalIntegrationFactory extends Factory
{
    protected $model = ExternalIntegration::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service' => 'kofi',
            'webhook_token' => (string) Str::uuid(),
            'credentials' => null,
            'settings' => ['enabled_events' => ['donation', 'subscription', 'shop_order']],
            'enabled' => true,
            'last_received_at' => null,
        ];
    }
}
