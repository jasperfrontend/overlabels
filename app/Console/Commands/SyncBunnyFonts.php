<?php

namespace App\Console\Commands;

use App\Support\BunnyFonts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncBunnyFonts extends Command
{
    protected $signature = 'fonts:sync-bunny';

    protected $description = 'Refresh public/fonts-bunny.json from the Bunny Fonts catalogue.';

    /**
     * Fetch, trim, check the two rules, write.
     *
     * The upstream list is ~490 KB of subsets, styles and variable-axis detail
     * the picker never reads. Trimmed to name and category it is a tenth of
     * that, which matters because the browser fetches it.
     *
     * The two rule checks are the point of the command being a command rather
     * than a curl. BunnyFonts documents why the overlay may derive a slug from
     * a family name and why one fixed weight pair is safe; if a future
     * catalogue breaks either, this refuses to write and the existing file
     * stands, instead of shipping an overlay that silently renders a fallback.
     */
    public function handle(): int
    {
        $response = Http::timeout(30)->get(BunnyFonts::LIST_URL);

        if (! $response->successful()) {
            $this->error('Bunny Fonts returned HTTP '.$response->status().'.');

            return self::FAILURE;
        }

        $list = $response->json();

        if (! is_array($list) || $list === []) {
            $this->error('Bunny Fonts returned no families.');

            return self::FAILURE;
        }

        $catalogue = [];
        $mismatched = [];
        $weightless = [];

        foreach ($list as $slug => $entry) {
            $name = is_array($entry) ? ($entry['familyName'] ?? null) : null;

            if (! is_string($name) || $name === '' || ! is_string($slug)) {
                continue;
            }

            // Rule 1: the overlay derives the slug from the name by itself.
            if (BunnyFonts::slug($name) !== $slug) {
                $mismatched[$name] = $slug;

                continue;
            }

            // Rule 2: a family the fixed weight pair cannot serve is dropped
            // rather than offered, since its link would come back empty.
            $weights = array_map('intval', (array) ($entry['weights'] ?? []));

            if (! in_array(400, $weights, true) && ! in_array(700, $weights, true)) {
                $weightless[] = $slug;

                continue;
            }

            $catalogue[$slug] = [
                'name' => $name,
                'category' => is_string($entry['category'] ?? null) ? $entry['category'] : 'other',
            ];
        }

        if ($catalogue === []) {
            $this->error('Nothing survived the checks; refusing to write an empty catalogue.');

            return self::FAILURE;
        }

        // A handful of drops is the known shape (two weightless families as of
        // September 2026). Wholesale mismatch means the slug rule has changed
        // upstream, which the overlay cannot see - so it is fatal, not a note.
        if (count($mismatched) > 0) {
            $this->error('The slug rule no longer holds for '.count($mismatched).' families, e.g. '
                .implode(', ', array_slice(array_keys($mismatched), 0, 3)).'.');
            $this->line('OverlayRenderer derives a font URL from the family name. Fix both sides before syncing.');

            return self::FAILURE;
        }

        ksort($catalogue);

        file_put_contents(
            public_path(BunnyFonts::CATALOGUE_PATH),
            json_encode($catalogue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
        );

        BunnyFonts::flush();

        $this->info(count($catalogue).' families written to public/'.BunnyFonts::CATALOGUE_PATH.'.');

        if ($weightless !== []) {
            $this->line('Dropped for having neither weight 400 nor 700: '.implode(', ', $weightless).'.');
        }

        return self::SUCCESS;
    }
}
