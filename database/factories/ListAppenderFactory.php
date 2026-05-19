<?php

namespace Database\Factories;

use App\Models\ListAppender;
use App\Models\OptionSet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListAppender>
 */
class ListAppenderFactory extends Factory
{
    protected $model = ListAppender::class;

    public function definition(): array
    {
        $userId = User::factory();

        return [
            'user_id' => $userId,
            'target_list_id' => fn (array $attributes) => OptionSet::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
            'command' => $this->faker->unique()->lexify('append_???'),
            'permission_level' => 'everyone',
            'cooldown_seconds' => 0,
            'value_template' => '[[[bot:from_user]]]',
            'args_empty_reply' => null,
            'success_reply' => null,
            'dedup_policy' => ListAppender::DEDUP_PER_CHATTER,
            'max_size' => null,
            'enabled' => true,
            'last_fired_at' => null,
        ];
    }
}
