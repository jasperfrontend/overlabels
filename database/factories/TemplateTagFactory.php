<?php

namespace Database\Factories;

use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class TemplateTagFactory extends Factory
{
    protected $model = TemplateTag::class;

    public function definition(): array
    {
        return [
            'original_tag_name' => $this->faker->name(),
            'is_editable' => $this->faker->boolean(),
            // Both are schema-constrained: version is varchar(10) and tag_type
            // has a CHECK for standard|custom, so a faker word fails at random.
            'version' => '1.0',
            'tag_type' => 'standard',
            'updated_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'is_active' => $this->faker->boolean(),
            'formatting_options' => $this->faker->words(),
            'sample_data' => $this->faker->words(),
            'description' => $this->faker->text(),
            'display_name' => $this->faker->name(),
            'data_type' => $this->faker->word(),
            'json_path' => $this->faker->word(),
            'display_tag' => $this->faker->word(),
            'tag_name' => $this->faker->name(),

            'category_id' => TemplateTagCategory::factory(),
        ];
    }
}
