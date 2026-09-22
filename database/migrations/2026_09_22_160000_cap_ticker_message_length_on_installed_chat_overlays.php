<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cap a ticker message on already-installed chat overlays.
 *
 * The recipe's ticker layout now clips a message body at about 200 characters
 * with an ellipsis: Twitch allows 500, a ticker is one line, and a wall of text
 * walked off the right edge and under every message behind it. A recipe is
 * copied into an overlay at install time and there is no update path, so an
 * overlay installed before this keeps the old rule until something rewrites it.
 *
 * Matched on the exact old rule, verbatim, the same way the font-link
 * migration matched the head it rewrote. The line came from one place and
 * nothing else writes it, so a streamer who edited their CSS and dropped or
 * changed that line is left alone, and one who kept it gets the fix.
 */
return new class extends Migration
{
    private const OLD_RULE = '.layout-ticker .msg { white-space: nowrap; overflow-wrap: normal; }';

    private const NEW_RULES = <<<'CSS'
.layout-ticker .msg { display: inline-flex; align-items: baseline; column-gap: 0.25em; white-space: nowrap; overflow-wrap: normal; }
/* Twitch allows 500 characters and a ticker is one line, so a wall of text would walk
   off the edge and under every message behind it. The body gets about 200 characters
   of the current font and an ellipsis, and no more room than that on purpose. The row
   is flex so the body can be clipped; the gap stands in for the space between spans
   that inline flow gave every skin, which Bubbles - no colon, no margin - relied on. */
.layout-ticker .body { min-width: 0; max-width: 200ch; overflow: hidden; text-overflow: ellipsis; }
CSS;

    public function up(): void
    {
        $this->rewrite(self::OLD_RULE, self::NEW_RULES);
    }

    public function down(): void
    {
        $this->rewrite(self::NEW_RULES, self::OLD_RULE);
    }

    private function rewrite(string $from, string $to): void
    {
        DB::table('overlay_templates')
            ->where('css', 'like', '%'.$from.'%')
            ->select('id', 'css')
            ->chunkById(100, function ($rows) use ($from, $to) {
                foreach ($rows as $row) {
                    DB::table('overlay_templates')->where('id', $row->id)->update([
                        'css' => str_replace($from, $to, (string) $row->css),
                    ]);
                }
            });
    }
};
