<?php

namespace Database\Factories;

use App\Models\BotExpression;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BotExpression>
 */
class BotExpressionFactory extends Factory
{
    protected $model = BotExpression::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'command' => 'distance',
            'permission_level' => 'everyone',
            'cooldown_seconds' => 0,
            'expression' => 'Hello, [[[bot:from_user]]]!',
            'enabled' => true,
            'hidden_from_commands' => false,
            'last_fired_at' => null,
        ];
    }
}
