<?php

namespace App\Support;

use RuntimeException;

/**
 * PHP reader for the shared DSL spec at resources/dsl/dsl.json.
 *
 * The spec is the single source of truth for the Overlabels template
 * language; resources/js/utils/dsl.ts is its TypeScript counterpart and
 * builds byte-identical patterns from the same file. Before this existed,
 * five hand-maintained regexes matched tags independently and had drifted
 * apart in seven separate ways (documented as D1-D7 in
 * docs/design/overlabels-dsl-spec.md).
 *
 * NOTHING may hand-roll a tag regex. If you need a new shape, add it here
 * so both runtimes get it at once.
 */
final class Dsl
{
    /** @var array<string,mixed>|null */
    private static ?array $spec = null;

    /**
     * The full decoded spec.
     *
     * @return array<string,mixed>
     */
    public static function spec(): array
    {
        if (self::$spec !== null) {
            return self::$spec;
        }

        $path = resource_path('dsl/dsl.json');
        $json = @file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException("DSL spec not readable at {$path}");
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException("DSL spec at {$path} is not valid JSON");
        }

        return self::$spec = $decoded;
    }

    /**
     * Reset the memoised spec. Test-support only.
     */
    public static function flush(): void
    {
        self::$spec = null;
    }

    private static function lex(string $key): string
    {
        $value = self::spec()['lexical'][$key] ?? null;

        if (! is_string($value)) {
            throw new RuntimeException("DSL spec is missing lexical.{$key}");
        }

        return $value;
    }

    /**
     * The canonical tag pattern.
     *
     * Capture groups:
     *   1 - tag key
     *   2 - pipe expression (formatter plus optional :args), optional
     *   3 - `?? default` literal, optional
     *
     * SINGLE-PASS BY DESIGN. Callers run this exactly once over their input
     * and never rescan substituted values. See the extended note in
     * resources/js/utils/tagParser.ts for the adversarial case this defends.
     */
    public static function tagPattern(string $flags = ''): string
    {
        $open = self::lex('open');
        $close = self::lex('close');
        $pipeOp = self::lex('pipeOperator');
        $defOp = self::lex('defaultOperator');
        $keyStart = self::lex('keyStart');
        $keyRest = self::lex('keyRest');
        $pipeArgs = self::lex('pipeArgs');
        $pipeEnd = self::lex('pipeArgsEnd');

        return '/'.$open
            .'(['.$keyStart.']['.$keyRest.']*)'
            .'(?:'.$pipeOp.'(['.$pipeArgs.']*['.$pipeEnd.']))?'
            .'\s*'
            .'(?:'.$defOp.'\s*(.*?))?'
            .$close.'/'.$flags;
    }

    /**
     * Matches a tag key only, ignoring any pipe or default. Used where the
     * clean key is wanted for an allowlist rather than for substitution.
     *
     * Capture group 1 is the key.
     */
    public static function tagKeyPattern(string $flags = ''): string
    {
        $open = self::lex('open');
        $close = self::lex('close');
        $pipeOp = self::lex('pipeOperator');
        $defOp = self::lex('defaultOperator');
        $keyStart = self::lex('keyStart');
        $keyRest = self::lex('keyRest');
        $pipeArgs = self::lex('pipeArgs');
        $pipeEnd = self::lex('pipeArgsEnd');

        return '/'.$open
            .'(['.$keyStart.']['.$keyRest.']*)'
            .'(?:'.$pipeOp.'['.$pipeArgs.']*['.$pipeEnd.'])?'
            .'\s*'
            .'(?:'.$defOp.'\s*.*?)?'
            .$close.'/'.$flags;
    }

    /**
     * Matches `[[[if:...]]]` and `[[[elseif:...]]]`, capturing the condition
     * key in group 1 and the rest of the condition (operator plus value,
     * if present) in group 2.
     */
    public static function conditionPattern(string $flags = ''): string
    {
        $open = self::lex('open');
        $close = self::lex('close');
        $keyStart = self::lex('keyStart');
        $keyRest = self::lex('keyRest');
        $body = self::lex('blockBody');

        return '/'.$open.'(?:if|elseif):'
            .'\s*(['.$keyStart.']['.$keyRest.']*)'
            .'(?:\s*('.$body.'))?'
            .$close.'/'.$flags;
    }

    /**
     * Comparison operators, longest-first so `>=` is preferred over `>`.
     *
     * @return array<int,string>
     */
    public static function comparisonOperators(): array
    {
        /** @var array<int,string> $ops */
        $ops = self::spec()['comparisonOperators'] ?? [];

        return $ops;
    }

    /**
     * Known formatter names.
     *
     * @return array<int,string>
     */
    public static function formatterNames(): array
    {
        /** @var array<string,mixed> $formatters */
        $formatters = self::spec()['formatters'] ?? [];

        return array_keys($formatters);
    }

    public static function isFormatter(string $name): bool
    {
        return array_key_exists($name, self::spec()['formatters'] ?? []);
    }

    public static function maxNestingDepth(): int
    {
        return (int) (self::spec()['limits']['maxNestingDepth'] ?? 10);
    }

    public static function foreachCapMax(): int
    {
        return (int) (self::spec()['limits']['foreachCapMax'] ?? 50);
    }

    /**
     * Split a tag key into its colon-delimited segments.
     *
     * @return array<int,string>
     */
    public static function segments(string $key): array
    {
        return explode(':', $key);
    }
}
