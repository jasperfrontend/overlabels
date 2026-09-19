<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Twitch Chat overlay's four color controls were labelled in British
 * English. The recipe is fixed, but a label is copied into `overlay_controls`
 * at install time, so every account that already installed the product keeps
 * the old spelling on its designer and controls tab until the row is rewritten.
 *
 * Matched on key AND the exact old label, so a control someone renamed
 * themselves is left alone. Table and column names are literals, and the pairs
 * are frozen here rather than read from the recipe: this migration is dated,
 * and the recipe is not.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> key => [old label, new label] */
    private const LABELS = [
        'twitch_colors' => ['Names in their Twitch colours', 'Names in their Twitch colors'],
        'name_color' => ['Name colour', 'Name color'],
        'text_color' => ['Text colour', 'Text color'],
        'background_color' => ['Background colour', 'Background color'],
    ];

    public function up(): void
    {
        foreach (self::LABELS as $key => [$old, $new]) {
            DB::table('overlay_controls')
                ->where('key', $key)
                ->where('label', $old)
                ->update(['label' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::LABELS as $key => [$old, $new]) {
            DB::table('overlay_controls')
                ->where('key', $key)
                ->where('label', $new)
                ->update(['label' => $old]);
        }
    }
};
