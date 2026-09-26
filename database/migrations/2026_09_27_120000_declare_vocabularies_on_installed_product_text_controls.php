<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Put the vocabulary on the text controls of already-installed product overlays.
 *
 * The Twitch Chat and Chat Emote Bubbles recipes now declare `config.choices`
 * on their closed-vocabulary text controls (`layout`, `background`; `look`,
 * `direction`, `spawn`), which is what turns the free-text box on the Controls
 * tab into a select and lets the designer read the values off the row. A
 * recipe is copied into an overlay at install time and there is no update
 * path, so an overlay installed before this has no `choices` on those rows.
 * Same situation, same shape as the webfont backfill of 2026-09-20.
 *
 * Matched through the install record rather than by description: the rows
 * are the ones a recipe_instance of the product maps to under its overlay
 * ref, so a control a streamer made by hand and happened to call `look` is
 * never touched, and a description reworded between recipe versions cannot
 * make the match miss. A row that already carries a vocabulary is left as it
 * is; only the key is added, the rest of the config and the value are kept.
 *
 * The lists are frozen here on purpose. A migration is dated but a reference
 * to a live class or file is not, so reading the recipe on disk would seed
 * whatever the document says on the day this happens to run.
 */
return new class extends Migration
{
    private const VOCABULARIES = [
        'twitch-chat-overlay' => [
            'overlay' => 'chat',
            'controls' => [
                'layout' => [
                    ['value' => 'bottom', 'label' => 'Bottom', 'hint' => 'Stacks upward, newest at the bottom.'],
                    ['value' => 'top', 'label' => 'Top', 'hint' => 'Stacks downward, newest at the top.'],
                    ['value' => 'ticker', 'label' => 'Ticker', 'hint' => 'One line, newest at the right.'],
                ],
                'background' => [
                    ['value' => 'none', 'label' => 'None', 'hint' => 'Text straight over the game, with a shadow.'],
                    ['value' => 'solid', 'label' => 'Solid', 'hint' => 'The background color, flat.'],
                    ['value' => 'glass', 'label' => 'Glass', 'hint' => 'Translucent and blurred.'],
                ],
            ],
        ],
        'chat-emote-bubbles' => [
            'overlay' => 'bubbles',
            'controls' => [
                'look' => [
                    ['value' => 'bubble', 'label' => 'Bubble', 'hint' => 'A soap bubble.'],
                    ['value' => 'snow', 'label' => 'Snowflake', 'hint' => 'A snowflake behind the emote.'],
                    ['value' => 'heart', 'label' => 'Heart', 'hint' => 'A heart-shaped bubble.'],
                ],
                'direction' => [
                    ['value' => 'up', 'label' => 'Up', 'hint' => 'Floats toward the top of the screen.'],
                    ['value' => 'down', 'label' => 'Down', 'hint' => 'Sinks toward the bottom. The spawn edge follows.'],
                ],
                'spawn' => [
                    ['value' => 'outside', 'label' => 'Outside', 'hint' => 'Floats in from just past the edge it starts from.'],
                    ['value' => 'edge', 'label' => 'Edge', 'hint' => 'Appears on that edge.'],
                    ['value' => 'random', 'label' => 'Random', 'hint' => 'Appears anywhere on screen.'],
                    ['value' => 'cannon', 'label' => 'Cannon', 'hint' => 'Appears at the point set by Spawn X and Spawn Y.'],
                ],
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::VOCABULARIES as $slug => $product) {
            // Every version of the recipe: a slug has one row per version.
            $recipeIds = DB::table('recipes')->where('slug', $slug)->pluck('id');

            if ($recipeIds->isEmpty()) {
                continue;
            }

            DB::table('recipe_instances')
                ->whereIn('recipe_id', $recipeIds)
                ->select('id', 'primitive_map')
                ->chunkById(100, function ($instances) use ($product) {
                    foreach ($instances as $instance) {
                        $map = is_string($instance->primitive_map)
                            ? json_decode($instance->primitive_map, true)
                            : $instance->primitive_map;
                        $templateId = is_array($map) ? ($map['overlays'][$product['overlay']] ?? null) : null;

                        if (! $templateId) {
                            continue;
                        }

                        $rows = DB::table('overlay_controls')
                            ->where('overlay_template_id', $templateId)
                            ->where('type', 'text')
                            ->whereIn('key', array_keys($product['controls']))
                            ->get(['id', 'key', 'config']);

                        foreach ($rows as $row) {
                            // Merged, not replaced: the column is free-form and
                            // a row may carry other keys since it was installed.
                            $config = json_decode((string) $row->config, true);
                            $config = is_array($config) ? $config : [];

                            if (! empty($config['choices'])) {
                                continue;
                            }

                            $config['choices'] = $product['controls'][$row->key];

                            DB::table('overlay_controls')->where('id', $row->id)->update([
                                'config' => json_encode($config),
                            ]);
                        }
                    }
                });
        }
    }

    /**
     * A vocabulary on a row states something true about the overlay's CSS.
     * Taking it away again would only turn the select back into a text box.
     */
    public function down(): void {}
};
