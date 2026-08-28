<?php

namespace App\Support;

/**
 * Flat `key: value` frontmatter, shared by help pages and update posts.
 *
 * Lifted verbatim out of HelpPage, which had carried the only copy since the
 * help system was written. Updates now need the same shape for their per-row
 * CTA, and a second hand-rolled parser would drift from the first the moment
 * either grew a rule.
 *
 * Deliberately NOT YAML. There is no nesting, no lists, no types, no comment
 * syntax - a line is split on its first colon and everything after it is the
 * value, which is the only reason `url: https://example.com/x` survives. Do
 * not swap in symfony/yaml to "do it properly": every consumer here wants the
 * dumb version, and a real parser would start interpreting `label: yes` as a
 * boolean.
 *
 * NOTHING may hand-roll a second frontmatter split. If you need a new shape,
 * add it here so both callers get it at once.
 */
final class Frontmatter
{
    /**
     * Split flat `key: value` frontmatter from the body.
     *
     * When $requiredKeys is non-empty the block is only accepted if it yields
     * at least one of them, and the document is otherwise handed back whole.
     * That guard exists because an update body is free-form markdown typed
     * into a textarea, where a leading `---` is an ordinary horizontal rule:
     * without it, a post opening with a rule silently loses everything up to
     * the next one, and any line inside that span containing a colon becomes
     * a phantom key. Help pages pass nothing and keep the original behaviour.
     *
     * @param  array<int, string>  $requiredKeys
     * @return array{0:array<string,string>,1:string}
     */
    public static function split(string $raw, array $requiredKeys = []): array
    {
        $raw = ltrim($raw, "\xEF\xBB\xBF");
        $normalized = str_replace("\r\n", "\n", $raw);

        if (! str_starts_with($normalized, "---\n")) {
            return [[], $normalized];
        }

        $end = strpos($normalized, "\n---", 3);

        if ($end === false) {
            return [[], $normalized];
        }

        $block = substr($normalized, 4, $end - 3);
        $body = ltrim(substr($normalized, $end + 4), "\n");
        $meta = self::parse($block);

        if ($requiredKeys !== [] && array_intersect_key($meta, array_flip($requiredKeys)) === []) {
            return [[], $normalized];
        }

        return [$meta, $body];
    }

    /**
     * Turn a frontmatter block into flat `key => value` pairs.
     *
     * Lines without a colon are skipped rather than erroring, the last
     * occurrence of a duplicate key wins, and surrounding quotes are trimmed
     * off the value.
     *
     * @return array<string,string>
     */
    public static function parse(string $block): array
    {
        $meta = [];

        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $meta[trim($key)] = trim(trim(trim($value), '"\''));
        }

        return $meta;
    }
}
