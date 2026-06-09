<?php

namespace App\Services\Expressions;

use Carbon\Carbon;
use NumberFormatter;
use Throwable;

/**
 * Server-side mirror of the pipe-formatter subset used by overlay templates
 * (resources/js/utils/formatters.ts). Shared across BotExpressionResolver and
 * AlertExpressionRenderer so chat output and TTS strings format identically.
 *
 * Unknown formatters pass the value through unchanged so a typo in a template
 * never breaks the substitution.
 */
class ExpressionFormatter
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
            'distance' => self::distance($value, $args),
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
     * Bare Twitch login: strip leading '@' chars and surrounding whitespace.
     * For URLs like https://twitch.tv/[[[bot:args.0|login]]] where a chatter's
     * "@name" mention would 404. Strip-and-trim only - no case or punctuation
     * normalization, so it stays predictable and unopinionated.
     */
    private static function login(string $value): string
    {
        return ltrim(trim($value), '@');
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

    private static function distance(string $value, string $args): string
    {
        if (! is_numeric($value)) {
            return $value;
        }
        $meters = (float) $value;
        $unit = strtolower($args === '' ? 'km' : $args);

        $converted = match ($unit) {
            'km' => $meters / 1000,
            'm' => $meters,
            'mi' => $meters / 1609.344,
            'ft' => $meters * 3.280839895,
            default => $meters,
        };

        return (string) round($converted, 2);
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
