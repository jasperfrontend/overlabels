<?php

namespace App\Console\Commands;

use App\Services\HelpReferenceService;
use App\Support\HelpCorpus;
use Illuminate\Console\Command;

class BuildHelpReferenceIndex extends Command
{
    protected $signature = 'help:build-index';

    protected $description = 'Emit the help search indexes to public/ for the client-side fuzzy search.';

    /**
     * Two indexes, on purpose.
     *
     * `help-index.json` is the one the site searches: every tutorial, guide and
     * reference entry, so one search box answers "where is this documented"
     * regardless of which pile the answer lives in.
     *
     * `help-reference-index.json` is the reference alone, and it stays exactly
     * as it was. It is a documented public contract - linked from the reference
     * page as "BYOF" and explained at
     * /help/help-reference-index-json - so anything
     * built against its shape keeps working.
     */
    public function handle(HelpReferenceService $service): int
    {
        $service->flush();

        $reference = array_map(
            fn (array $e) => [
                'category' => $e['category'],
                'categoryLabel' => $e['categoryLabel'],
                'slug' => $e['slug'],
                'title' => $e['title'],
                'body' => $e['body'],
            ],
            $service->all(),
        );

        $unified = array_map(
            fn (array $d) => [
                'kind' => $d['kind'],
                'kindLabel' => $d['kindLabel'],
                'slug' => $d['slug'],
                'title' => $d['title'],
                'lead' => $d['lead'],
                'url' => $d['url'],
                'body' => $d['body'],
                // Declared in the page's `keywords:` frontmatter. Searched by a
                // separate exact/prefix pass rather than a sixth Fuse key,
                // because adding a key renormalises every score in the corpus.
                // Deliberately absent from help-reference-index.json below:
                // that shape is a documented public contract.
                'keywords' => $d['keywords'],
                // The folder a document lives in is a thing people search for:
                // "foreach" is expected to return the nine foreach loop fields,
                // not just whichever ones happen to say the word. Reference
                // entries carry the category they are filed under; a page's
                // pile is already its kindLabel, so those stay null.
                'category' => $d['category'],
                'categoryLabel' => $d['categoryLabel'],
            ],
            HelpCorpus::all(),
        );

        foreach ([
            'help-index.json' => $unified,
            'help-reference-index.json' => $reference,
        ] as $file => $entries) {
            $json = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                $this->error("Failed to encode {$file}.");

                return self::FAILURE;
            }

            $path = public_path($file);
            file_put_contents($path, $json);

            $this->info(sprintf(
                'Wrote %d entries to %s (%s)',
                count($entries),
                $path,
                $this->humanBytes(strlen($json)),
            ));
        }

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
