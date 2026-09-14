<?php

namespace App\Services\Recipes;

use RuntimeException;

/**
 * The questions a recipe asks at install, and what their answers do.
 *
 * A recipe is a form, not a tree. Each ingredient is one question with fixed
 * choices and a default, and the answer is written wherever the manifest or
 * an overlay document says {{key}}: the service to connect, the trigger's
 * service, the `c:<service>:` namespace in an overlay's tags. Nothing
 * branches, and every choice resolves to something the installer can pour.
 *
 * The answers are recorded on the instance, so the shipped files plus the
 * answers always reproduce what was installed. That is what lets a later
 * surface tell an edited overlay from an installed one.
 *
 * The placeholder is {{key}} because it cannot occur in valid CSS and cannot
 * sit inside a [[[tag]]]: one left unfilled renders as nothing, never as a
 * wrong tag.
 */
final class RecipeIngredients
{
    public const string PLACEHOLDER = '/\{\{([a-z][a-z0-9_]{0,49})\}\}/';

    /**
     * The ingredients a manifest declares, in the order it asks them.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<array{key: string, question: string, choices: list<array{value: string, label: string}>, default: string}>
     */
    public static function declared(array $manifest): array
    {
        $declared = [];

        foreach ($manifest['ingredients'] ?? [] as $ingredient) {
            $declared[] = [
                'key' => (string) $ingredient['key'],
                'question' => (string) $ingredient['question'],
                'choices' => array_values(array_map(
                    fn (array $choice) => ['value' => (string) $choice['value'], 'label' => (string) $choice['label']],
                    $ingredient['choices'] ?? [],
                )),
                'default' => (string) $ingredient['default'],
            ];
        }

        return $declared;
    }

    /**
     * One answer per declared ingredient: the given one where there is one,
     * the default otherwise. An answer that is not among the choices, or an
     * answer to a question the recipe does not ask, refuses the install.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $given
     * @return array<string, string>
     *
     * @throws RuntimeException
     */
    public static function answers(array $manifest, array $given): array
    {
        $declared = self::declared($manifest);
        $keys = array_column($declared, 'key');

        foreach (array_keys($given) as $key) {
            if (! in_array($key, $keys, true)) {
                throw new RuntimeException("This product does not ask '{$key}'.");
            }
        }

        $answers = [];

        foreach ($declared as $ingredient) {
            $key = $ingredient['key'];

            if (! array_key_exists($key, $given)) {
                $answers[$key] = $ingredient['default'];

                continue;
            }

            $value = $given[$key];
            $values = array_column($ingredient['choices'], 'value');

            if (! is_string($value) || ! in_array($value, $values, true)) {
                $shown = is_scalar($value) ? (string) $value : gettype($value);

                throw new RuntimeException("'{$shown}' is not one of the choices for \"{$ingredient['question']}\".");
            }

            $answers[$key] = $value;
        }

        return $answers;
    }

    /**
     * The manifest with every placeholder replaced by its answer. A key with
     * no answer is left as it stands, which the validator has already refused.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $answers
     * @return array<string, mixed>
     */
    public static function resolve(array $manifest, array $answers): array
    {
        if ($answers === []) {
            return $manifest;
        }

        return self::walk($manifest, $answers);
    }

    /**
     * Text with every placeholder replaced by its answer.
     *
     * @param  array<string, string>  $answers
     */
    public static function fill(string $text, array $answers): string
    {
        if ($answers === [] || ! str_contains($text, '{{')) {
            return $text;
        }

        return (string) preg_replace_callback(
            self::PLACEHOLDER,
            fn (array $m) => array_key_exists($m[1], $answers) ? $answers[$m[1]] : $m[0],
            $text,
        );
    }

    /**
     * Every ingredient key the strings in a value name, in order of first
     * appearance.
     *
     * @return list<string>
     */
    public static function referenced(mixed $value): array
    {
        $keys = [];

        foreach (self::strings($value) as $string) {
            if (preg_match_all(self::PLACEHOLDER, $string, $m)) {
                $keys = array_merge($keys, $m[1]);
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Every answer set the manifest admits: one entry per combination of
     * choices, so a check run over all of them has covered every install
     * the recipe can produce. A manifest with no ingredients admits exactly
     * one, the empty set.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<array<string, string>>
     */
    public static function combinations(array $manifest): array
    {
        $combinations = [[]];

        foreach (self::declared($manifest) as $ingredient) {
            $next = [];
            foreach ($combinations as $combination) {
                foreach ($ingredient['choices'] as $choice) {
                    $next[] = $combination + [$ingredient['key'] => $choice['value']];
                }
            }
            $combinations = $next;
        }

        return $combinations;
    }

    public static function isPlaceholder(mixed $value): bool
    {
        return is_string($value) && preg_match('/^'.trim(self::PLACEHOLDER, '/').'$/', $value) === 1;
    }

    /**
     * Every string leaf in a value, keyed by JSON pointer.
     *
     * @return array<string, string>
     */
    public static function strings(mixed $value, string $pointer = ''): array
    {
        if (is_string($value)) {
            return [$pointer === '' ? '/' : $pointer => $value];
        }

        if (! is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $key => $item) {
            $strings += self::strings($item, $pointer.'/'.$key);
        }

        return $strings;
    }

    /**
     * @param  array<string, string>  $answers
     */
    private static function walk(mixed $value, array $answers): mixed
    {
        if (is_string($value)) {
            return self::fill($value, $answers);
        }

        if (! is_array($value)) {
            return $value;
        }

        $walked = [];
        foreach ($value as $key => $item) {
            $walked[$key] = self::walk($item, $answers);
        }

        return $walked;
    }
}
