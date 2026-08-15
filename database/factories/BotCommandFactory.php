<?php

namespace Database\Factories;

use App\Models\BotCommand;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BotCommand>
 */
class BotCommandFactory extends Factory
{
    protected $model = BotCommand::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'command' => 'distance',
            'permission_level' => 'everyone',
            'cooldown_seconds' => 0,
            'reply' => 'Hello, [[[bot:from_user]]]!',
            'enabled' => true,
            'hidden' => false,
            'last_fired_at' => null,
        ];
    }
}
