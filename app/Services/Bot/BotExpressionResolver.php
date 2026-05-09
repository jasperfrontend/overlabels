<?php

namespace App\Services\Bot;

use App\Models\OverlayControl;
use App\Models\User;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use NumberFormatter;
use Throwable;

/**
 * Resolves a Bot Expression template string into a chat-ready string.
 *
 * SINGLE-PASS BY DESIGN: the regex matches once over the input. Substituted
 * values are never re-scanned for tags. Mirrors the day-one rule from the
 * frontend overlay parser - prevents template injection if a control value
 * happens to contain something tag-shaped.
 *
 * Tag families inside [[[...]]]:
 *   c:<key>            -> own OverlayControl by key
 *   c:<service>:<key>  -> service-managed OverlayControl by broadcastKey
 *   bot:<key>          -> per-invocation context (from_user, args.0, ...)
 *   <bare>             -> Twitch Helix tag from TemplateDataMapperService
 *
 * Pipe formatters (e.g. |number, |round:2, |distance:mi) run after lookup.
 * Unknown tags resolve to empty string per the null-over-placeholder rule.
 */
class BotExpressionResolver
{
    private const string TAG_REGEX = '/\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?]]]/';

    private const int MAX_RESOLVED_LENGTH = 500;

    public function __construct(
        private readonly TwitchApiService $twitch,
        private readonly TemplateDataMapperService $mapper,
    ) {}

    /**
     * Resolve $expression for $user, given $botContext for the invocation.
     *
     * @param  array<string,mixed>  $botContext  Keys: from_user, from_user_login,
     *                                           from_user_id, command, args (string),
     *                                           args.0/1/... (tokens), channel.
     * @param  bool  $dryRun  When true, skips the (possibly expensive) Twitch
     *                        fetch and resolves bare tags to empty. Used by the
     *                        builder UI's live preview and the validator.
     */
    public function resolve(User $user, string $expression, array $botContext = [], bool $dryRun = false): string
    {
        $controls = $this->loadControls($user);
        $twitchTags = $dryRun ? [] : $this->loadTwitchTags($user);
        $locale = (string) ($user->preference('locale', 'en-US'));

        $resolved = preg_replace_callback(
            self::TAG_REGEX,
            function (array $matches) use ($controls, $twitchTags, $botContext, $locale): string {
                $key = $matches[1];
                $pipe = $matches[2] ?? null;
                $value = $this->lookup($key, $controls, $twitchTags, $botContext);
                if ($pipe !== null) {
                    $value = $this->applyFormatter($value, $pipe, $locale);
                }

                return $value;
            },
            $expression
        );

        if (mb_strlen($resolved) > self::MAX_RESOLVED_LENGTH) {
            $resolved = mb_substr($resolved, 0, self::MAX_RESOLVED_LENGTH);
        }

        return $resolved;
    }

    /**
     * @param  array<string,string>  $controls
     * @param  array<string,mixed>  $twitchTags
     * @param  array<string,mixed>  $botContext
     */
    private function lookup(string $key, array $controls, array $twitchTags, array $botContext): string
    {
        if (str_starts_with($key, 'c:')) {
            $controlKey = substr($key, 2);

            return (string) ($controls[$controlKey] ?? '');
        }

        if (str_starts_with($key, 'bot:')) {
            $rest = substr($key, 4);
            // Bot context is flat: keys are literal strings like "from_user",
            // "args", "args.0". Literal lookup avoids data_get's dot traversal,
            // which would try to index into the "args" string.
            $value = $botContext[$rest] ?? null;

            return $value === null ? '' : (string) $value;
        }

        $value = $twitchTags[$key] ?? null;

        return $value === null ? '' : (string) $value;
    }

    /**
     * @return array<string,string> Map of control identifier -> resolved value.
     *                              Service-managed controls use broadcastKey
     *                              (e.g. "kofi:donations_received"); own
     *                              controls use the plain key.
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
     * @return array<string,mixed>
     */
    private function loadTwitchTags(User $user): array
    {
        if (! $user->access_token || ! $user->twitch_id) {
            return [];
        }

        try {
            $data = $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);

            return $this->mapper->mapTwitchDataForTemplates($data, '');
        } catch (Throwable $e) {
            Log::warning('bot_expression.twitch_fetch_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Apply a pipe formatter to a string value. Mirrors the subset of
     * resources/js/utils/formatters.ts that's relevant to chat output.
     * Unknown formatters pass the value through unchanged so a typo in
     * the template doesn't break the whole substitution.
     */
    private function applyFormatter(string $value, string $pipe, string $locale): string
    {
        $pipe = trim($pipe);
        [$name, $args] = array_pad(explode(':', $pipe, 2), 2, '');
        $name = strtolower(trim($name));
        $args = trim($args);

        return match ($name) {
            'round' => $this->formatRound($value, $args),
            'number' => $this->formatNumber($value, $args, $locale),
            'currency' => $this->formatCurrency($value, $args, $locale),
            'date' => $this->formatDate($value, $args),
            'uppercase' => mb_strtoupper($value),
            'lowercase' => mb_strtolower($value),
            'distance' => $this->formatDistance($value, $args),
            'duration' => $this->formatDuration($value, $args),
            default => $value,
        };
    }

    private function formatRound(string $value, string $args): string
    {
        $precision = $args === '' ? 0 : max(0, (int) $args);
        if (! is_numeric($value)) {
            return $value;
        }

        return (string) round((float) $value, $precision);
    }

    private function formatNumber(string $value, string $args, string $locale): string
    {
        if (! is_numeric($value)) {
            return $value;
        }
        $precision = $args === '' ? 0 : max(0, (int) $args);
        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $precision);

        return $formatter->format((float) $value);
    }

    private function formatCurrency(string $value, string $args, string $locale): string
    {
        if (! is_numeric($value)) {
            return $value;
        }
        $currency = $args === '' ? 'USD' : strtoupper($args);
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency((float) $value, $currency);
    }

    /**
     * Best-effort date formatting. Accepts unix seconds or any Carbon-parseable
     * string. Format string follows Carbon's syntax (e.g. "HH:mm", "yyyy-MM-dd").
     */
    private function formatDate(string $value, string $args): string
    {
        if ($value === '') {
            return '';
        }
        try {
            $date = is_numeric($value)
                ? Carbon::createFromTimestamp((int) $value)
                : Carbon::parse($value);
            $format = $args === '' ? 'Y-m-d H:i' : $this->translateDateFormat($args);

            return $date->format($format);
        } catch (Throwable) {
            return $value;
        }
    }

    /**
     * Translate a small subset of the JS-style date tokens (yyyy, MM, dd, HH, mm,
     * ss) into PHP date() tokens. Anything else is passed through verbatim.
     */
    private function translateDateFormat(string $pattern): string
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

    private function formatDistance(string $value, string $args): string
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

    /**
     * Format a number of seconds into a human-readable duration. Default
     * pattern is "hh:mm:ss". Supports d/h/m/s tokens via str_pad.
     */
    private function formatDuration(string $value, string $args): string
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
