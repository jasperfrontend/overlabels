<?php

namespace App\Services\Messages;

use Carbon\Carbon;
use NumberFormatter;
use Throwable;

/**
 * Server-side mirror of the pipe-formatter subset used by overlay templates
 * (resources/js/utils/formatters.ts). Shared across BotCommandResolver and
 * AlertMessageRenderer so chat output and TTS strings format identically.
 *
 * Unknown formatters pass the value through unchanged so a typo in a template
 * never breaks the substitution.
 */
class PipeFormatter
{
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
            'date' => self::date($value, $args),
            'uppercase' => mb_strtoupper($value),
            'lowercase' => mb_strtolower($value),
            'login' => self::login($value),
            'mention' => self::mention($value),
            'distance' => self::distance($value, $args, $locale),
            'duration' => self::duration($value, $args),
            default => $value,
        };
    }

    private static function round(string $value, string $args): string
    {
        if (! is_numeric($value)) {
            return $value;
        }
        $precision = $args === '' ? 0 : max(0, (int) $args);

        return (string) round((float) $value, $precision);
    }

    private static function number(string $value, string $args, string $locale): string
    {
        if (! is_numeric($value)) {
            return $value;
        }
        $precision = $args === '' ? 0 : max(0, (int) $args);
        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);

        return $formatter->format((float) $value);
    }

    private static function currency(string $value, string $args, string $locale): string
    {
        if (! is_numeric($value)) {
            return $value;
        }
        $currency = $args === '' ? 'USD' : strtoupper($args);
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency((float) $value, $currency);
    }

    private static function date(string $value, string $args): string
    {
        if ($value === '') {
            return '';
        }
        try {
            $date = is_numeric($value)
                ? Carbon::createFromTimestamp((int) $value)
                : Carbon::parse($value);
            $format = $args === '' ? 'Y-m-d H:i' : self::translateDateFormat($args);

            return $date->format($format);
        } catch (Throwable) {
            return $value;
        }
    }

    private static function translateDateFormat(string $pattern): string
    {
        $map = [
            'yyyy' => 'Y',
            'yy' => 'y',
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

    private static function duration(string $value, string $args): string
    {
        if (! is_numeric($value)) {
            return $value;
        }
        $totalSeconds = max(0, (int) $value);
        $pattern = $args === '' ? 'hh:mm:ss' : $args;

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
