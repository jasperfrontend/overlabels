<?php

namespace Database\Seeders;

use App\Models\Recipe;
use App\Services\Recipes\RecipeManifestValidator;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Walks resources/recipes/<slug>/manifest.json and upserts each into the
 * recipes catalogue with is_first_party=true. Each (slug, version) gets
 * its own row - new versions never overwrite old ones.
 *
 * Idempotent: running the seeder twice with the same manifest produces
 * the same row, but a bumped version (or a manifest edit) produces a new
 * row alongside the old one.
 */
class FirstPartyRecipesSeeder extends Seeder
{
    public function __construct(
        private readonly RecipeManifestValidator $validator = new RecipeManifestValidator,
    ) {}

    public function run(): void
    {
        $dir = base_path('resources/recipes');
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*/manifest.json') as $path) {
            $this->seedOne($path);
        }
    }

    private function seedOne(string $path): void
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Manifest not readable at {$path}");
        }

        $result = $this->validator->validate($json);
        if (! $result['valid']) {
            throw new RuntimeException(
                "First-party manifest at {$path} failed validation: ".json_encode($result['errors'])
            );
        }

        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        Recipe::updateOrCreate(
            [
                'slug' => $manifest['slug'],
                'version' => $manifest['version'],
            ],
            [
                'name' => $manifest['name'],
                'description' => $manifest['description'],
                'author_name' => $manifest['author']['name'],
                'author_twitch_login' => $manifest['author']['twitch_login'] ?? null,
                'icon_url' => $manifest['icon_url'] ?? null,
                'changelog' => $manifest['changelog'] ?? null,
                'min_overlabels_version' => $manifest['min_overlabels_version'] ?? 1,
                'requires_integrations' => $manifest['requires_integrations'] ?? [],
                'max_instances_per_user' => $manifest['max_instances_per_user'] ?? null,
                'manifest' => $manifest,
                'is_first_party' => true,
            ]
        );
    }
}
