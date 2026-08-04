<?php

namespace Database\Factories;

use App\Models\OverlayReport;
use App\Models\OverlayTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class OverlayReportFactory extends Factory
{
    protected $model = OverlayReport::class;

    public function definition(): array
    {
        return [
            // Left null by default so a report can be built without dragging a
            // template and its owner into the test. Pass ->about($template)
            // when the relationship is what is under test.
            'overlay_template_id' => null,
            'template_slug' => $this->faker->slug(),
            'template_name' => $this->faker->words(3, true),
            'reporter_user_id' => null,
            'reporter_email' => $this->faker->safeEmail(),
            'reason' => $this->faker->sentence(12),
            'status' => OverlayReport::STATUS_OPEN,
            'ip_address' => $this->faker->ipv4(),
            'reviewed_at' => null,
            'reviewed_by_id' => null,
        ];
    }

    public function about(OverlayTemplate $template): static
    {
        return $this->state(fn () => [
            'overlay_template_id' => $template->id,
            'template_slug' => $template->slug,
            'template_name' => $template->name,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'status' => OverlayReport::STATUS_READ,
            'reviewed_at' => now(),
        ]);
    }
}
