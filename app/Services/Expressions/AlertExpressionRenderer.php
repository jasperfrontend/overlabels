<?php

namespace App\Services\Expressions;

use App\Models\OverlayControl;
use App\Models\User;

/**
 * Renders an alert template's `tts_expression` against the same flat
 * $templateData payload that ships with AlertTriggered.
 *
 * SINGLE-PASS BY DESIGN: matches the day-one "tags never reparse" rule and
 * mirrors BotExpressionResolver. Substituted values are never re-scanned for
 * tags.
 *
 * Tag families inside [[[...]]]:
 *   c:<key>            -> own OverlayControl by key
 *   c:<service>:<key>  -> service-managed OverlayControl by broadcastKey
 *   <flat key>         -> $templateData lookup (event.user_name, user_name, ...)
 *
 * Pipe formatters (e.g. |number, |currency:USD) run after lookup via
 * ExpressionFormatter. Unknown tags resolve to empty string per the
 * null-over-placeholder rule.
 *
 * Default values: `[[[key ?? literal]]]` emits the literal text VERBATIM when
 * the value resolves empty - a presentation fallback for ABSENCE only, never
 * re-scanned for tags and never piped. Both sinks here (TTS audio, chat) are
 * plain text, so no HTML encoding is applied.
 *
 * Reserved gate control: if the user owns any boolean control with key `tts`
 * whose value is "0", render() returns null. The caller treats null as "do not
 * include tts_text in the broadcast". The control may live on any of the user's
 * overlays (template-scoped) or be user-scoped - we don't care, because all the
 * streamer wants is a single switch to mute TTS.
 */
class AlertExpressionRenderer
{
    // Group 1: tag key. Group 2 (optional): pipe formatter. Group 3 (optional,
    // after `??`): literal default emitted when the value resolves empty.
    private const string TAG_REGEX = '/\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?(?:\s*\?\?\s*(.*?))?]]]/';

    private const int MAX_RESOLVED_LENGTH = 500;

    private const string GATE_KEY = 'tts';

    /**
     * @param  array<string,mixed>  $templateData  Flat dot-keyed map already
     *                                             built for the alert payload.
     */
    public function render(User $user, ?string $expression, array $templateData): ?string
    {
        if ($this->isGatedOff($user)) {
            return null;
        }

        return $this->resolve($user, $expression, $templateData);
    }

    /**
     * Render an expression for posting to chat via BotChatOutbox. Identical tag
     * resolution to render(), but WITHOUT the `tts` mute gate - bot chat
     * messages are gated only by the user's `bot_enabled` flag, checked at the
     * dispatch site. The 500-char cap doubles as a fit for Twitch's chat limit.
     *
     * @param  array<string,mixed>  $templateData
     */
    public function renderMessage(User $user, ?string $expression, array $templateData): ?string
    {
        return $this->resolve($user, $expression, $templateData);
    }

    /**
     * Core single-pass tag substitution shared by render() and renderMessage().
     *
     * @param  array<string,mixed>  $templateData
     */
    private function resolve(User $user, ?string $expression, array $templateData): ?string
    {
        if ($expression === null || trim($expression) === '') {
            return null;
        }

        $controls = $this->loadControls($user);
        $locale = (string) ($user->preference('locale', 'en-US'));

        $resolved = preg_replace_callback(
            self::TAG_REGEX,
            function (array $matches) use ($controls, $templateData, $locale): string {
                $key = $matches[1];
                $pipe = ($matches[2] ?? '') !== '' ? $matches[2] : null;
                $default = isset($matches[3]) ? trim($matches[3]) : null;
                $value = $this->lookup($key, $controls, $templateData);

                // Absence backstop: empty value renders the literal default.
                if ($value === '' && $default !== null && $default !== '') {
                    return $default;
                }

                if ($pipe !== null) {
                    $value = ExpressionFormatter::apply($value, $pipe, $locale);
                }

                return $value;
            },
            $expression
        );

        if (mb_strlen($resolved) > self::MAX_RESOLVED_LENGTH) {
            $resolved = mb_substr($resolved, 0, self::MAX_RESOLVED_LENGTH);
        }

        $resolved = trim($resolved);

        return $resolved === '' ? null : $resolved;
    }

    /**
     * @param  array<string,string>  $controls
     * @param  array<string,mixed>  $templateData
     */
    private function lookup(string $key, array $controls, array $templateData): string
    {
        if (str_starts_with($key, 'c:')) {
            $controlKey = substr($key, 2);

            return (string) ($controls[$controlKey] ?? '');
        }

        $value = $templateData[$key] ?? null;

        return $value === null ? '' : (string) $value;
    }

    /**
     * @return array<string,string>
     */
    private function loadControls(User $user): array
    {
        $rows = OverlayControl::where('user_id', $user->id)->get();
        $map = [];
        foreach ($rows as $control) {
            $identifier = $control->source_managed
                ? $control->broadcastKey()
                : $control->key;
            $map[$identifier] = $control->resolveDisplayValue();
        }

        return $map;
    }

    /**
     * User opts out of TTS by creating a boolean control named `tts` and
     * toggling it off. Any such control of the user's gates - we don't restrict
     * by overlay_template_id because the streamer's mental model is "one switch
     * that mutes my TTS", not "one switch per overlay". Absent control = TTS on.
     */
    private function isGatedOff(User $user): bool
    {
        return OverlayControl::where('user_id', $user->id)
            ->where('key', self::GATE_KEY)
            ->where('type', 'boolean')
            ->where('value', '0')
            ->exists();
    }
}
