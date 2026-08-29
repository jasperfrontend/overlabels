<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Reads an overlay back out of the markdown that OverlayShareService writes.
 *
 * `/overlay/{slug}/public.md` is the export: one file carrying the three
 * source fields, every control the overlay defines, and an alert's behaviour.
 * That document has always ended with "you can also paste the source into a
 * new overlay by hand". This class is the other half of that sentence - the
 * importer parses the same file instead of a person retyping it - so a
 * finished overlay moves between accounts, or between installs, as one file.
 *
 * It parses ONLY what the emitter writes deterministically: front matter,
 * fenced source blocks, the controls table, the "Control detail" list and the
 * alert lines. Prose is never interpreted. Anything the document describes but
 * a copy does not deliver (services, Lists, triggers) is ignored here for the
 * same reason the Copy button ignores it.
 *
 * The contract is pinned by a round-trip test: export a template, parse the
 * export, and every field and every control must come back identical.
 */
final class OverlayMarkdown
{
    private const array TYPES = ['static', 'alert', 'block'];

    /**
     * @return array{
     *     name: string,
     *     type: string,
     *     description: ?string,
     *     head: string,
     *     html: string,
     *     css: string,
     *     alert_sound_url: ?string,
     *     tts_message: ?string,
     *     tts_delay_ms: ?int,
     *     chat_message: ?string,
     *     controls: array<int,array{key:string,label:?string,description:?string,type:string,value:?string,config:?array}>
     * }
     *
     * @throws InvalidArgumentException when the text is not an overlay document
     */
    public static function parse(string $markdown): array
    {
        $md = str_replace("\r\n", "\n", $markdown);

        $front = self::frontMatter($md);

        $type = $front['type'] ?? '';
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('This is not an Overlabels overlay document: the front matter has no overlay type.');
        }

        $name = trim($front['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('This is not an Overlabels overlay document: the front matter has no name.');
        }

        $source = [];
        foreach (['head', 'html', 'css'] as $field) {
            $source[$field] = self::sourceField($md, $field);
        }

        if ($source['head'] === null && $source['html'] === null && $source['css'] === null) {
            throw new InvalidArgumentException('This is not an Overlabels overlay document: it has no Source section.');
        }

        $alert = $type === 'alert' ? self::alert($md) : [];

        return [
            'name' => $name,
            'type' => $type,
            'description' => self::description($md, $name),
            'head' => $source['head'] ?? '',
            'html' => $source['html'] ?? '',
            'css' => $source['css'] ?? '',
            'alert_sound_url' => $alert['sound_url'] ?? null,
            'tts_message' => $alert['tts_message'] ?? null,
            'tts_delay_ms' => $alert['tts_delay_ms'] ?? null,
            'chat_message' => $alert['chat_message'] ?? null,
            'controls' => self::controls($md),
        ];
    }

    /**
     * `key: value` lines between the opening and closing `---`. Values the
     * emitter quoted (anything YAML would misread) are unquoted here; no other
     * YAML is understood, because no other YAML is written.
     *
     * @return array<string,string>
     */
    private static function frontMatter(string $md): array
    {
        if (! preg_match('/\A---\n(.*?)\n---\n/s', $md, $m)) {
            throw new InvalidArgumentException('This is not an Overlabels overlay document: it has no front matter.');
        }

        $fields = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (! preg_match('/^([a-z_]+): (.*)$/', $line, $kv)) {
                continue;
            }
            $value = $kv[2];
            if (preg_match('/^"(.*)"$/s', $value, $q)) {
                $value = strtr($q[1], ['\\\\' => '\\', '\\"' => '"']);
            }
            $fields[$kv[1]] = $value;
        }

        return $fields;
    }

    /**
     * The description is the free text between the H1 and the fixed
     * "An Overlabels **...** by" sentence the emitter always writes next.
     */
    private static function description(string $md, string $name): ?string
    {
        $h1 = "\n# ".$name."\n";
        $start = strpos($md, $h1);
        if ($start === false) {
            return null;
        }
        $start += strlen($h1);

        $end = strpos($md, "\nAn Overlabels **", $start);
        if ($end === false) {
            return null;
        }

        $description = trim(substr($md, $start, $end - $start));

        return $description === '' ? null : $description;
    }

    /**
     * One `### `field`` heading followed by either `Empty.` or a note line and
     * a fenced block. The fence is whatever run of backticks the emitter chose
     * to outrun the content, so it is matched back by reference.
     */
    private static function sourceField(string $md, string $field): ?string
    {
        $pattern = '/^#{2,6} `'.$field.'`\n\n(?:(Empty\.)\n|[^\n]*\n\n(`{3,})[a-z]*\n(.*?)\n\2\n)/ms';

        if (! preg_match($pattern, $md, $m)) {
            return null;
        }

        if (($m[1] ?? '') === 'Empty.') {
            return '';
        }

        return $m[3];
    }

    /**
     * Controls come from two places the emitter keeps in step: the table
     * carries key, type, label and default for every control; the "Control
     * detail" list adds description, expression and behaviour config for the
     * ones that have any.
     *
     * @return array<int,array{key:string,label:?string,description:?string,type:string,value:?string,config:?array}>
     */
    private static function controls(string $md): array
    {
        $controls = [];

        $row = '/^\| `\[\[\[c:([a-z][a-z0-9_]*)\]\]\]` \| ([a-z_]+) \| ((?:[^|\n]|\\\\\|)*?) \| ((?:[^|\n]|\\\\\|)*?) \| (?:yes|no) \|$/m';
        if (preg_match_all($row, $md, $rows, PREG_SET_ORDER)) {
            foreach ($rows as $r) {
                $label = self::unescapeCell($r[3]);
                $value = self::unescapeCell($r[4]);

                $controls[$r[1]] = [
                    'key' => $r[1],
                    'label' => $label === '' ? null : $label,
                    'description' => null,
                    'type' => $r[2],
                    'value' => $value === '' ? null : self::unwrapInlineCode($value),
                    'config' => null,
                ];
            }
        }

        if ($controls === []) {
            return [];
        }

        if (preg_match('/^#{2,6} Control detail\n\n(.*?)\n\n/ms', $md, $section)) {
            $current = null;

            foreach (explode("\n", $section[1]) as $line) {
                if (preg_match('/^- `c:([a-z][a-z0-9_]*)`(?: - (.*))?$/', $line, $m)) {
                    $current = $m[1];
                    if (isset($controls[$current]) && isset($m[2]) && trim($m[2]) !== '') {
                        $controls[$current]['description'] = trim($m[2]);
                    }

                    continue;
                }

                if ($current === null || ! isset($controls[$current])) {
                    continue;
                }

                if (preg_match('/^  - expression: (.*)$/', $line, $m)) {
                    $controls[$current]['config']['expression'] = self::unwrapInlineCode($m[1]);

                    continue;
                }

                if (preg_match('/^  - (.*)$/', $line, $m)) {
                    foreach (self::behaviourPairs($m[1]) as $key => $value) {
                        $controls[$current]['config'][$key] = $value;
                    }
                }
            }
        }

        return array_values($controls);
    }

    /**
     * `min=0, max=NULL, step=1, random=false` back into typed values. Scalars
     * were written with var_export, anything else as JSON.
     *
     * @return array<string,mixed>
     */
    private static function behaviourPairs(string $line): array
    {
        $pairs = [];

        if (! preg_match_all('/([a-z][a-z0-9_]*)=(\'(?:[^\'\\\\]|\\\\.)*\'|[^,]*)(?:, |$)/', $line, $matches, PREG_SET_ORDER)) {
            return $pairs;
        }

        foreach ($matches as $m) {
            $raw = $m[2];
            // null is not scalar, so the emitter json_encodes it as `null`;
            // `NULL` is accepted too in case that ever changes to var_export.
            $pairs[$m[1]] = match (true) {
                $raw === 'null', $raw === 'NULL' => null,
                $raw === 'true' => true,
                $raw === 'false' => false,
                is_numeric($raw) => $raw + 0,
                (bool) preg_match('/^\'(.*)\'$/s', $raw, $s) => strtr($s[1], ['\\\\' => '\\', "\\'" => "'"]),
                default => json_decode($raw, true) ?? $raw,
            };
        }

        return $pairs;
    }

    /**
     * Alert behaviour lines. Each is written in one fixed shape with the
     * user's value in an inline code span (or, for the sound, an autolink).
     *
     * @return array{sound_url:?string,tts_message:?string,tts_delay_ms:?int,chat_message:?string}
     */
    private static function alert(string $md): array
    {
        $out = ['sound_url' => null, 'tts_message' => null, 'tts_delay_ms' => null, 'chat_message' => null];

        if (! preg_match('/^#{2,6} Alert behaviour\n\n(.*?)\n\n/ms', $md, $section)) {
            return $out;
        }

        foreach (explode("\n", $section[1]) as $line) {
            if (preg_match('/^- Plays a sound on fire: <([^>]+)>/', $line, $m)) {
                $out['sound_url'] = $m[1];
            } elseif (preg_match('/^- Speaks via text to speech(?: after a (\d+)ms delay)?: (.*)$/', $line, $m)) {
                $out['tts_delay_ms'] = $m[1] !== '' ? (int) $m[1] : null;
                $out['tts_message'] = self::unwrapInlineCode($m[2]);
            } elseif (preg_match('/^- Posts to Twitch chat via the @overlabels bot: (.*)$/', $line, $m)) {
                $out['chat_message'] = self::unwrapInlineCode($m[1]);
            }
        }

        return $out;
    }

    private static function unescapeCell(string $cell): string
    {
        return trim(str_replace('\|', '|', $cell));
    }

    /**
     * Strip an inline code span: a run of backticks either side, plus the one
     * space of padding the emitter adds when the content itself starts or ends
     * with a backtick (the CommonMark rule).
     */
    private static function unwrapInlineCode(string $span): string
    {
        if (! preg_match('/^(`+)(.*)\1$/s', $span, $m)) {
            return $span;
        }

        $inner = $m[2];
        if (strlen($inner) >= 2 && str_starts_with($inner, ' ') && str_ends_with($inner, ' ')) {
            $inner = substr($inner, 1, -1);
        }

        return $inner;
    }
}
