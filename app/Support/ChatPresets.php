<?php

namespace App\Support;

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;

/**
 * The ten looks of the Twitch Chat product.
 *
 * A preset is nothing but a bundle of values for the overlay's thirteen
 * look controls: a `skin` (a block of rules in the overlay's own CSS, so
 * the shape and behaviour of a message) and twelve values for the font,
 * the colors, the layout and the lifetime. Applying one writes those
 * controls through the same path the controls tab uses, so the overlay in
 * OBS changes as the click lands. Nothing is stored about which preset is
 * active: it is derived by comparing the controls to the bundles, so a
 * streamer who edits one value after applying a preset is shown no preset,
 * which is the truth.
 *
 * Fixed, first-party, versioned with the overlay document. A streamer's own
 * presets are a later chapter and would need a table.
 */
class ChatPresets
{
    public const PRODUCT = 'twitch-chat-overlay';

    public const OVERLAY_REF = 'chat';

    /**
     * Every key a preset writes, in the order the overlay declares them.
     */
    public const KEYS = [
        'skin', 'layout', 'font', 'font_size', 'twitch_colors', 'name_color', 'text_color',
        'accent', 'background', 'background_color', 'lifetime', 'show_badges', 'emote_size',
    ];

    /**
     * @var array<string, array{label: string, blurb: string, values: array<string, string>}>
     */
    public const PRESETS = [
        'clean' => [
            'label' => 'Clean',
            'blurb' => 'Glass rows, everyone in their own Twitch color. The one you never regret.',
            'values' => [
                'skin' => 'clean', 'layout' => 'bottom', 'font' => 'Albert Sans', 'font_size' => '22',
                'twitch_colors' => '1', 'name_color' => '#ffffff', 'text_color' => '#ffffff', 'accent' => '#9146ff',
                'background' => 'glass', 'background_color' => '#0f0f14', 'lifetime' => '0', 'show_badges' => '1', 'emote_size' => '28',
            ],
        ],
        'terminal' => [
            'label' => 'Terminal',
            'blurb' => 'Green on black in a mono font, a prompt before every name and a cursor blinking on the last line.',
            'values' => [
                'skin' => 'terminal', 'layout' => 'bottom', 'font' => 'JetBrains Mono', 'font_size' => '20',
                'twitch_colors' => '0', 'name_color' => '#7cff7c', 'text_color' => '#c8f7c8', 'accent' => '#3dff3d',
                'background' => 'solid', 'background_color' => '#050805', 'lifetime' => '0', 'show_badges' => '0', 'emote_size' => '22',
            ],
        ],
        'bubbles' => [
            'label' => 'Bubbles',
            'blurb' => 'Speech bubbles with the name above each one, in a round friendly font. For cozy streams.',
            'values' => [
                'skin' => 'bubbles', 'layout' => 'bottom', 'font' => 'Fredoka', 'font_size' => '22',
                'twitch_colors' => '1', 'name_color' => '#ffffff', 'text_color' => '#ffffff', 'accent' => '#f472b6',
                'background' => 'solid', 'background_color' => '#2a1b3d', 'lifetime' => '0', 'show_badges' => '1', 'emote_size' => '28',
            ],
        ],
        'neon' => [
            'label' => 'Neon',
            'blurb' => 'No background, a thin glowing outline and names that light up. Late nights and dark games.',
            'values' => [
                'skin' => 'neon', 'layout' => 'bottom', 'font' => 'Space Grotesk', 'font_size' => '22',
                'twitch_colors' => '1', 'name_color' => '#ffffff', 'text_color' => '#e0f2fe', 'accent' => '#22d3ee',
                'background' => 'none', 'background_color' => '#0b1020', 'lifetime' => '0', 'show_badges' => '1', 'emote_size' => '28',
            ],
        ],
        'paper' => [
            'label' => 'Paper',
            'blurb' => 'Cream cards with dark ink and a soft shadow. The light one, for bright rooms and art streams.',
            'values' => [
                'skin' => 'paper', 'layout' => 'bottom', 'font' => 'Inter', 'font_size' => '21',
                'twitch_colors' => '0', 'name_color' => '#6d28d9', 'text_color' => '#1f1a2e', 'accent' => '#f59e0b',
                'background' => 'solid', 'background_color' => '#fbf7ee', 'lifetime' => '0', 'show_badges' => '1', 'emote_size' => '26',
            ],
        ],
        'broadcast' => [
            'label' => 'Broadcast',
            'blurb' => 'A news ticker along the bottom edge, names in caps. Just Chatting and podcast formats.',
            'values' => [
                'skin' => 'broadcast', 'layout' => 'ticker', 'font' => 'Inter', 'font_size' => '22',
                'twitch_colors' => '0', 'name_color' => '#ffd166', 'text_color' => '#ffffff', 'accent' => '#ef4444',
                'background' => 'solid', 'background_color' => '#111827', 'lifetime' => '0', 'show_badges' => '0', 'emote_size' => '24',
            ],
        ],
        'caption' => [
            'label' => 'Caption',
            'blurb' => 'Big centred text with a heavy shadow, gone after twelve seconds. Subtitles for IRL and talking-head streams.',
            'values' => [
                'skin' => 'caption', 'layout' => 'bottom', 'font' => 'Albert Sans', 'font_size' => '34',
                'twitch_colors' => '0', 'name_color' => '#ffd166', 'text_color' => '#ffffff', 'accent' => '#ffd166',
                'background' => 'none', 'background_color' => '#000000', 'lifetime' => '12', 'show_badges' => '0', 'emote_size' => '34',
            ],
        ],
        'pixel' => [
            'label' => 'Pixel',
            'blurb' => 'A pixel font, chunky borders and a hard drop shadow. Retro and indie games.',
            'values' => [
                'skin' => 'pixel', 'layout' => 'bottom', 'font' => 'Silkscreen', 'font_size' => '18',
                'twitch_colors' => '0', 'name_color' => '#ffe66d', 'text_color' => '#ffffff', 'accent' => '#ff6b6b',
                'background' => 'solid', 'background_color' => '#1a1a2e', 'lifetime' => '0', 'show_badges' => '1', 'emote_size' => '24',
            ],
        ],
        'cards' => [
            'label' => 'Cards',
            'blurb' => 'Each message its own card, the name as a header bar in the chatter\'s color, emotes big. Room for slow chats.',
            'values' => [
                'skin' => 'cards', 'layout' => 'bottom', 'font' => 'Albert Sans', 'font_size' => '21',
                'twitch_colors' => '1', 'name_color' => '#ffffff', 'text_color' => '#f4f4f5', 'accent' => '#9146ff',
                'background' => 'solid', 'background_color' => '#18181b', 'lifetime' => '0', 'show_badges' => '1', 'emote_size' => '36',
            ],
        ],
        'vapor' => [
            'label' => 'Vapor',
            'blurb' => 'Pink and cyan on deep purple glass, newest at the top, emotes oversized. For the chat that is mostly emotes anyway.',
            'values' => [
                'skin' => 'vapor', 'layout' => 'top', 'font' => 'Space Grotesk', 'font_size' => '22',
                'twitch_colors' => '0', 'name_color' => '#ff71ce', 'text_color' => '#ffffff', 'accent' => '#01cdfe',
                'background' => 'glass', 'background_color' => '#2d0a4e', 'lifetime' => '0', 'show_badges' => '1', 'emote_size' => '44',
            ],
        ],
    ];

    public static function has(string $product): bool
    {
        return $product === self::PRODUCT;
    }

    /**
     * The overlay the presets write to, for an install of the product.
     */
    public static function overlayFor(RecipeInstance $instance): ?OverlayTemplate
    {
        $id = $instance->primitive_map['overlays'][self::OVERLAY_REF] ?? null;

        return $id ? OverlayTemplate::find($id) : null;
    }

    /**
     * The list the product page renders. `active` is the preset whose every
     * value the overlay's controls currently hold, or none. `preview` is the
     * handful of values a card needs to draw a swatch.
     *
     * @return list<array{key: string, label: string, blurb: string, active: bool, preview: array<string, string>}>
     */
    public static function forProduct(string $product, ?RecipeInstance $instance): array
    {
        if (! self::has($product)) {
            return [];
        }

        $current = [];
        if ($instance && ($template = self::overlayFor($instance))) {
            $current = $template->controls()
                ->whereIn('key', self::KEYS)
                ->pluck('value', 'key')
                ->all();
        }

        return collect(self::PRESETS)
            ->map(fn (array $preset, string $key) => [
                'key' => $key,
                'label' => $preset['label'],
                'blurb' => $preset['blurb'],
                'active' => $current !== [] && self::matches($preset['values'], $current),
                'preview' => [
                    'skin' => $preset['values']['skin'],
                    'font' => $preset['values']['font'],
                    'name_color' => $preset['values']['name_color'],
                    'text_color' => $preset['values']['text_color'],
                    'accent' => $preset['values']['accent'],
                    'background' => $preset['values']['background'],
                    'background_color' => $preset['values']['background_color'],
                    'twitch_colors' => $preset['values']['twitch_colors'],
                    'layout' => $preset['values']['layout'],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Write a preset onto the overlay's controls. Returns the controls that
     * were written, so the caller can broadcast each the way the controls
     * tab does. A control the overlay does not have (an install from before
     * a key existed) is skipped, never created.
     *
     * @return list<OverlayControl>
     */
    public static function apply(OverlayTemplate $template, string $key): array
    {
        return self::applyValues($template, self::PRESETS[$key]['values']);
    }

    /**
     * Write any bundle onto the overlay's controls - a built-in preset's or a
     * saved one's (UserChatPreset). Same skip rule as apply(): a key the
     * overlay has no control for is passed over, and a control the bundle does
     * not name is left as it is.
     *
     * @param  array<string, string>  $values
     * @return list<OverlayControl>
     */
    public static function applyValues(OverlayTemplate $template, array $values): array
    {
        $written = [];

        foreach ($values as $controlKey => $value) {
            $control = $template->controls()->where('key', $controlKey)->first();
            if (! $control) {
                continue;
            }

            $control->writeValue((string) $value);
            $written[] = $control;
        }

        return $written;
    }

    /**
     * The overlay's look as it stands, in the shape a preset bundle holds it:
     * keyed by control key, in KEYS order, only the keys the overlay has. This
     * is what a saved preset captures.
     *
     * @return array<string, string>
     */
    public static function currentValues(OverlayTemplate $template): array
    {
        $current = $template->controls()
            ->whereIn('key', self::KEYS)
            ->pluck('value', 'key')
            ->all();

        $values = [];
        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $current)) {
                $values[$key] = (string) $current[$key];
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, ?string>  $current
     */
    private static function matches(array $values, array $current): bool
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $current) || (string) $current[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
