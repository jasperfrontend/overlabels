<?php

namespace App\Support;

use App\Models\OverlayControl;
use Random\RandomException;

/**
 * Pure parsing for the two bot-only tag namespaces, `rand:` and `counter:`.
 *
 * No DB, no side effects - this class only reads a template string and reports
 * what it found. The writing half lives in BotCounterService; the reading half
 * is wired into BotCommandResolver::lookup().
 *
 * Both namespaces are declared in resources/dsl/dsl.json with an explicit
 * `scope: ["bot"]`, and both parse under the existing shared tag regex with no
 * lexical change: `rand:0-69` and `counter:wins` are already valid tag keys
 * (digits, hyphen and colon are all in the spec's keyRest class). NOTHING here
 * hand-rolls a tag regex - keys come out of Dsl::tagKeyPattern().
 */
final class BotTags
{
    private const string RAND_PREFIX = 'rand:';

    private const string COUNTER_PREFIX = 'counter:';

    /**
     * A `rand:` range is two non-negative whole numbers separated by a hyphen.
     *
     * Negatives are rejected rather than supported: a leading `-` makes the
     * separator ambiguous (`rand:-5-5` has three readings), and no streamer
     * rolls a negative Steven Level. The 15-digit cap keeps both bounds inside
     * PHP's integer range so the (int) casts below cannot silently clamp.
     */
    private const string RANGE_PATTERN = '/^(\d{1,15})-(\d{1,15})$/';

    /**
     * Every tag key in $text, pipe and `?? default` stripped.
     *
     * @return array<int,string>
     */
    public static function keys(string $text): array
    {
        preg_match_all(Dsl::tagKeyPattern(), $text, $matches);

        return $matches[1] ?? [];
    }

    /**
     * The distinct counter keys this text increments when it fires.
     *
     * Deduplicated on purpose: writing `[[[counter:wins]]]` twice in one
     * message must still count one win. The service bumps this list, not the
     * tag occurrences.
     *
     * @return array<int,string>
     */
    public static function counterKeys(string $text): array
    {
        $keys = [];

        foreach (self::keys($text) as $key) {
            if (str_starts_with($key, self::COUNTER_PREFIX)) {
                $keys[] = substr($key, strlen(self::COUNTER_PREFIX));
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * The raw argument of every `rand:` tag, in source order and NOT
     * deduplicated - two rolls in one message are two independent rolls, and
     * the validator wants to report each bad one.
     *
     * @return array<int,string>
     */
    public static function randArgs(string $text): array
    {
        $args = [];

        foreach (self::keys($text) as $key) {
            if (str_starts_with($key, self::RAND_PREFIX)) {
                $args[] = substr($key, strlen(self::RAND_PREFIX));
            }
        }

        return $args;
    }

    /**
     * Parse a `rand:` argument into [min, max], or null if it is malformed.
     *
     * Bounds are swapped when given high-first, matching how
     * OverlayControl::resolveRandomValue() already treats a reversed min/max.
     *
     * @return array{0:int,1:int}|null
     */
    public static function parseRange(string $arg): ?array
    {
        if (! preg_match(self::RANGE_PATTERN, $arg, $m)) {
            return null;
        }

        $min = (int) $m[1];
        $max = (int) $m[2];

        return $min > $max ? [$max, $min] : [$min, $max];
    }

    /**
     * Resolve a `rand:` argument to a value string. A malformed range resolves
     * empty rather than throwing: the validator refuses to save a command
     * carrying one, so reaching this with bad input means a row predates the
     * validation, and a live chat command is not the place to raise.
     *
     * @throws RandomException
     */
    public static function resolveRand(string $arg): string
    {
        $range = self::parseRange($arg);

        if ($range === null) {
            return '';
        }

        return (string) random_int($range[0], $range[1]);
    }

    /**
     * Whether $key is usable as a counter control key. Same rules as any other
     * user-created control, so a counter provisioned from chat is
     * indistinguishable from one made in the UI.
     */
    public static function isValidCounterKey(string $key): bool
    {
        if (in_array($key, OverlayControl::RESERVED_KEYS, true)) {
            return false;
        }

        return (bool) preg_match(OverlayControl::KEY_PATTERN, $key);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Near-miss detection.
    //
    // Everything below exists because null-over-placeholder, which is the
    // right rule for a tag that HAS no value yet, is the wrong rule for a tag
    // that could never have had one. `[[[rnd:0-69]]]` is not a Twitch tag
    // waiting to be populated, it is a typo - and resolving it to empty means
    // the command saves cleanly, then quietly drops the number in front of
    // chat with nothing anywhere to say why. These checks run at save time
    // only. The live fire path stays silent-on-block; chat is not a debugger.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Namespaces that resolve in a bot command. `c` covers `c:list` too,
     * since only the first segment is checked.
     *
     * `event` and `loop` are deliberately absent: the bot resolver does no
     * block processing and has no alert payload, so both resolve empty there.
     * They are declared in the shared spec for the overlay and alert runtimes.
     */
    private const array BOT_NAMESPACES = ['c', 'bot', 'rand', 'counter'];

    /** Edit distance within which a typo gets a "did you mean". */
    private const int SUGGEST_DISTANCE = 3;

    /**
     * Tag keys whose namespace this resolver has never heard of, mapped to the
     * closest real namespace when there is an obvious one.
     *
     * Safe to key off the colon: of the 68 bare Twitch tags, none contains one,
     * so a colon means the author was reaching for a namespace.
     *
     * @return array<string,string|null> tag key => suggested namespace or null
     */
    public static function unknownNamespaces(string $text): array
    {
        $unknown = [];

        foreach (self::keys($text) as $key) {
            if (! str_contains($key, ':')) {
                continue;
            }

            $namespace = Dsl::segments($key)[0];

            if (in_array($namespace, self::BOT_NAMESPACES, true)) {
                continue;
            }

            $unknown[$key] = self::closestNamespace($namespace);
        }

        return $unknown;
    }

    private static function closestNamespace(string $namespace): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach (self::BOT_NAMESPACES as $candidate) {
            $distance = levenshtein($namespace, $candidate);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $bestDistance <= self::SUGGEST_DISTANCE ? $best : null;
    }

    /**
     * Triple-bracket runs the tag pattern could not read.
     *
     * These are the loudest failure of the lot: an unparsed `[[[...]]]` is not
     * substituted, so it reaches Twitch chat character for character. Catches
     * a space where a colon belongs (`[[[counter wins]]]`) and the block
     * syntax, which the overlay renderer supports and the bot resolver does
     * not.
     *
     * @return array<int,string> The offending snippets, as written.
     */
    public static function malformedTags(string $text): array
    {
        // Strip everything that parses, then anything still holding brackets
        // is by definition something the resolver would leave alone.
        $leftovers = preg_replace(Dsl::tagPattern(), '', $text) ?? '';

        $open = Dsl::spec()['lexical']['open'] ?? '\[\[\[';
        $close = Dsl::spec()['lexical']['close'] ?? '\]\]\]';

        preg_match_all('/'.$open.'.*?'.$close.'/s', $leftovers, $closed);

        $found = $closed[0] ?? [];

        // An unterminated `[[[` never reaches the pattern above but is just as
        // visible in chat, so report the tail as its own snippet.
        $remaining = preg_replace('/'.$open.'.*?'.$close.'/s', '', $leftovers) ?? '';
        if (preg_match('/'.$open.'.{0,40}/s', $remaining, $dangling)) {
            $found[] = $dangling[0];
        }

        return array_values(array_unique($found));
    }

    /**
     * Tag-shaped content wrapped in too few brackets, e.g. `[[rand:0-69]]`.
     *
     * Only flagged when the inside really is a tag with a namespace we know,
     * so ordinary bracketed prose keeps working.
     *
     * @return array<int,string> The offending snippets, as written.
     */
    public static function underBracketedTags(string $text): array
    {
        $leftovers = preg_replace(Dsl::tagPattern(), '', $text) ?? '';

        preg_match_all('/\[\[([^\[\]]+)\]\]/', $leftovers, $matches, PREG_SET_ORDER);

        $found = [];

        foreach ($matches as $match) {
            $inner = $match[1];

            if (! preg_match(Dsl::tagPattern(), '[[['.$inner.']]]', $parsed)) {
                continue;
            }

            if (in_array(Dsl::segments($parsed[1])[0], self::BOT_NAMESPACES, true)) {
                $found[] = $match[0];
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * The namespaces worth naming in an error message, ready to read aloud.
     */
    public static function namespaceList(): string
    {
        return implode(', ', self::BOT_NAMESPACES).' (and c:list)';
    }
}
