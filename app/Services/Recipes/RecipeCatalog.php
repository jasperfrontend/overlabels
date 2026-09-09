<?php

namespace App\Services\Recipes;

use App\Models\Recipe;
use RuntimeException;

/**
 * The recipe catalogue is the repo: resources/recipes/<slug>/manifest.json.
 * The `recipes` table row exists so instances have something to point at,
 * and it is written from the file on demand - by the seeder in dev, and by
 * the product install on prod, which runs no seeder. Same upsert either way.
 */
class RecipeCatalog
{
    public function __construct(
        private readonly RecipeManifestValidator $validator = new RecipeManifestValidator,
    ) {}

    /**
     * Every manifest on disk, validated, keyed by slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $manifests = [];

        foreach (glob(base_path('resources/recipes').'/*/manifest.json') ?: [] as $path) {
            $manifest = $this->read($path);
            $manifests[$manifest['slug']] = $manifest;
        }

        ksort($manifests);

        return $manifests;
    }

    /**
     * The manifests flagged `listed`, which is what /products shows.
     *
     * @return array<string, array<string, mixed>>
     */
    public function listed(): array
    {
        return array_filter($this->all(), fn (array $manifest) => (bool) ($manifest['listed'] ?? false));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        if (! preg_match('/^[a-z][a-z0-9_]{0,49}$/', $slug)) {
            return null;
        }

        $path = RecipeInstaller::directoryFor($slug).DIRECTORY_SEPARATOR.'manifest.json';

        return is_file($path) ? $this->read($path) : null;
    }

    /**
     * The catalogue row for a manifest, written or refreshed from the file.
     * Each (slug, version) is its own row; a bumped version never overwrites
     * the old one, so an instance installed from v1 keeps describing v1.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function sync(array $manifest): Recipe
    {
        return Recipe::updateOrCreate(
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

    /**
     * @return array<string, mixed>
     */
    private function read(string $path): array
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

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
