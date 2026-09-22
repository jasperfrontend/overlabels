<?php

namespace App\Support;

use App\Models\OverlayAccessToken;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Models\UserChatPreset;

/**
 * Everything the chat designer needs that the database does not already say.
 *
 * The thirteen controls carry their own label, type and (for the numbers)
 * min/max/step, so the designer reads those from the rows rather than
 * restating them. What a row cannot say is which VALUES a text control may
 * take: `skin`, `layout` and `background` are each a small closed vocabulary
 * that only the overlay's own CSS knows about. That vocabulary is here, and a
 * test holds it against the recipe so a skin added to the CSS without a choice
 * here fails rather than quietly rendering wrong.
 *
 * `font` is the exception, and has SUGGESTED_FONTS instead: its vocabulary is
 * open - every family Bunny Fonts serves - because the overlay loads whichever
 * family the control holds rather than picking from a <link> in its head.
 *
 * The skins are NOT declared: a skin is a preset key, one to one, by the same
 * rule ChatPresets already keeps (`values['skin']` equals the entry key). So
 * adding a look stays one entry in one constant.
 *
 * @see ChatPresets
 */
class ChatDesigner
{
    /**
     * Name and marker on the preview's access token.
     *
     * `metadata.purpose` is what keeps a preview out of the OBS answer: the
     * only sign of which overlays OBS actually loads is which slugs a link has
     * served, and the designer serves the same slug every time it is opened.
     */
    public const TOKEN_PURPOSE = 'chat_designer';

    public const TOKEN_NAME = 'Chat designer preview';

    /** Where the plaintext lives between renders. See previewToken(). */
    public const TOKEN_SESSION_KEY = 'chat_designer_token';

    /**
     * Closed vocabularies for the text controls, in the order they are offered.
     *
     * @var array<string, list<array{value: string, label: string, hint: string}>>
     */
    public const CHOICES = [
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
    ];

    /**
     * Fonts worth putting in front of someone before they search.
     *
     * `font` is deliberately NOT in CHOICES: it is the one knob with an open
     * vocabulary, every family Bunny Fonts serves, and the overlay loads
     * whichever it is given rather than choosing from a list baked into its
     * head. These six are the shortlist the picker shows first - the ten
     * presets between them use exactly these, so the list is what a streamer
     * lands on anyway, and the search is there when they want out of it.
     *
     * A name here that Bunny does not serve is a row that writes a value the
     * value endpoint refuses, so a test holds this against the catalogue.
     *
     * @var list<array{value: string, hint: string}>
     */
    public const SUGGESTED_FONTS = [
        ['value' => 'Albert Sans', 'hint' => 'The default. Plain and wide.'],
        ['value' => 'Inter', 'hint' => 'Neutral, reads small.'],
        ['value' => 'Space Grotesk', 'hint' => 'Squarer, a little technical.'],
        ['value' => 'Fredoka', 'hint' => 'Round and friendly.'],
        ['value' => 'JetBrains Mono', 'hint' => 'Monospaced.'],
        ['value' => 'Silkscreen', 'hint' => 'Pixel type. Best large.'],
    ];

    /**
     * How the knobs are grouped down the left column.
     *
     * `skin` is absent on purpose: it has the preset strip and the skin picker
     * above these, not a row among them. A test asserts every other key of
     * ChatPresets::KEYS appears here exactly once, so a fourteenth control
     * cannot be added to the overlay and left off the designer.
     *
     * @var list<array{title: string, keys: list<string>}>
     */
    public const GROUPS = [
        ['title' => 'Layout', 'keys' => ['layout', 'lifetime']],
        ['title' => 'Type', 'keys' => ['font', 'font_size', 'emote_size']],
        ['title' => 'Colors', 'keys' => ['twitch_colors', 'name_color', 'text_color', 'accent']],
        ['title' => 'Background', 'keys' => ['background', 'background_color']],
        ['title' => 'Badges', 'keys' => ['show_badges']],
    ];

    /**
     * The skins, one per preset, in preset order.
     *
     * @return list<array{value: string, label: string, hint: string}>
     */
    public static function skins(): array
    {
        $skins = [];

        foreach (ChatPresets::PRESETS as $key => $preset) {
            $skins[] = ['value' => $key, 'label' => $preset['label'], 'hint' => $preset['blurb']];
        }

        return $skins;
    }

    /**
     * The presets, each with the whole bundle it writes.
     *
     * The product page gets `active` computed server-side, because a click
     * there reloads the page anyway. The designer gets the values instead and
     * works out which preset is active itself, on every knob turn, without a
     * round trip - the same derivation, in the only place that can run it
     * often enough. Still derived, still nothing stored.
     *
     * @return list<array{key: string, label: string, blurb: string, values: array<string, string>}>
     */
    public static function presets(): array
    {
        $presets = [];

        foreach (ChatPresets::PRESETS as $key => $preset) {
            $presets[] = [
                'key' => $key,
                'label' => $preset['label'],
                'blurb' => $preset['blurb'],
                'values' => $preset['values'],
            ];
        }

        return $presets;
    }

    /**
     * The streamer's own saved looks, by name.
     *
     * @return list<array{id: int, name: string, values: array<string, string>}>
     */
    public static function savedPresets(User $user): array
    {
        return UserChatPreset::where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (UserChatPreset $preset) => $preset->toDesigner())
            ->all();
    }

    /**
     * The overlay's look controls as the designer renders them: the row's own
     * label, type, value and config, plus the id the value endpoint needs.
     *
     * Keyed by control key, because the page addresses them by key. A control
     * the overlay does not have (an install from before a key existed) is
     * simply absent, and the group renders one row fewer rather than a knob
     * that writes nowhere.
     *
     * @return array<string, array{id: int, key: string, label: string, type: string, value: string, config: array<string, mixed>}>
     */
    public static function controls(OverlayTemplate $template): array
    {
        return $template->controls()
            ->whereIn('key', ChatPresets::KEYS)
            ->get()
            ->mapWithKeys(fn (OverlayControl $control) => [$control->key => [
                'id' => $control->id,
                'key' => $control->key,
                'label' => $control->label,
                'type' => $control->type,
                'value' => (string) $control->value,
                'config' => $control->config ?? [],
            ]])
            ->all();
    }

    /**
     * A short-lived token for the preview frame.
     *
     * The preview is the real overlay in an iframe, so it needs a real token:
     * the render endpoint has no session door, and the overlay's Echo
     * authorizer signs against the same token - which is the whole reason a
     * knob change lands in the frame at all, over the broadcast OBS is already
     * listening on.
     *
     * Held in the session and reused while it lives, rather than minted per
     * render. Minting per render would mean any re-render of the designer
     * changes the frame's src and reloads the preview mid-design, and would
     * pull the rug from under the frame already on screen. The session key is
     * the only place the plaintext can be kept - the row stores sha256 of it -
     * and it is the user's own token for their own overlay.
     *
     * One at a time, and a day at most. Minting a new one takes the account's
     * stale preview tokens and their access rows with it, so the tokens page
     * never fills up with them.
     */
    public static function previewToken(User $user): string
    {
        $held = session(self::TOKEN_SESSION_KEY);

        if (is_string($held) && strlen($held) === 64) {
            $token = OverlayAccessToken::findByToken($held);

            if ($token && $token->user_id === $user->id && ($token->metadata['purpose'] ?? null) === self::TOKEN_PURPOSE) {
                return $held;
            }
        }

        $stale = OverlayAccessToken::where('user_id', $user->id)
            ->where('metadata->purpose', self::TOKEN_PURPOSE)
            ->get();

        foreach ($stale as $token) {
            $token->accessLogs()->delete();
            $token->delete();
        }

        $generated = OverlayAccessToken::generateToken();

        $user->overlayAccessTokens()->create([
            'name' => self::TOKEN_NAME,
            'token_hash' => $generated['hash'],
            'token_prefix' => $generated['prefix'],
            'expires_at' => now()->addDay(),
            'abilities' => 'read',
            'metadata' => ['purpose' => self::TOKEN_PURPOSE],
        ]);

        session([self::TOKEN_SESSION_KEY => $generated['plain']]);

        return $generated['plain'];
    }

    /**
     * The preview frame's URL: the real overlay, on this origin, reading
     * generated chat instead of the channel's own.
     *
     * Deliberately NOT the hosted-overlay origin the OBS link uses. That one
     * is a second domain (overlabels.net on prod) and framing it would put the
     * preview cross-origin for no gain; the designer talks to the frame, and
     * .com serves every overlay URL anyway.
     */
    public static function previewUrl(OverlayTemplate $template, string $token): string
    {
        return "/overlay/{$template->slug}?chat=sample#{$token}";
    }
}
