<?php

namespace App\Support;

use App\Models\OverlayAccessToken;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Models\UserChatPreset;

/**
 * A product's designer, read from the `designer` block of its manifest.
 *
 * Until 2026-09-28 the designer was hard-wired to the Twitch Chat product: its
 * ten looks, its five groups and its two extras were constants on two classes
 * (ChatPresets, ChatDesigner), and every route and controller gated on the
 * product's slug. A product declares all of that in its manifest now, next to
 * the overlay it installs, and having the block is what gives it the page.
 * The knobs themselves are the overlay's own controls - label, type, bounds
 * and choices all live on the row - so the block only says how they are
 * arranged, what the presets write, and which account settings sit beside
 * them. RecipeManifestValidator holds the block against the document.
 *
 * A preset is nothing but a bundle of values for the designer's keys. Applying
 * one writes those controls through the same path the Values tab uses, so the
 * overlay in OBS changes as the click lands. Nothing is stored about which
 * preset is active: it is derived by comparing the controls to the bundles, so
 * a streamer who edits one value after applying a preset is shown no preset,
 * which is the truth. A streamer's own looks (UserChatPreset) are bundles of
 * the same shape, captured server-side.
 */
class ProductDesigner
{
    /**
     * The control types the designer has a knob for: a select or a font
     * picker for text, a slider for number, a switch for boolean, a picker
     * for color. Every declared control of one of these types must be in a
     * group (or be the skin strip); a counter, timer, expression or datetime
     * control is not a look and stays on the Values tab.
     */
    public const KNOB_TYPES = ['text', 'number', 'boolean', 'color'];

    /**
     * The account-level extras a manifest may switch on, and nothing else.
     * The schema's enum lists the same three; a test holds them together.
     */
    public const EXTRAS = ['sample_chat', 'chat_window', 'chat_filters'];

    /** The preview's browser-source size when the block declares no stage. */
    public const DEFAULT_STAGE = ['w' => 1920, 'h' => 1080];

    /**
     * Name and marker on the preview's access token.
     *
     * `metadata.purpose` is what keeps a preview out of the OBS answer: the
     * only sign of which overlays OBS actually loads is which slugs a link has
     * served, and the designer serves the same slug every time it is opened.
     * The purpose string predates the unified designer and is kept, because
     * live tokens on prod carry it and the sweep below matches on it.
     */
    public const TOKEN_PURPOSE = 'chat_designer';

    public const TOKEN_NAME = 'Designer preview';

    /** Where the plaintext lives between renders. See previewToken(). */
    public const TOKEN_SESSION_KEY = 'chat_designer_token';

    /**
     * The block, or null for a product that has no designer.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>|null
     */
    public static function declared(array $manifest): ?array
    {
        $designer = $manifest['designer'] ?? null;

        return is_array($designer) ? $designer : null;
    }

    /**
     * Every key the designer edits: the skin key first, then each group's
     * keys in page order. This is what a preset writes and what a saved look
     * captures.
     *
     * @param  array<string, mixed>  $designer
     * @return list<string>
     */
    public static function keys(array $designer): array
    {
        $keys = [];
        if (isset($designer['skin_key'])) {
            $keys[] = $designer['skin_key'];
        }
        foreach ($designer['groups'] ?? [] as $group) {
            foreach ($group['keys'] ?? [] as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * The overlay the designer edits, for an install of the product.
     *
     * @param  array<string, mixed>  $designer
     */
    public static function overlayFor(array $designer, RecipeInstance $instance): ?OverlayTemplate
    {
        $id = $instance->primitive_map['overlays'][$designer['overlay']] ?? null;

        return $id ? OverlayTemplate::find($id) : null;
    }

    /**
     * The presets, each with the whole bundle it writes, in manifest order.
     *
     * The product page gets `active` computed server-side (forProduct()),
     * because a click there reloads the page anyway. The designer gets the
     * values instead and works out which preset is active itself, on every
     * knob turn, without a round trip - the same derivation, in the only
     * place that can run it often enough. Still derived, still nothing stored.
     *
     * @param  array<string, mixed>  $designer
     * @return list<array{key: string, label: string, blurb: string, values: array<string, string>}>
     */
    public static function presets(array $designer): array
    {
        return array_values(array_map(fn (array $preset) => [
            'key' => $preset['key'],
            'label' => $preset['label'],
            'blurb' => $preset['blurb'],
            'values' => $preset['values'],
        ], $designer['presets'] ?? []));
    }

    /**
     * One preset by key, or null.
     *
     * @param  array<string, mixed>  $designer
     * @return array{key: string, label: string, blurb: string, values: array<string, string>}|null
     */
    public static function preset(array $designer, string $key): ?array
    {
        foreach (self::presets($designer) as $preset) {
            if ($preset['key'] === $key) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * The skin strip: one entry per preset, in preset order, or nothing for a
     * product with no skin key. A skin IS a preset key, one to one, by the
     * rule the validator enforces (`values[skin_key]` equals the preset key),
     * so adding a look stays one entry in one list.
     *
     * @param  array<string, mixed>  $designer
     * @return list<array{value: string, label: string, hint: string}>
     */
    public static function skins(array $designer): array
    {
        if (! isset($designer['skin_key'])) {
            return [];
        }

        return array_map(fn (array $preset) => [
            'value' => $preset['key'],
            'label' => $preset['label'],
            'hint' => $preset['blurb'],
        ], self::presets($designer));
    }

    /**
     * The groups as the page renders them.
     *
     * @param  array<string, mixed>  $designer
     * @return list<array{title: string, keys: list<string>}>
     */
    public static function groups(array $designer): array
    {
        return array_values(array_map(fn (array $group) => [
            'title' => $group['title'],
            'keys' => array_values($group['keys']),
        ], $designer['groups'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $designer
     * @return list<string>
     */
    public static function extras(array $designer): array
    {
        return array_values(array_filter(
            $designer['extras'] ?? [],
            fn ($extra) => in_array($extra, self::EXTRAS, true),
        ));
    }

    /**
     * The preview sizes, defaulted. The page picks: the last entry whose
     * `when` the controls satisfy, else the first entry with no `when`.
     *
     * @param  array<string, mixed>  $designer
     * @return list<array{w: int, h: int, when?: array<string, string>}>
     */
    public static function stage(array $designer): array
    {
        $stage = array_values($designer['stage'] ?? []);

        return $stage === [] ? [self::DEFAULT_STAGE] : $stage;
    }

    /**
     * What the product page renders: the presets with the one the overlay
     * currently holds marked, and the group titles the card's copy names.
     * Null for a product with no designer.
     *
     * @param  array<string, mixed>  $manifest
     * @return array{presets: list<array{key: string, label: string, blurb: string, active: bool}>, groups: list<string>}|null
     */
    public static function forProduct(array $manifest, ?RecipeInstance $instance): ?array
    {
        $designer = self::declared($manifest);
        if ($designer === null) {
            return null;
        }

        $current = [];
        if ($instance && ($template = self::overlayFor($designer, $instance))) {
            $current = $template->controls()
                ->whereIn('key', self::keys($designer))
                ->pluck('value', 'key')
                ->all();
        }

        return [
            'presets' => array_map(fn (array $preset) => [
                'key' => $preset['key'],
                'label' => $preset['label'],
                'blurb' => $preset['blurb'],
                'active' => $current !== [] && self::matches($preset['values'], $current),
            ], self::presets($designer)),
            'groups' => array_column(self::groups($designer), 'title'),
        ];
    }

    /**
     * Write a bundle onto the overlay's controls - a built-in preset's or a
     * saved look's. Returns the controls that were written, so the caller can
     * broadcast each the way the Values tab does. A control the overlay does
     * not have (an install from before a key existed) is skipped, never
     * created, and a control the bundle does not name is left as it is.
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
     * keyed by control key, in designer-key order, only the keys the overlay
     * has. This is what a saved look captures.
     *
     * @param  array<string, mixed>  $designer
     * @return array<string, string>
     */
    public static function currentValues(array $designer, OverlayTemplate $template): array
    {
        $keys = self::keys($designer);
        $current = $template->controls()
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->all();

        $values = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $current)) {
                $values[$key] = (string) $current[$key];
            }
        }

        return $values;
    }

    /**
     * The overlay's designer controls as the page renders them: the row's own
     * label, type, value and config, plus the id the value endpoint needs.
     *
     * Keyed by control key, because the page addresses them by key. A control
     * the overlay does not have (an install from before a key existed) is
     * simply absent, and the group renders one row fewer rather than a knob
     * that writes nowhere.
     *
     * @param  array<string, mixed>  $designer
     * @return array<string, array{id: int, key: string, label: string, type: string, value: string, config: array<string, mixed>}>
     */
    public static function controls(array $designer, OverlayTemplate $template): array
    {
        return $template->controls()
            ->whereIn('key', self::keys($designer))
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
     * The streamer's own saved looks for this product, by name.
     *
     * @return list<array{id: int, name: string, values: array<string, string>}>
     */
    public static function savedPresets(User $user, string $product): array
    {
        return UserChatPreset::where('user_id', $user->id)
            ->where('product', $product)
            ->orderBy('name')
            ->get()
            ->map(fn (UserChatPreset $preset) => $preset->toDesigner())
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
     * and it is the user's own token for their own overlay. One token serves
     * every product's designer: it is a read token on the account, and the
     * frame's URL says which overlay to render.
     *
     * One per session, and a day at most. Minting a new one takes the account's
     * EXPIRED preview tokens and their access rows with it, so the tokens page
     * never fills up with them - and only the expired ones. Until 2026-09-22 it
     * swept every preview token the account had, live or not, which made the
     * designer open in two browsers a fight: each page load killed the other
     * session's frame. A token that is still valid belongs to a session that
     * may still be looking at it; it goes when it expires, a day at most.
     */
    public static function previewToken(User $user): string
    {
        $held = session(self::TOKEN_SESSION_KEY);

        if (is_string($held) && strlen($held) === 64) {
            // findByToken() already refuses an expired or inactive row, so a
            // held token that has run out falls through to a fresh mint.
            $token = OverlayAccessToken::findByToken($held);

            if ($token && $token->user_id === $user->id && ($token->metadata['purpose'] ?? null) === self::TOKEN_PURPOSE) {
                return $held;
            }
        }

        $stale = OverlayAccessToken::where('user_id', $user->id)
            ->where('metadata->purpose', self::TOKEN_PURPOSE)
            ->where(fn ($query) => $query->where('expires_at', '<=', now())->orWhere('is_active', false))
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
     * generated chat instead of the channel's own. The renderer ignores the
     * sample flag for an overlay that never reads chat, so every product's
     * preview is asked for the same way.
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
