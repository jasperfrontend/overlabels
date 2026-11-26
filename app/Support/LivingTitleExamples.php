<?php

namespace App\Support;

/**
 * The Living Title examples, read from the help page's "## Examples" section
 * (resources/help/pages/living-title.md), so the settings page offers them as
 * one-click inserts and the help page stays the one place they are written.
 * A new `### Name` + fenced block there is a new insert here, nothing else.
 *
 * Each example: its `###` heading, the first fenced block under it (the
 * template), and the first paragraph after the block as plain text.
 */
class LivingTitleExamples
{
    public const string SOURCE = 'help/pages/living-title.md';

    /** @return list<array{name: string, template: string, description: string}> */
    public static function all(): array
    {
        $markdown = @file_get_contents(resource_path(self::SOURCE));
        if ($markdown === false) {
            return [];
        }

        $markdown = str_replace("\r\n", "\n", $markdown);
        if (! preg_match('/^## Examples\n(.*?)(?=^## )/ms', $markdown, $section)) {
            return [];
        }

        $examples = [];
        foreach (preg_split('/^### /m', $section[1]) as $i => $chunk) {
            if ($i === 0) {
                continue;
            }

            [$name, $rest] = array_pad(explode("\n", $chunk, 2), 2, '');
            if (! preg_match('/^```[^\n]*\n(.*?)\n```\n?(.*)$/ms', $rest, $parts)) {
                continue;
            }

            $examples[] = [
                'name' => trim($name),
                'template' => trim($parts[1]),
                'description' => self::plain(self::firstParagraph($parts[2])),
            ];
        }

        return $examples;
    }

    private static function firstParagraph(string $text): string
    {
        foreach (preg_split('/\n\s*\n/', trim($text)) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph !== '' && ! str_starts_with($paragraph, '>')) {
                return preg_replace('/\s*\n\s*/', ' ', $paragraph) ?? $paragraph;
            }
        }

        return '';
    }

    /** Markdown links become their text; inline code keeps its text. */
    private static function plain(string $text): string
    {
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;

        return str_replace('`', '', $text);
    }
}
