<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Point already-installed overlays at Bunny Fonts instead of Google.
 *
 * The three first-party recipes now ship Bunny links, but a recipe is copied
 * into an overlay at install time and RecipeInstaller has no update path, so an
 * overlay installed before this keeps whatever head it was born with. Every one
 * of those still asks fonts.googleapis.com for its type, which is the request
 * this change exists to stop making.
 *
 * Two edits, both exact-string, both host-level:
 *
 *   - The chat overlay's six-family link goes entirely. Its font is a control
 *     now (`webfont=true`), so the overlay loads the family it is actually set
 *     to and the fixed list is what used to cap it at six.
 *   - Everything else is a host swap. Bunny serves the identical css2 query -
 *     same families, same weights, same `display=swap` - so the URL is correct
 *     the moment the host changes, for the two /engine overlays and for any
 *     hand-written one that copied a Google link out of a tutorial.
 *
 * Literal table name and literal link strings on purpose: a migration is dated
 * but a model reference is not, and a constant read from today's code would
 * make this do something different next year than it did when it ran.
 */
return new class extends Migration
{
    private const CHAT_LINK = '<link href="https://fonts.googleapis.com/css2?family=Albert+Sans:wght@400;700&family=Inter:wght@400;700&family=Space+Grotesk:wght@400;700&family=Fredoka:wght@400;700&family=JetBrains+Mono:wght@400;700&family=Silkscreen:wght@400;700&display=swap" rel="stylesheet">';

    private const GOOGLE_PRECONNECT = '<link rel="preconnect" href="https://fonts.googleapis.com">';

    private const GSTATIC_PRECONNECT = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';

    private const BUNNY_PRECONNECT = '<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>';

    public function up(): void
    {
        // Grouped, and chunkById rather than chunk: a rewritten row stops
        // matching the filter, which with offset paging would shift the next
        // page and skip overlays. Keyset paging walks past what it has done.
        DB::table('overlay_templates')
            ->where(function ($q) {
                $q->where('head', 'like', '%fonts.googleapis.com%')
                    ->orWhere('head', 'like', '%fonts.gstatic.com%');
            })
            ->select('id', 'head')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $head = (string) $row->head;

                    // The chat overlay's fixed list, dropped with the newline
                    // it sat on so the head does not gain a blank line.
                    $head = str_replace([self::CHAT_LINK."\n", self::CHAT_LINK], '', $head);

                    // The preconnect pair collapses to one: Bunny serves the
                    // CSS and the font files from the same host.
                    $head = str_replace(
                        [
                            self::GOOGLE_PRECONNECT."\n".self::GSTATIC_PRECONNECT,
                            self::GOOGLE_PRECONNECT."\r\n".self::GSTATIC_PRECONNECT,
                            self::GOOGLE_PRECONNECT,
                            self::GSTATIC_PRECONNECT,
                        ],
                        self::BUNNY_PRECONNECT,
                        $head,
                    );

                    $head = str_replace(
                        ['https://fonts.googleapis.com/css2?', 'https://fonts.googleapis.com/css?'],
                        ['https://fonts.bunny.net/css2?', 'https://fonts.bunny.net/css?'],
                        $head,
                    );

                    if ($head !== (string) $row->head) {
                        DB::table('overlay_templates')->where('id', $row->id)->update(['head' => $head]);
                    }
                }
            });
    }

    /**
     * Sending overlays back to Google is not something to automate. The swap is
     * lossless in the direction that matters (Bunny answers the same query), so
     * a down() here would exist only to undo a privacy improvement.
     */
    public function down(): void {}
};
