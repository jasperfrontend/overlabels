<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Offer the Plain look on already-installed Chat Emote Bubbles overlays.
 *
 * The recipe's `look` control gained a fourth choice, `plain` (no bubble, just
 * the emote), the designer a Plain preset that writes it, and the CSS one rule
 * that lets the bare emote fill the box a bubble would have. A recipe is copied
 * into an overlay at install time with no update path, so an existing install
 * has neither: its `look` select has no entry for the value the Plain preset
 * writes, and its emote would sit at bubble-interior size.
 *
 * Matched through the install record, like the 2026-09-27 vocabulary backfill,
 * so a hand-made control called `look` is never touched. Only a row whose
 * choices are exactly the three the recipe shipped gets the fourth appended;
 * a row carrying any other list is left as it is. The CSS rule goes in after
 * the heart's emote rule, and only when that line is there exactly once and
 * no `.look-plain` rule is: an install whose CSS was rewritten there still
 * gets the choice, and Plain still works on it, with a smaller emote.
 * Everything is frozen here.
 */
return new class extends Migration
{
    private const SHIPPED = ['bubble', 'snow', 'heart'];

    private const PLAIN = ['value' => 'plain', 'label' => 'Plain', 'hint' => 'No bubble, just the emote.'];

    private const CSS_ANCHOR = '.look-heart .skin img { height: calc(var(--size) * 0.44); max-width: calc(var(--size) * 0.56); transform: translate(-50%, -56%); }';

    private const CSS_RULE = "\n\n/* Plain: no shape at all, so the emote fills the box the bubble would have. */\n.look-plain .skin img { height: calc(var(--size) * 0.8); max-width: var(--size); }";

    public function up(): void
    {
        $recipeIds = DB::table('recipes')->where('slug', 'chat-emote-bubbles')->pluck('id');

        if ($recipeIds->isEmpty()) {
            return;
        }

        DB::table('recipe_instances')
            ->whereIn('recipe_id', $recipeIds)
            ->select('id', 'primitive_map')
            ->chunkById(100, function ($instances) {
                foreach ($instances as $instance) {
                    $map = is_string($instance->primitive_map)
                        ? json_decode($instance->primitive_map, true)
                        : $instance->primitive_map;
                    $templateId = is_array($map) ? ($map['overlays']['bubbles'] ?? null) : null;

                    if (! $templateId) {
                        continue;
                    }

                    $css = DB::table('overlay_templates')->where('id', $templateId)->value('css');

                    if (is_string($css) && substr_count($css, self::CSS_ANCHOR) === 1 && ! str_contains($css, '.look-plain')) {
                        DB::table('overlay_templates')->where('id', $templateId)->update([
                            'css' => str_replace(self::CSS_ANCHOR, self::CSS_ANCHOR.self::CSS_RULE, $css),
                        ]);
                    }

                    $row = DB::table('overlay_controls')
                        ->where('overlay_template_id', $templateId)
                        ->where('type', 'text')
                        ->where('key', 'look')
                        ->first(['id', 'config']);

                    if (! $row) {
                        continue;
                    }

                    $config = json_decode((string) $row->config, true);
                    $choices = is_array($config) ? ($config['choices'] ?? null) : null;

                    if (! is_array($choices) || array_column($choices, 'value') !== self::SHIPPED) {
                        continue;
                    }

                    $config['choices'][] = self::PLAIN;

                    DB::table('overlay_controls')->where('id', $row->id)->update([
                        'config' => json_encode($config),
                    ]);
                }
            });
    }

    /**
     * Taking the choice away again would only leave a row set to `plain`
     * showing a select with no entry for its own value.
     */
    public function down(): void {}
};
