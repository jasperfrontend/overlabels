<?php

namespace App\Support;

/**
 * Server-side `[[[if:...]]]` / `[[[elseif:...]]]` / `[[[else]]]` / `[[[endif]]]`
 * for one-line text sinks - today, bot command replies.
 *
 * This is the if-family half of the block engine in
 * resources/js/composables/useConditionalTemplates.ts, with the same
 * semantics on purpose: a depth-aware token scan (nested ifs pair up
 * correctly), first truthy branch wins, `else` always wins, and the chosen
 * branch is rendered recursively so an `if` inside it still works. Tokens and
 * the condition grammar come from the shared spec via Dsl, so a condition
 * that works in an overlay works here character for character.
 *
 * `foreach` is deliberately NOT here. A chat reply is one line, so there is
 * nothing to repeat into; the bot validator refuses it at save time.
 *
 * Blocks are evaluated on the template SOURCE, before any tag substitution,
 * and every value a condition looks at comes from the caller's lookup. Nothing
 * a condition produces is ever rescanned, so the single-pass rule holds: this
 * decides which text survives, and the tag pass then runs exactly once over
 * whatever survived.
 *
 * Malformed input never throws. An `if` with no `endif` leaves the rest of
 * the text as written, and a stray `else`/`elseif`/`endif` is left in place -
 * both mirror the overlay engine, and both are refused at save time anyway
 * (see structuralProblem()).
 */
final class Conditionals
{
    /**
     * @param  callable(string):string  $lookup  Resolves a condition key to its
     *                                           string value ('' for absent).
     */
    public static function render(string $text, callable $lookup, int $depth = 0): string
    {
        if ($depth > Dsl::maxNestingDepth()) {
            return $text;
        }

        $out = '';
        $pos = 0;

        while (($token = self::nextToken($text, $pos)) !== null) {
            if ($token['kind'] !== 'if') {
                // Stray else/elseif/endif, or a foreach: not ours to handle.
                // Emit it as written and carry on.
                $out .= substr($text, $pos, $token['end'] - $pos);
                $pos = $token['end'];

                continue;
            }

            $endif = self::matchingEndif($text, $token);

            if ($endif === null) {
                break;
            }

            $out .= substr($text, $pos, $token['index'] - $pos);

            $inner = substr($text, $token['end'], $endif['index'] - $token['end']);

            foreach (self::splitTopLevel($inner, $token['condition']) as $branch) {
                if ($branch['condition'] === null || self::evaluate($branch['condition'], $lookup)) {
                    $out .= self::render($branch['content'], $lookup, $depth + 1);
                    break;
                }
            }

            $pos = $endif['end'];
        }

        return $out.substr($text, $pos);
    }

    /**
     * The keys every condition in $text reads, in source order, duplicates kept.
     *
     * A comparison contributes its left-hand key; a bare condition IS the key.
     * Anything that does not look like a key (a bare condition with spaces in
     * it, say) is skipped - it would resolve to nothing and evaluate false.
     *
     * @return array<int,string>
     */
    public static function keys(string $text): array
    {
        $keys = [];

        foreach (self::tokens($text) as $token) {
            if (! isset($token['condition'])) {
                continue;
            }

            $key = self::conditionKey($token['condition']);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Check the block structure without evaluating anything.
     *
     * Returns null when every `if` is closed, every `else`/`elseif`/`endif` has
     * an open `if` in front of it, `else` is the last branch, and there is no
     * `foreach`. Otherwise one problem, the first found, as
     * `['problem' => ..., 'snippet' => ...]` where problem is one of
     * `unclosed_if`, `stray`, `after_else`, `foreach` and snippet is the
     * offending token as written.
     *
     * @return array{problem:string,snippet:string}|null
     */
    public static function structuralProblem(string $text): ?array
    {
        /** @var array<int,array{snippet:string,hasElse:bool}> $stack */
        $stack = [];

        foreach (self::tokens($text) as $token) {
            $snippet = $token['snippet'];

            switch ($token['kind']) {
                case 'if':
                    $stack[] = ['snippet' => $snippet, 'hasElse' => false];
                    break;

                case 'elseif':
                case 'else':
                    if ($stack === []) {
                        return ['problem' => 'stray', 'snippet' => $snippet];
                    }

                    $top = array_key_last($stack);

                    if ($stack[$top]['hasElse']) {
                        return ['problem' => 'after_else', 'snippet' => $snippet];
                    }

                    if ($token['kind'] === 'else') {
                        $stack[$top]['hasElse'] = true;
                    }
                    break;

                case 'endif':
                    if ($stack === []) {
                        return ['problem' => 'stray', 'snippet' => $snippet];
                    }

                    array_pop($stack);
                    break;

                default:
                    // foreach / endforeach
                    return ['problem' => 'foreach', 'snippet' => $snippet];
            }
        }

        if ($stack !== []) {
            return ['problem' => 'unclosed_if', 'snippet' => $stack[0]['snippet']];
        }

        return null;
    }

    /**
     * A structural problem as one sentence for the author, naming the token as
     * written. Shared by every save gate (bot commands, alert TTS and chat
     * messages) so the same mistake reads the same everywhere.
     *
     * @param  array{problem:string,snippet:string}  $problem
     */
    public static function describeProblem(array $problem): string
    {
        $snippet = $problem['snippet'];

        return match ($problem['problem']) {
            'unclosed_if' => "'$snippet' has no [[[endif]]] to close it, so I can't tell where the condition ends. Put [[[endif]]] after the text it controls.",
            'stray' => "'$snippet' has no [[[if:...]]] in front of it. Every else, elseif and endif belongs to an if that comes before it.",
            'after_else' => "'$snippet' comes after [[[else]]], and else is always the last branch before [[[endif]]].",
            default => "'$snippet' is for overlays. A message is one line, so loops don't work here - conditions like [[[if:c:wins > 3]]] do.",
        };
    }

    /**
     * Strip every block token, leaving the text between them. What remains is
     * what the tag pass would see if every branch were taken.
     */
    public static function strip(string $text): string
    {
        return preg_replace(Dsl::blockTokenPattern(), '', $text) ?? $text;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Evaluation - same rules as evaluateCondition() in the overlay engine.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  callable(string):string  $lookup
     */
    private static function evaluate(string $condition, callable $lookup): bool
    {
        $condition = trim($condition);

        if (preg_match(Dsl::conditionBodyPattern(), $condition, $m)) {
            $value = (string) $lookup($m[1]);
            $operator = $m[2] === '=' ? '==' : $m[2];
            // Quotes are optional around the right-hand side, like the overlay.
            $compare = preg_replace('/^["\']|["\']$/', '', trim($m[3])) ?? '';

            return self::compare($value, $operator, $compare);
        }

        // Bare key: truthy unless absent, 'false' or '0'.
        $value = (string) $lookup($condition);

        return $value !== '' && $value !== 'false' && $value !== '0';
    }

    private static function compare(string $value, string $operator, string $compare): bool
    {
        $left = self::toNumber($value);
        $right = self::toNumber($compare);

        if ($left !== null && $right !== null) {
            return match ($operator) {
                '>' => $left > $right,
                '<' => $left < $right,
                '>=' => $left >= $right,
                '<=' => $left <= $right,
                '!=' => $left != $right,
                '==' => $left == $right,
                default => false,
            };
        }

        $order = strcmp($value, $compare);

        return match ($operator) {
            '==' => $order === 0,
            '!=' => $order !== 0,
            '>' => $order > 0,
            '<' => $order < 0,
            '>=' => $order >= 0,
            '<=' => $order <= 0,
            default => false,
        };
    }

    /**
     * Numeric reading of a value, or null when it has none. Mirrors JavaScript's
     * Number() for the values chat actually sees: an empty value counts as 0,
     * so `[[[if:c:wins > 0]]]` is false on a counter that does not exist yet
     * rather than falling into a string comparison.
     */
    private static function toNumber(string $value): ?float
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return 0.0;
        }

        return is_numeric($trimmed) ? (float) $trimmed : null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Token scanning - same shape as nextTag() / findMatchingEndif() /
    // splitTopLevel() in the overlay engine.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array{kind:string,condition?:string,index:int,end:int,snippet:string}|null
     */
    private static function nextToken(string $text, int $from): ?array
    {
        if (! preg_match(Dsl::blockTokenPattern(), $text, $m, PREG_OFFSET_CAPTURE, $from)) {
            return null;
        }

        $index = $m[0][1];
        $snippet = $m[0][0];
        $body = $m[1][0];
        $token = ['index' => $index, 'end' => $index + strlen($snippet), 'snippet' => $snippet];

        if (str_starts_with($body, 'if:')) {
            return $token + ['kind' => 'if', 'condition' => trim($m[2][0])];
        }

        if (str_starts_with($body, 'elseif:')) {
            return $token + ['kind' => 'elseif', 'condition' => trim($m[3][0])];
        }

        if (str_starts_with($body, 'foreach:')) {
            return $token + ['kind' => 'foreach'];
        }

        return $token + ['kind' => $body];
    }

    /**
     * @return array<int,array{kind:string,condition?:string,index:int,end:int,snippet:string}>
     */
    private static function tokens(string $text): array
    {
        $tokens = [];
        $pos = 0;

        while (($token = self::nextToken($text, $pos)) !== null) {
            $tokens[] = $token;
            $pos = $token['end'];
        }

        return $tokens;
    }

    /**
     * @param  array{index:int,end:int}  $if
     * @return array{index:int,end:int}|null
     */
    private static function matchingEndif(string $text, array $if): ?array
    {
        $depth = 1;
        $pos = $if['end'];

        while (($token = self::nextToken($text, $pos)) !== null) {
            if ($token['kind'] === 'if') {
                $depth++;
            } elseif ($token['kind'] === 'endif' && --$depth === 0) {
                return $token;
            }

            $pos = $token['end'];
        }

        return null;
    }

    /**
     * Split the text between an `if` and its `endif` into branches, splitting
     * only on `else` / `elseif` at depth 0 so a nested if keeps its own.
     *
     * @return array<int,array{condition:string|null,content:string}>
     */
    private static function splitTopLevel(string $inner, string $firstCondition): array
    {
        $branches = [];
        $depth = 0;
        $cursor = 0;
        $pos = 0;
        $condition = $firstCondition;

        while (($token = self::nextToken($inner, $pos)) !== null) {
            $pos = $token['end'];

            if ($token['kind'] === 'if') {
                $depth++;
            } elseif ($token['kind'] === 'endif') {
                $depth--;
            } elseif ($depth === 0 && ($token['kind'] === 'else' || $token['kind'] === 'elseif')) {
                $branches[] = ['condition' => $condition, 'content' => substr($inner, $cursor, $token['index'] - $cursor)];
                $cursor = $pos;
                $condition = $token['kind'] === 'elseif' ? $token['condition'] : null;
            }
        }

        $branches[] = ['condition' => $condition, 'content' => substr($inner, $cursor)];

        return $branches;
    }

    private static function conditionKey(string $condition): ?string
    {
        $condition = trim($condition);

        if (preg_match(Dsl::conditionBodyPattern(), $condition, $m)) {
            return $m[1];
        }

        $keyStart = Dsl::spec()['lexical']['keyStart'];
        $keyRest = Dsl::spec()['lexical']['keyRest'];

        return preg_match('/^['.$keyStart.']['.$keyRest.']*$/', $condition) ? $condition : null;
    }
}
