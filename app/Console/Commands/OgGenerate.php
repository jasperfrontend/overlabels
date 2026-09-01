<?php

namespace App\Console\Commands;

use App\Models\Update;
use App\Services\HelpReferenceService;
use App\Services\OgImageService;
use App\Support\HelpPage;
use Illuminate\Console\Command;

class OgGenerate extends Command
{
    protected $signature = 'og:generate {--force : Re-render even if cached PNGs exist}';

    protected $description = 'Pre-render OG images for the help reference and every published update post.';

    public function handle(HelpReferenceService $help, OgImageService $og): int
    {
        if ($this->option('force')) {
            $this->purgeCache();
        }

        $entries = $help->all();
        $total = count($entries);

        $indexUrl = $og->urlForIndex($total, 'https://overlabels.com/help/reference');
        $this->line("[index] {$indexUrl}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($entries as $entry) {
            $canonical = "https://overlabels.com/help/reference/{$entry['category']}/{$entry['slug']}";
            $og->urlFor($entry, $canonical);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Rendered OG images for {$total} entries + index.");

        $this->generatePages($og);
        $this->generateUpdates($og);

        return self::SUCCESS;
    }

    /**
     * Warm the card for every guide, tutorial and deep dive. Same reason as
     * the posts below: a scraper that arrives before the first render caches
     * the fallback image for good.
     */
    private function generatePages(OgImageService $og): void
    {
        $slugs = HelpPage::all();

        $bar = $this->output->createProgressBar(count($slugs));
        $bar->start();

        foreach ($slugs as $slug) {
            $page = HelpPage::render($slug);
            $og->urlForPage($page, $page['canonical']);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Rendered OG images for '.count($slugs).' help pages.');
    }

    /**
     * Warm the card for every published post.
     *
     * Worth doing on deploy rather than leaving to the first request: a link
     * scraper gives a page a second or two, and a cold render is a shell-out
     * to resvg. Miss that window and the scraper caches the fallback image
     * for the post, which is the exact failure this feature exists to end.
     *
     * Wrapped because a container can boot with the database unreachable, and
     * a warm-up must never be the thing that stops it.
     */
    private function generateUpdates(OgImageService $og): void
    {
        try {
            $updates = Update::published()->orderByDesc('published_at')->get();
        } catch (\Throwable $e) {
            $this->warn('Skipped update posts: '.$e->getMessage());

            return;
        }

        if ($updates->isEmpty()) {
            return;
        }

        $bar = $this->output->createProgressBar($updates->count());
        $bar->start();

        foreach ($updates as $update) {
            $og->urlForUpdate($update, "https://overlabels.com/updates/{$update->slug}");
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Rendered OG images for {$updates->count()} update posts.");
    }

    private function purgeCache(): void
    {
        $dir = public_path('og');
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir.'/*.png') ?: [] as $file) {
            @unlink($file);
        }
        $this->line('Purged existing PNGs in public/og/.');
    }
}
