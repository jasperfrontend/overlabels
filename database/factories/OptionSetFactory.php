<?php

namespace Database\Factories;

use App\Models\OptionSet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionSet>
 */
class OptionSetFactory extends Factory
{
    protected $model = OptionSet::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipe_instance_id' => null,
            'slug' => $this->faker->unique()->lexify('list_???'),
            'label' => null,
            'items' => [],
            'item_added_at' => [],
            'min_items' => 0,
            'max_items' => null,
            'user_editable' => true,
            'disabled_at' => null,
            'entry_ttl_seconds' => null,
            'expires_at' => null,
        ];
    }
}
