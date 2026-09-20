<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Turn the font control on already-installed chat overlays into a webfont one.
 *
 * The recipe now declares `webfont=true` on `c:font`, which is what puts the
 * key in the render payload and makes the overlay fetch the family it is set
 * to. But a recipe is copied into an overlay at install time and there is no
 * update path, so an overlay installed before this has `config = NULL` on that
 * row - the key never reaches the payload, the overlay never loads a font, and
 * the designer writes a value that changes nothing anyone can see. The
 * companion migration moved the same overlays' heads off Google Fonts, which
 * without this leaves them with no font link at all.
 *
 * Matched on the description the recipe itself wrote, verbatim. That string
 * came from one place and no streamer has typed it, so it identifies exactly
 * the rows this recipe created and cannot catch a control someone made by hand
 * and happened to call `font`.
 *
 * Values are left alone. Anything already stored was written before the
 * allowlist existed, and a family that is not in the catalogue renders as the
 * fallback rather than breaking - which is the streamer's to fix in the picker,
 * not a migration's to overwrite.
 */
return new class extends Migration
{
    private const OLD_DESCRIPTION = 'The font family. One of Albert Sans, Inter, Space Grotesk, Fredoka, JetBrains Mono or Silkscreen; all six are loaded by the overlay.';

    private const NEW_DESCRIPTION = 'The font family, any of the ~1970 families Bunny Fonts serves. The overlay loads it itself, so the name has to be one Bunny knows, spelled its way.';

    public function up(): void
    {
        DB::table('overlay_controls')
            ->where('key', 'font')
            ->where('description', self::OLD_DESCRIPTION)
            ->select('id', 'config')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    // Merged, not replaced: the column is free-form and a row
                    // may have picked up other keys since it was installed.
                    $config = json_decode((string) $row->config, true);
                    $config = is_array($config) ? $config : [];
                    $config['webfont'] = true;

                    DB::table('overlay_controls')->where('id', $row->id)->update([
                        'config' => json_encode($config),
                        'description' => self::NEW_DESCRIPTION,
                    ]);
                }
            });
    }

    /**
     * Undoing this would leave overlays whose head no longer names a font and
     * whose control is no longer allowed to load one - type that renders as the
     * system fallback. There is nothing worth going back to.
     */
    public function down(): void {}
};
