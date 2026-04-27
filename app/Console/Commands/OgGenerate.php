<?php

namespace App\Console\Commands;

use App\Services\HelpReferenceService;
use App\Services\OgImageService;
use Illuminate\Console\Command;

class OgGenerate extends Command
{
    protected $signature = 'og:generate {--force : Re-render even if cached PNGs exist}';

    protected $description = 'Pre-render OG images for the help reference index and every entry.';

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

        return self::SUCCESS;
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
