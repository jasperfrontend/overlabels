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
    private const TEMPLATE_VERSION = 2;

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

    private function renderOrCached(array $ctx): string
    {
        $hash = hash('sha256', json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'|'.self::TEMPLATE_VERSION);
        $relative = "/og/{$hash}.png";
        $absolute = public_path("og/{$hash}.png");

        if (File::exists($absolute)) {
            return $relative;
        }

        if (! $this->renderToFile($ctx, $absolute)) {
            return self::DEFAULT_IMAGE;
        }

        return $relative;
    }

    private function renderToFile(array $ctx, string $outPath): bool
    {
        $fontDir = resource_path('fonts');
        if (! File::isDirectory($fontDir)) {
            Log::warning('OG render skipped: fonts directory missing', ['fontDir' => $fontDir]);

            return false;
        }

        File::ensureDirectoryExists(dirname($outPath));

        $svg = View::make('og.help-reference', $ctx)->render();

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
