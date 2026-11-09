<?php

namespace App\Services\Messages;

use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Controls\ExpressionControlHydrator;
use App\Support\Conditionals;
use App\Support\ControlSnapshot;
use App\Support\Dsl;

/**
 * Renders an alert template's `tts_message` against the same flat
 * $templateData payload that ships with AlertTriggered.
 *
 * SINGLE-PASS BY DESIGN: matches the day-one "tags never reparse" rule and
 * mirrors BotCommandResolver. Substituted values are never re-scanned for
 * tags.
 *
 * Conditionals run FIRST, on the source, exactly as in BotCommandResolver:
 * `[[[event.streak_months]]] month[[[if:event.streak_months != 1]]]s[[[endif]]]`
 * decides which text survives (App\Support\Conditionals), and the tag pass
 * then runs once over what is left. `foreach` is refused at save time.
 *
 * Tag families inside [[[...]]]:
 *   c:<key>            -> own OverlayControl by key
 *   c:<service>:<key>  -> service-managed OverlayControl by broadcastKey
 *   <flat key>         -> $templateData lookup (event.user_name, user_name, ...)
 *
 * Pipe formatters (e.g. |number, |currency:USD) run after lookup via
 * PipeFormatter. Unknown tags resolve to empty string per the
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
class AlertMessageRenderer
{
    // Group 1: tag key. Group 2 (optional): pipe formatter. Group 3 (optional,
    // after `??`): literal default emitted when the value resolves empty.
    // Built from the shared DSL spec (resources/dsl/dsl.json) so this renderer,
    // the bot resolver and the overlay renderer cannot drift apart.
    private static function tagRegex(): string
    {
        return Dsl::tagPattern();
    }

    /**
     * Ceiling on a resolved TTS line. Same number as the source cap on
     * `tts_message`, so a line that fits the editor is never cut short by
     * a few expanded tags. It still bounds what one alert can spend on
     * synthesis when a tag value (a supporter's note) runs long.
     */
    private const int MAX_TTS_LENGTH = 2000;

    /** Twitch rejects a chat message over 500 characters; it never splits one. */
    public const int CHAT_PART_LENGTH = 500;

    /**
     * How many chat messages one alert may post. Longer output is split
     * into this many parts and the rest is dropped, so a long note cannot
     * flood chat. The editor's help text states this number.
     */
    public const int CHAT_MAX_PARTS = 3;

    public function __construct(
        private readonly ExpressionControlHydrator $expressions,
    ) {}

    private const string GATE_KEY = 'tts';

    /**
     * @param  array<string,mixed>  $templateData  Flat dot-keyed map already
     *                                             built for the alert payload.
     */
    public function render(User $user, ?string $message, array $templateData): ?string
    {
        if ($this->isGatedOff($user)) {
            return null;
        }

        return $this->resolve($user, $message, $templateData, self::MAX_TTS_LENGTH);
    }

    /**
     * Render a message for posting to chat via BotChatOutbox. Identical tag
     * resolution to render(), but WITHOUT the `tts` mute gate - bot chat
     * messages are gated only by the user's `bot_enabled` flag, checked at the
     * dispatch site. Not length-capped: chatParts() fits it to Twitch.
     *
     * @param  array<string,mixed>  $templateData
     */
    public function renderMessage(User $user, ?string $message, array $templateData): ?string
    {
        return $this->resolve($user, $message, $templateData, null);
    }

    /**
     * Split a rendered chat message into the messages the bot posts, in
     * order. Twitch refuses anything over CHAT_PART_LENGTH, so longer output
     * is broken at the last space that fits (a hard cut only for a single
     * word longer than a whole message) and capped at CHAT_MAX_PARTS.
     *
     * @return list<string>
     */
    public static function chatParts(?string $message): array
    {
        $rest = trim((string) $message);
        $parts = [];

        while ($rest !== '' && count($parts) < self::CHAT_MAX_PARTS) {
            if (mb_strlen($rest) <= self::CHAT_PART_LENGTH) {
                $parts[] = $rest;
                break;
            }

            // The character just past the limit counts: a space there means
            // the whole first CHAT_PART_LENGTH characters fit as they are.
            $window = mb_substr($rest, 0, self::CHAT_PART_LENGTH + 1);
            $cut = self::lastWhitespace($window);
            if ($cut === null || $cut === 0) {
                $cut = self::CHAT_PART_LENGTH;
            }

            $part = rtrim(mb_substr($rest, 0, $cut));
            if ($part !== '') {
                $parts[] = $part;
            }
            $rest = ltrim(mb_substr($rest, $cut));
        }

        return $parts;
    }

    /** Character offset of the last whitespace in $text, or null. */
    private static function lastWhitespace(string $text): ?int
    {
        if (! preg_match_all('/\s/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $byteOffset = end($matches[0])[1];

        return mb_strlen(substr($text, 0, $byteOffset));
    }

    /**
     * Render everything ONE alert says - its TTS line, its chat line and
     * the payload the overlay renders its HTML from - against a single
     * control snapshot. render() and renderMessage() each take their own
     * snapshot, so an alert built from the pair would speak one roll of a
     * random-mode control and post another; the overlay, which ticks its
     * random controls locally, would show a third. This is the one door
     * for a fired alert. The tts gate applies to `tts` only; `chat` is
     * gated on bot_enabled at the dispatch site, as before.
     *
     * `data` is $templateData plus the snapshot's rolls under their `c:`
     * keys. The overlay's alert merge prefers payload keys over its live
     * data, so that is what makes the screen agree with the speaker.
     *
     * @param  array<string,mixed>  $templateData
     * @return array{tts: ?string, chat: ?string, data: array<string,mixed>}
     */
    public function renderAlert(User $user, ?string $ttsMessage, ?string $chatMessage, array $templateData): array
    {
        $snapshot = ControlSnapshot::for($user);
        $data = array_merge($templateData, $snapshot->rolls);

        return [
            'tts' => $this->isGatedOff($user) ? null : $this->resolveWith($user, $ttsMessage, $snapshot->values, $data, self::MAX_TTS_LENGTH),
            'chat' => $this->resolveWith($user, $chatMessage, $snapshot->values, $data, null),
            'data' => $data,
        ];
    }

    /**
     * Single-template entry: takes its own snapshot. Anything resolving more
     * than one template for the same query goes through renderAlert().
     *
     * @param  array<string,mixed>  $templateData
     */
    private function resolve(User $user, ?string $message, array $templateData, ?int $maxLength): ?string
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        return $this->resolveWith($user, $message, ControlSnapshot::for($user)->values, $templateData, $maxLength);
    }

    /**
     * Core single-pass tag substitution. $controls is a snapshot's `values`.
     * $maxLength cuts the result (TTS); null leaves it whole (chat, which
     * chatParts() splits instead of cutting).
     *
     * @param  array<string,string>  $controls
     * @param  array<string,mixed>  $templateData
     */
    private function resolveWith(User $user, ?string $message, array $controls, array $templateData, ?int $maxLength): ?string
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        // Expression Controls hold no value of their own - see
        // ExpressionControlHydrator. Same call, same reason, as
        // BotCommandResolver::resolve().
        $controls = $this->expressions->hydrate($user, $controls, $message);

        $locale = (string) ($user->preference('locale', 'en-US'));

        $message = Conditionals::render(
            $message,
            fn (string $key): string => $this->lookup($key, $controls, $templateData),
        );

        $resolved = preg_replace_callback(
            self::tagRegex(),
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
                    $value = PipeFormatter::apply($value, $pipe, $locale);
                }

                return $value;
            },
            $message
        );

        if ($maxLength !== null && mb_strlen($resolved) > $maxLength) {
            $resolved = mb_substr($resolved, 0, $maxLength);
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
