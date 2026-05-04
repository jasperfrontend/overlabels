<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class OgImageService
{
    /**
     * Bump when the SVG Blade template changes shape so existing cached PNGs
     * become unreachable by hash and get regenerated.
     */
    private const TEMPLATE_VERSION = 3;

    private const DEFAULT_IMAGE = '/ogimage.png';

    private const TITLE_MAX = 32;

    private const URL_MAX = 70;

    private const SNIPPET_MAX = 52;

    private const BODY_LINE_MAX = 75;

    private const BODY_LINES_MAX = 4;

    /**
     * Render (or fetch from cache) the OG image for a single reference entry.
     */
    public function urlFor(array $entry, string $canonicalUrl): string
    {
        $ctx = $this->contextForEntry($entry, $canonicalUrl);

        return $this->renderOrCached($ctx);
    }

    /**
     * Render (or fetch from cache) the OG image for a public GPS session map.
     *
     * The polyline is drawn directly into the SVG (resvg renders <polyline>
     * natively) so we get an actual route silhouette in the share preview - no
     * tile fetch, no headless browser. Coords come pre-simplified so even a
     * 4000-ping session stays under a few hundred points in the SVG.
     *
     * Layout: route panel on the left, big stat tiles on the right
     * (Distance / Duration / Max speed / Pings) plus streamer + date.
     *
     * @param  array<string, mixed>  $session  Aggregator shape (started_at, ended_at, distance_km, max_speed_ms, ping_count, ...)
     * @param  array<int, array{0: float, 1: float}>  $coords  [[lng, lat], ...] pre-simplified
     */
    public function urlForGpsSession(
        array $session,
        array $coords,
        string $streamerName,
        string $speedUnit,
        string $locale,
        string $canonicalUrl,
    ): string {
        $ctx = $this->contextForGpsSession($session, $coords, $streamerName, $speedUnit, $locale, $canonicalUrl);

        return $this->renderOrCached($ctx, 'og.gps-session');
    }

    /**
     * Render (or fetch from cache) the OG image for the reference index page.
     */
    public function urlForIndex(int $totalCount, string $canonicalUrl): string
    {
        $ctx = [
            'eyebrow' => 'Reference',
            'title' => 'Overlabels Reference',
            'snippetLabel' => 'Entries',
            'snippetCode' => $totalCount.' tags, events, and loop fields',
            'bodyLines' => $this->wrapBody(
                'Searchable reference for every Overlabels template tag, EventSub event, and foreach loop field.',
            ),
            'url' => $this->truncateUrl($this->urlForFooter($canonicalUrl)),
        ];

        return $this->renderOrCached($ctx);
    }

    /**
     * Build the SVG-template context for a GPS session card.
     *
     * @param  array<string, mixed>  $session
     * @param  array<int, array{0: float, 1: float}>  $coords
     * @return array<string, mixed>
     */
    private function contextForGpsSession(
        array $session,
        array $coords,
        string $streamerName,
        string $speedUnit,
        string $locale,
        string $canonicalUrl,
    ): array {
        // Route panel viewport (in SVG units; matches the rect in the blade).
        $panelX = 60;
        $panelY = 130;
        $panelWidth = 620;
        $panelHeight = 460;
        $padding = 30;

        $points = $this->projectCoordsToSvg($coords, $panelX, $panelY, $panelWidth, $panelHeight, $padding);

        $startedAt = isset($session['started_at']) ? strtotime((string) $session['started_at']) : false;
        $endedAt = isset($session['ended_at']) ? strtotime((string) $session['ended_at']) : false;

        $durationLabel = ($startedAt !== false && $endedAt !== false && $endedAt >= $startedAt)
            ? $this->formatDurationShort($endedAt - $startedAt)
            : '-';

        $dateLabel = $startedAt !== false
            ? $this->formatDateForLocale($startedAt, $locale)
            : '';

        $distanceKm = (float) ($session['distance_km'] ?? 0);
        $distanceLabel = $this->formatDistance($distanceKm, $speedUnit, $locale);

        $maxSpeedMs = $session['max_speed_ms'] ?? null;
        $maxSpeedLabel = $maxSpeedMs !== null
            ? $this->formatSpeed((float) $maxSpeedMs, $speedUnit, $locale)
            : '-';

        $pingCount = (int) ($session['ping_count'] ?? 0);
        $pingLabel = $this->formatNumber($pingCount, $locale);

        return [
            'streamerName' => $this->truncate($streamerName, 20),
            'dateLabel' => $dateLabel,
            'distanceLabel' => $distanceLabel,
            'durationLabel' => $durationLabel,
            'maxSpeedLabel' => $maxSpeedLabel,
            'pingLabel' => $pingLabel,
            'routePoints' => $points['points'],
            'startMarker' => $points['start'],
            'endMarker' => $points['end'],
            'panelX' => $panelX,
            'panelY' => $panelY,
            'panelWidth' => $panelWidth,
            'panelHeight' => $panelHeight,
            'url' => $this->truncateUrl($this->urlForFooter($canonicalUrl)),
        ];
    }

    /**
     * Project [lng, lat] pairs into the SVG viewport.
     *
     * Uses a simple equirectangular projection scaled with an aspect-ratio
     * correction (cos(midLat)) so short routes look right at any latitude. We
     * fit the bounding box into the panel with `meet` semantics, preserving
     * aspect ratio rather than stretching.
     *
     * @param  array<int, array{0: float, 1: float}>  $coords
     * @return array{points: string, start: ?array{x: float, y: float}, end: ?array{x: float, y: float}}
     */
    private function projectCoordsToSvg(
        array $coords,
        float $panelX,
        float $panelY,
        float $panelWidth,
        float $panelHeight,
        float $padding,
    ): array {
        if (count($coords) < 2) {
            return ['points' => '', 'start' => null, 'end' => null];
        }

        $minLat = $maxLat = $coords[0][1];
        $minLng = $maxLng = $coords[0][0];

        foreach ($coords as [$lng, $lat]) {
            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
            $minLng = min($minLng, $lng);
            $maxLng = max($maxLng, $lng);
        }

        $midLat = ($minLat + $maxLat) / 2;
        $latToY = 1.0;
        $lngToX = max(cos(deg2rad($midLat)), 0.0001);

        $rangeX = max(($maxLng - $minLng) * $lngToX, 1e-9);
        $rangeY = max(($maxLat - $minLat) * $latToY, 1e-9);

        $availableW = $panelWidth - $padding * 2;
        $availableH = $panelHeight - $padding * 2;
        $scale = min($availableW / $rangeX, $availableH / $rangeY);

        $renderedW = $rangeX * $scale;
        $renderedH = $rangeY * $scale;
        $offsetX = $panelX + $padding + ($availableW - $renderedW) / 2;
        $offsetY = $panelY + $padding + ($availableH - $renderedH) / 2;

        $project = function (float $lng, float $lat) use (
            $minLng, $maxLat, $lngToX, $scale, $offsetX, $offsetY,
        ): array {
            $x = $offsetX + ($lng - $minLng) * $lngToX * $scale;
            // Latitude grows north -> SVG y grows south, so invert.
            $y = $offsetY + ($maxLat - $lat) * $scale;

            return [round($x, 2), round($y, 2)];
        };

        $pieces = [];
        foreach ($coords as [$lng, $lat]) {
            [$x, $y] = $project($lng, $lat);
            $pieces[] = $x.','.$y;
        }

        [$startX, $startY] = $project($coords[0][0], $coords[0][1]);
        [$endX, $endY] = $project(end($coords)[0], end($coords)[1]);

        return [
            'points' => implode(' ', $pieces),
            'start' => ['x' => $startX, 'y' => $startY],
            'end' => ['x' => $endX, 'y' => $endY],
        ];
    }

    private function formatDurationShort(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }
        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $secs);
        }

        return $secs.'s';
    }

    private function formatDateForLocale(int $unix, string $locale): string
    {
        $tz = new \DateTimeZone(date_default_timezone_get());
        $dt = (new \DateTimeImmutable('@'.$unix))->setTimezone($tz);

        // Avoid intl extension dependency: hand-format with a sensible default.
        // Locale only steers a small DD/MM swap so this is good enough for an
        // OG card.
        return str_starts_with($locale, 'en-US')
            ? $dt->format('M j, Y')
            : $dt->format('j M Y');
    }

    private function formatDistance(float $km, string $unit, string $locale): string
    {
        if ($unit === 'mph') {
            $miles = $km / 1.609344;

            return $this->formatNumber($miles, $locale, 2).' mi';
        }

        return $this->formatNumber($km, $locale, 2).' km';
    }

    private function formatSpeed(float $ms, string $unit, string $locale): string
    {
        $kmh = $ms * 3.6;
        if ($unit === 'mph') {
            return $this->formatNumber($kmh / 1.609344, $locale, 1).' mph';
        }

        return $this->formatNumber($kmh, $locale, 1).' km/h';
    }

    private function formatNumber(float|int $value, string $locale, int $maxFractionDigits = 0): string
    {
        // class_exists keeps this safe on hosts without ext-intl; we just fall
        // back to PHP's number_format with a Western decimal/thousands style.
        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $maxFractionDigits);
            $formatted = $formatter->format($value);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return number_format((float) $value, $maxFractionDigits, '.', ',');
    }

    private function contextForEntry(array $entry, string $canonicalUrl): array
    {
        $snippetLabel = null;
        $snippetCode = null;

        if (str_starts_with($entry['slug'], 'all-')) {
            $count = $this->countEntriesInListBody($entry['body']);
            if ($count !== null) {
                $snippetLabel = 'List';
                $snippetCode = $count.' entries';
            }
        } elseif ($entry['category'] === 'template-tags') {
            $snippetLabel = 'Tag';
            $snippetCode = "[[[{$entry['slug']}]]]";
        } elseif ($entry['category'] === 'foreach-loops') {
            $snippetLabel = 'Loop';
            $snippetCode = "[[[foreach:{$entry['slug']}]]]";
        }

        return [
            'eyebrow' => $entry['categoryLabel'],
            'title' => $this->truncate($entry['title'], self::TITLE_MAX),
            'snippetLabel' => $snippetLabel,
            'snippetCode' => $snippetCode === null ? null : $this->truncate($snippetCode, self::SNIPPET_MAX),
            'bodyLines' => $this->wrapBody($this->bodyExcerpt($entry['body'])),
            'url' => $this->truncateUrl($this->urlForFooter($canonicalUrl)),
        ];
    }

    private function renderOrCached(array $ctx, string $template = 'og.help-reference'): string
    {
        $hashInput = json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'|'.$template.'|'.self::TEMPLATE_VERSION;
        $hash = hash('sha256', $hashInput);
        $relative = "/og/{$hash}.png";
        $absolute = public_path("og/{$hash}.png");

        if (File::exists($absolute)) {
            return $relative;
        }

        if (! $this->renderToFile($ctx, $absolute, $template)) {
            return self::DEFAULT_IMAGE;
        }

        return $relative;
    }

    private function renderToFile(array $ctx, string $outPath, string $template = 'og.help-reference'): bool
    {
        $fontDir = resource_path('fonts');
        if (! File::isDirectory($fontDir)) {
            Log::warning('OG render skipped: fonts directory missing', ['fontDir' => $fontDir]);

            return false;
        }

        File::ensureDirectoryExists(dirname($outPath));

        $svg = View::make($template, $ctx)->render();

        $tmpSvg = tempnam(sys_get_temp_dir(), 'og_').'.svg';
        $tmpPng = $outPath.'.tmp';

        try {
            file_put_contents($tmpSvg, $svg);

            $bin = (string) (env('RESVG_BIN') ?: 'resvg');
            $process = new Process([
                $bin,
                '--use-fonts-dir', $fontDir,
                '--width', '1200',
                $tmpSvg,
                $tmpPng,
            ]);
            $process->setTimeout(15);
            $process->mustRun();

            // Atomic-ish rename so partial writes never become the served file.
            if (! @rename($tmpPng, $outPath)) {
                @copy($tmpPng, $outPath);
                @unlink($tmpPng);
            }

            return File::exists($outPath);
        } catch (ProcessFailedException $e) {
            Log::warning('OG render failed', [
                'error' => $e->getMessage(),
                'output' => $e->getProcess()->getErrorOutput(),
            ]);

            return false;
        } finally {
            @unlink($tmpSvg);
            if (File::exists($tmpPng)) {
                @unlink($tmpPng);
            }
        }
    }

    private function bodyExcerpt(string $body): string
    {
        // Strip the leading "# Title" if present.
        $text = preg_replace('/^#\s+.*$/m', '', $body, 1) ?? $body;
        // Strip fenced code blocks entirely.
        $text = preg_replace('/```[\s\S]*?```/', '', $text) ?? $text;
        // Drop ALL markdown headings so "### Train State" doesn't leak.
        $text = preg_replace('/^#{1,6}\s+.*$/m', '', $text) ?? $text;
        // Strip Obsidian-style links [[slug]] / [[slug|label]] - keep label.
        $text = preg_replace_callback(
            '/\[\[([^\]|\[]+?)(?:\|([^\]]+))?]]/',
            fn ($m) => trim($m[2] ?? $m[1]),
            $text,
        ) ?? $text;
        // Strip [[[tag]]] markers - keep tag bare, no brackets.
        $text = preg_replace('/\[\[\[([^\[\]<>]+?)]]]/', '$1', $text) ?? $text;
        // Strip inline code backticks.
        $text = str_replace('`', '', $text);
        // Strip leading list bullets ("- ", "* ", "1. ") so the prose flows.
        $text = preg_replace('/^\s*(?:[-*]|\d+\.)\s+/m', '', $text) ?? $text;
        // Collapse whitespace.
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return $text;
    }

    /**
     * @return array<int, string>
     */
    private function wrapBody(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $lines = [];
        $words = preg_split('/\s+/', $text) ?: [];
        $current = '';

        foreach ($words as $word) {
            if ($current === '') {
                $current = $word;

                continue;
            }
            if (mb_strlen($current.' '.$word) > self::BODY_LINE_MAX) {
                $lines[] = $current;
                if (count($lines) === self::BODY_LINES_MAX) {
                    return $this->ellipsizeLastLine($lines);
                }
                $current = $word;
            } else {
                $current .= ' '.$word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > self::BODY_LINES_MAX) {
            $lines = array_slice($lines, 0, self::BODY_LINES_MAX);

            return $this->ellipsizeLastLine($lines);
        }

        return $lines;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function ellipsizeLastLine(array $lines): array
    {
        $last = $lines[count($lines) - 1];
        if (mb_strlen($last) > self::BODY_LINE_MAX - 1) {
            $last = rtrim(mb_substr($last, 0, self::BODY_LINE_MAX - 1));
        }
        $lines[count($lines) - 1] = rtrim($last, '.,;:').'…';

        return $lines;
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    private function urlForFooter(string $canonicalUrl): string
    {
        // Strip protocol so it reads like "overlabels.com/help/...".
        return preg_replace('#^https?://#i', '', $canonicalUrl) ?? $canonicalUrl;
    }

    private function truncateUrl(string $url): string
    {
        if (mb_strlen($url) <= self::URL_MAX) {
            return $url;
        }

        return mb_substr($url, 0, self::URL_MAX - 1).'…';
    }

    private function countEntriesInListBody(string $body): ?int
    {
        // List pages typically render as a markdown list - count "- " or "* "
        // line starts. Falls back to null if zero, so we hide the slot.
        $count = preg_match_all('/^[-*]\s+\S/m', $body);
        if ($count === false || $count === 0) {
            return null;
        }

        return $count;
    }
}
