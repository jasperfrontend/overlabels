<?php

namespace App\Console\Commands;

use App\Services\HelpReferenceService;
use Illuminate\Console\Command;

class BuildHelpReferenceIndex extends Command
{
    protected $signature = 'help:build-index';

    protected $description = 'Emit public/help-reference-index.json for the client-side fuzzy search.';

    public function handle(HelpReferenceService $service): int
    {
        $service->flush();

        $entries = array_map(
            fn (array $e) => [
                'category' => $e['category'],
                'categoryLabel' => $e['categoryLabel'],
                'slug' => $e['slug'],
                'title' => $e['title'],
                'body' => $e['body'],
            ],
            $service->all(),
        );

        $path = public_path('help-reference-index.json');
        $json = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error('Failed to encode JSON.');

            return self::FAILURE;
        }

        file_put_contents($path, $json);
        $this->info(sprintf(
            'Wrote %d entries to %s (%s)',
            count($entries),
            $path,
            $this->humanBytes(strlen($json)),
        ));

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
