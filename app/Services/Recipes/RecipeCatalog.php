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
    /**
     * The shelves of /products, in the order Show all stacks them. A listed
     * manifest carries at most one `category`, and the schema's enum is
     * exactly these keys (pinned by ProductCategoryTest). `label` is the
     * sidebar link and the shelf heading; `lead` is the line under it.
     */
    public const CATEGORIES = [
        'product' => [
            'label' => 'Products',
            'lead' => 'Small games your chat plays together, live, on your stream. No other overlay pack has these.',
        ],
        'alert' => [
            'label' => 'Alerts',
            'lead' => 'Connect Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee or Throne, and every tip, order or gift lands on your stream with a sound, a spoken line and a message in chat.',
        ],
    ];

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
     * The product a legacy URL slug belongs to now, or null when the slug is
     * current, unknown, or was never renamed.
     *
     * Scanned rather than mapped: `url_aliases` sits in the manifest beside
     * the slug that replaced it, so a rename is one file's business and there
     * is no central table to fall out of step with the catalogue. Only ever
     * reached on a miss, so the glob costs nothing on the path people take.
     */
    public function canonicalSlugFor(string $legacy): ?string
    {
        foreach ($this->all() as $slug => $manifest) {
            if (in_array($legacy, $manifest['url_aliases'] ?? [], true)) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        if (! preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $slug)) {
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

        // With the directory, so the overlay documents next to the manifest
        // are checked too: a choice that leaves one reading a control its
        // service never provisions fails here, not on a streamer's install.
        $result = $this->validator->validate($json, dirname($path));
        if (! $result['valid']) {
            throw new RuntimeException(
                "First-party manifest at {$path} failed validation: ".json_encode($result['errors'])
            );
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
