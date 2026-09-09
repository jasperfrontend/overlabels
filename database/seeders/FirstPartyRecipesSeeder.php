<?php

namespace Database\Seeders;

use App\Services\Recipes\RecipeCatalog;
use Illuminate\Database\Seeder;

/**
 * Walks resources/recipes/<slug>/manifest.json and upserts each into the
 * recipes catalogue with is_first_party=true. Each (slug, version) gets
 * its own row - new versions never overwrite old ones.
 *
 * Idempotent: running the seeder twice with the same manifest produces
 * the same row, but a bumped version (or a manifest edit) produces a new
 * row alongside the old one. The read and the upsert live in
 * RecipeCatalog, which the product install uses too - prod never runs
 * this seeder.
 */
class FirstPartyRecipesSeeder extends Seeder
{
    public function __construct(
        private readonly RecipeCatalog $catalog = new RecipeCatalog,
    ) {}

    public function run(): void
    {
        foreach ($this->catalog->all() as $manifest) {
            $this->catalog->sync($manifest);
        }
    }
}
