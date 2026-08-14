<?php

namespace App\Support;

use App\Models\OverlayControl;
use Random\RandomException;

/**
 * Pure parsing for the two bot-only tag namespaces, `rand:` and `counter:`.
 *
 * No DB, no side effects - this class only reads a template string and reports
 * what it found. The writing half lives in BotCounterService; the reading half
 * is wired into BotExpressionResolver::lookup().
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
     * Every tag key in $expression, pipe and `?? default` stripped.
     *
     * @return array<int,string>
     */
    public static function keys(string $expression): array
    {
        preg_match_all(Dsl::tagKeyPattern(), $expression, $matches);

        return $matches[1] ?? [];
    }

    /**
     * The distinct counter keys this expression increments when it fires.
     *
     * Deduplicated on purpose: writing `[[[counter:wins]]]` twice in one
     * message must still count one win. The service bumps this list, not the
     * tag occurrences.
     *
     * @return array<int,string>
     */
    public static function counterKeys(string $expression): array
    {
        $keys = [];

        foreach (self::keys($expression) as $key) {
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
    public static function randArgs(string $expression): array
    {
        $args = [];

        foreach (self::keys($expression) as $key) {
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
     * empty rather than throwing: the validator refuses to save an expression
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
}
