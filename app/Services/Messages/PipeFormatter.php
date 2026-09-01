<?php

namespace App\Services\Messages;

use Carbon\Carbon;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use NumberFormatter;
use Throwable;

/**
 * Server-side mirror of the pipe formatters overlay templates run in the
 * browser (resources/js/utils/formatters.ts). Shared across
 * BotCommandResolver and AlertMessageRenderer so chat output and TTS strings
 * format identically to what the overlay shows.
 *
 * formatters.ts IS the contract - it is what viewers see rendered - and this
 * class mirrors it bug-for-bug where it is quirky (see currency()). The one
 * divergence that cannot be mirrored is the date timezone: the browser
 * formats in the streamer's machine-local time, this class in the app
 * timezone, because the server does not know the viewer's clock.
 *
 * Unknown formatters pass the value through unchanged so a typo in a template
 * never breaks the substitution. PipeFormatterParityTest pins the mirror.
 */
class PipeFormatter
{
    /**
     * Ported verbatim from formatters.ts LOCALE_CURRENCY_MAP, symbols and
     * all: the symbols are not valid ISO codes, so the no-args currency path
     * intentionally falls back to a plain two-decimal number for mapped
     * locales, exactly like Intl.NumberFormat throwing into the JS catch.
     *
     * @var array<string, string>
     */
    private const array LOCALE_CURRENCY_MAP = [
        'en-US' => '$',
        'en-GB' => '£',
        'nl-NL' => '€',
        'nl-BE' => '€',
        'de-DE' => '€',
        'fr-FR' => '€',
        'es-ES' => '€',
        'pt-BR' => 'R$',
        'ja-JP' => '¥',
        'ko-KR' => '₩',
    ];

    public static function apply(string $value, string $pipe, string $locale): string
    {
        $pipe = trim($pipe);
        [$name, $args] = array_pad(explode(':', $pipe, 2), 2, '');
        $name = strtolower(trim($name));
        $args = trim($args);

        return match ($name) {
            'round' => self::round($value, $args),
            'number' => self::number($value, $args, $locale),
            'currency' => self::currency($value, $args, $locale),
            'date' => self::date($value, $args, $locale),
            'uppercase' => mb_strtoupper($value),
            'lowercase' => mb_strtolower($value),
            'login' => self::login($value),
            'mention' => self::mention($value),
            'distance' => self::distance($value, $args, $locale),
            'speed' => self::speed($value, $args, $locale),
            'duration' => self::duration($value, $args),
            default => $value,
        };
    }

    /**
     * Mirrors formatters.ts formatRound(): toFixed() semantics, so the
     * result is PADDED to the precision (5|round:2 is "5.00", not "5"), and
     * args that do not start with a number, or are negative, return the
     * value unchanged rather than being coerced to 0.
     */
    private static function round(string $value, string $args): string
    {
        if (! is_numeric($value)) {
            return $value;
        }

        $precision = 0;
        if ($args !== '') {
            // parseInt semantics: leading integer wins, pure junk is NaN.
            if (! preg_match('/^[+-]?\d/', $args)) {
                return $value;
            }
            $precision = (int) $args;
            if ($precision < 0) {
                return $value;
            }
        }

        return number_format((float) $value, $precision, '.', '');
    }

    /**
     * Mirrors formatters.ts formatNumber(): no args (or unparseable /
     * negative args) means the locale's NATURAL formatting - ICU's default
     * of up to three fraction digits, same engine Intl uses - never a forced
     * zero-decimal rounding. A valid precision pins both min and max digits.
     */
    private static function number(string $value, string $args, string $locale): string
    {
        if (! is_numeric($value)) {
            return $value;
        }

        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);

        if ($args !== '' && preg_match('/^[+-]?\d/', $args) && (int) $args >= 0) {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, (int) $args);
        }

        return $formatter->format((float) $value);
    }

    /**
     * Mirrors formatters.ts formatCurrency(), quirk included: with no args
     * the JS side asks LOCALE_CURRENCY_MAP for a default and gets a SYMBOL,
     * which Intl.NumberFormat rejects, landing in the catch that returns a
     * plain toFixed(2). So `|currency` with no code renders "12.34" for a
     * mapped locale and a real USD format for unmapped ones - and this side
     * does the same. Fixing that means fixing the JS map first; until then
     * the mirror is the contract.
     */
    private static function currency(string $value, string $args, string $locale): string
    {
        if (! is_numeric($value)) {
            return $value;
        }

        $currency = $args !== '' ? $args : (self::LOCALE_CURRENCY_MAP[$locale] ?? 'USD');

        // Intl throws on anything that is not a well-formed 3-letter code;
        // the JS catch falls back to a plain two-decimal number.
        if (! preg_match('/^[A-Za-z]{3}$/', $currency)) {
            return number_format((float) $value, 2, '.', '');
        }

        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $result = $formatter->formatCurrency((float) $value, strtoupper($currency));

        return $result === false ? number_format((float) $value, 2, '.', '') : $result;
    }

    /**
     * Mirrors formatters.ts formatDate(): the no-args default and the four
     * named presets are locale-aware via the same ICU machinery Intl uses
     * (skeleton -> best pattern for the locale), and custom patterns replace
     * the yyyy/MM/dd/HH/mm/ss tokens. Times render in the APP timezone -
     * the browser side uses the streamer's machine clock, which the server
     * cannot know; that is the one documented divergence.
     */
    private static function date(string $value, string $args, string $locale): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $date = is_numeric($value)
                ? Carbon::createFromTimestamp((int) $value)
                : Carbon::parse($value);

            // 'j' is ICU for "the hour style this locale prefers", which is
            // what Intl's `hour: 'numeric'` resolves to.
            $skeletons = [
                '' => 'yMMMdjmm',
                'short' => 'MMMdjmm',
                'long' => 'yMMMMEEEEdjmm',
                'date' => 'yMMMd',
                'time' => 'jmmss',
            ];

            if (array_key_exists($args, $skeletons)) {
                $generator = new IntlDatePatternGenerator($locale);
                $pattern = $generator->getBestPattern($skeletons[$args]);
                $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, $pattern);

                $formatted = $formatter->format($date);
                if ($formatted !== false) {
                    return $formatted;
                }
            }

            return $date->format(self::translateDateFormat($args));
        } catch (Throwable) {
            return $value;
        }
    }

    private static function translateDateFormat(string $pattern): string
    {
        // The same six tokens formatters.ts replaces - and only those. A
        // bare 'yy' stays literal there, so it stays literal here.
        $map = [
            'yyyy' => 'Y',
            'MM' => 'm',
            'dd' => 'd',
            'HH' => 'H',
            'mm' => 'i',
            'ss' => 's',
        ];

        return strtr($pattern, $map);
    }

    /**
     * Bare Twitch login: strip leading '@' chars, trim, and lowercase.
     * For URLs like https://twitch.tv/[[[bot:args.0|login]]] where a chatter's
     * "@name" mention would 404. Twitch logins are case-insensitive and their
     * canonical profile URL is lowercase, so @UserName56 -> username56.
     */
    private static function login(string $value): string
    {
        return mb_strtolower(ltrim(trim($value), '@'));
    }

    /**
     * Chat mention: ensure exactly one leading '@', so a chatter who omits it
     * still pings and '@@' collapses to one. Empty stays empty (never a bare
     * '@'). The inverse of login() - use it where you want the ping form.
     */
    private static function mention(string $value): string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? '' : '@'.ltrim($trimmed, '@');
    }

    /**
     * Input is KILOMETERS - the contract formatters.ts documents and the
     * April 2026 changelog announced ("Input assumed km"), and the unit every
     * distance control actually stores (GPS distance/session_distance, the
     * checkin distances). This method shipped assuming meters and divided km
     * by 1000, so every server-side |distance: on a real control spoke a
     * value 1000x too small; the m/ft units it invented existed in neither
     * the JS side nor any documentation and are gone with the meters.
     *
     * Mirrors formatters.ts formatDistance(): no args or unknown unit means
     * km passthrough, mi converts, output is locale-formatted with at most
     * two fraction digits.
     */
    private static function distance(string $value, string $args, string $locale): string
    {
        if (! is_numeric($value)) {
            return $value;
        }
        if ($args === '') {
            return $value;
        }

        $km = (float) $value;
        $converted = strtolower($args) === 'mi' ? $km / 1.609344 : $km;

        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return $formatter->format($converted);
    }

    /**
     * Mirrors formatters.ts formatSpeed(): input is meters per second (the
     * unit GPS stores), kmh multiplies by 3.6, mph converts on top, unknown
     * units fall back to kmh, no args passes the raw value through. Output
     * is locale-formatted with at most one fraction digit.
     */
    private static function speed(string $value, string $args, string $locale): string
    {
        if (! is_numeric($value)) {
            return $value;
        }
        if ($args === '') {
            return $value;
        }

        $kmh = (float) $value * 3.6;
        $converted = strtolower($args) === 'mph' ? $kmh / 1.609344 : $kmh;

        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);

        return $formatter->format($converted);
    }

    /**
     * Mirrors formatters.ts formatDuration(): a negative total keeps its
     * sign, no args picks the auto format (days as "1d 2h 3m", hours as
     * "2:15:07" with the leading unit unpadded, otherwise "5:07"), and a
     * pattern decomposes from its largest present unit down with overflow
     * into the largest one.
     */
    private static function duration(string $value, string $args): string
    {
        if (! is_numeric($value)) {
            return $value;
        }

        $totalSeconds = (int) floor((float) $value);
        $sign = $totalSeconds < 0 ? '-' : '';
        $absSeconds = abs($totalSeconds);

        if ($args === '') {
            return $sign.self::durationAuto($absSeconds);
        }

        return $sign.self::durationPattern($absSeconds, $args);
    }

    private static function durationAuto(int $totalSeconds): string
    {
        $days = intdiv($totalSeconds, 86400);
        $hours = intdiv($totalSeconds % 86400, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        $pad = fn (int $n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT);

        if ($days > 0) {
            $parts = ["{$days}d"];
            if ($hours > 0) {
                $parts[] = "{$hours}h";
            }
            if ($minutes > 0) {
                $parts[] = "{$minutes}m";
            }
            if (count($parts) === 1 && $seconds > 0) {
                $parts[] = "{$seconds}s";
            }

            return implode(' ', $parts);
        }

        if ($hours > 0) {
            return $hours.':'.$pad($minutes).':'.$pad($seconds);
        }

        return $minutes.':'.$pad($seconds);
    }

    private static function durationPattern(int $totalSeconds, string $pattern): string
    {
        $hasDays = str_contains($pattern, 'dd');
        $hasHours = str_contains($pattern, 'hh');
        $hasMinutes = str_contains($pattern, 'mm');
        $hasSeconds = str_contains($pattern, 'ss');

        $remaining = $totalSeconds;
        $days = $hours = $minutes = $seconds = 0;

        if ($hasDays) {
            $days = intdiv($remaining, 86400);
            $remaining %= 86400;
        }
        if ($hasHours) {
            $hours = intdiv($remaining, 3600);
            $remaining %= 3600;
        }
        if ($hasMinutes) {
            $minutes = intdiv($remaining, 60);
            $remaining %= 60;
        }
        if ($hasSeconds) {
            $seconds = $remaining;
        }

        $pad = fn (int $n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT);

        return strtr($pattern, [
            'dd' => $pad($days),
            'hh' => $pad($hours),
            'mm' => $pad($minutes),
            'ss' => $pad($seconds),
        ]);
    }
}
