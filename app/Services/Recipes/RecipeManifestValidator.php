<?php

namespace App\Services\Recipes;

use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use RuntimeException;

/**
 * Validates Recipe manifests against the JSON Schema in
 * resources/recipes/recipe-manifest.schema.json plus a small
 * pass of semantic checks that JSON Schema can't express
 * (cross-references between primitives and triggers).
 *
 * Returns a flat list of {pointer, message} errors so callers
 * can render them next to the offending field without walking
 * a nested error tree.
 */
class RecipeManifestValidator
{
    public function __construct(
        private readonly ?string $schemaPath = null,
    ) {}

    /**
     * Validate a manifest given as a decoded array or JSON string.
     *
     * @return array{valid: bool, errors: list<array{pointer: string, message: string}>}
     *
     * @throws JsonException
     */
    public function validate(array|string $manifest): array
    {
        $data = is_string($manifest)
            ? json_decode($manifest, false, 512, JSON_THROW_ON_ERROR)
            : json_decode(json_encode($manifest, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);

        $schemaJson = file_get_contents($this->resolveSchemaPath());
        if ($schemaJson === false) {
            throw new RuntimeException('Recipe manifest schema not readable at '.$this->resolveSchemaPath());
        }

        $validator = new Validator;
        $validator->setMaxErrors(50);

        $result = $validator->validate($data, $schemaJson);

        $errors = [];

        if ($result->hasError()) {
            $errors = $this->flattenSchemaErrors($result->error());
        }

        // Even if schema validation failed we still try semantic checks so the
        // user sees both shapes of error in one pass. But only if the input is
        // structurally workable enough to introspect.
        if (is_object($data)) {
            $manifestArray = json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            foreach ($this->semanticErrors($manifestArray) as $err) {
                $errors[] = $err;
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Convenience: validate a manifest stored on disk.
     *
     * @return array{valid: bool, errors: list<array{pointer: string, message: string}>}
     *
     * @throws JsonException
     */
    public function validateFile(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Manifest file not readable at {$path}");
        }

        return $this->validate($contents);
    }

    /**
     * Path on disk to the JSON Schema document. Lives under resources/
     * by default but can be overridden for tests or alternate installs.
     */
    private function resolveSchemaPath(): string
    {
        return $this->schemaPath
            ?? base_path('resources/recipes/recipe-manifest.schema.json');
    }

    /**
     * @return list<array{pointer: string, message: string}>
     */
    private function flattenSchemaErrors(ValidationError $error): array
    {
        $formatter = new ErrorFormatter;
        $flat = $formatter->formatFlat($error, function (ValidationError $e) use ($formatter) {
            return [
                'pointer' => $this->pointerOf($e),
                'message' => $formatter->formatErrorMessage($e),
            ];
        });

        return array_values($flat);
    }

    private function pointerOf(ValidationError $error): string
    {
        $path = $error->data()->fullPath();

        return '/'.implode('/', array_map(static fn ($seg) => (string) $seg, $path));
    }

    /**
     * Cross-reference checks that JSON Schema can't express on its own:
     *   - picker.option_set_ref points at a declared option_set
     *   - control_export.from references a declared picker
     *   - trigger.fires references a declared picker
     *   - manifest-local refs are unique within their list
     *
     * @param  array<string, mixed>  $manifest
     * @return list<array{pointer: string, message: string}>
     */
    private function semanticErrors(array $manifest): array
    {
        $errors = [];

        $optionSets = $manifest['primitives']['option_sets'] ?? [];
        $pickers = $manifest['primitives']['pickers'] ?? [];

        $optionSetRefs = [];
        foreach ($optionSets as $i => $set) {
            $ref = $set['ref'] ?? null;
            if (! is_string($ref)) {
                continue;
            }
            if (in_array($ref, $optionSetRefs, true)) {
                $errors[] = [
                    'pointer' => "/primitives/option_sets/{$i}/ref",
                    'message' => "Duplicate option_set ref \"{$ref}\".",
                ];
            }
            $optionSetRefs[] = $ref;
        }

        $pickerRefs = [];
        foreach ($pickers as $i => $picker) {
            $ref = $picker['ref'] ?? null;
            if (is_string($ref)) {
                if (in_array($ref, $pickerRefs, true)) {
                    $errors[] = [
                        'pointer' => "/primitives/pickers/{$i}/ref",
                        'message' => "Duplicate picker ref \"{$ref}\".",
                    ];
                }
                $pickerRefs[] = $ref;
            }

            $optionSetRef = $picker['option_set_ref'] ?? null;
            if (is_string($optionSetRef) && ! in_array($optionSetRef, $optionSetRefs, true)) {
                $errors[] = [
                    'pointer' => "/primitives/pickers/{$i}/option_set_ref",
                    'message' => "Picker references unknown option_set \"{$optionSetRef}\".",
                ];
            }
        }

        foreach ($manifest['control_exports'] ?? [] as $i => $export) {
            $from = $export['from'] ?? null;
            if (! is_string($from)) {
                continue;
            }
            // Pattern enforced by JSON Schema: pickers.<ref>.{result|result_index|result_at|running}
            if (preg_match('/^pickers\.([a-z][a-z0-9_]*)\.(?:result|result_index|result_at|running)$/', $from, $m)
                && ! in_array($m[1], $pickerRefs, true)
            ) {
                $errors[] = [
                    'pointer' => "/control_exports/{$i}/from",
                    'message' => "control_export references unknown picker \"{$m[1]}\".",
                ];
            }
        }

        $exportNames = [];
        foreach ($manifest['control_exports'] ?? [] as $i => $export) {
            $name = $export['name'] ?? null;
            if (! is_string($name)) {
                continue;
            }
            if (in_array($name, $exportNames, true)) {
                $errors[] = [
                    'pointer' => "/control_exports/{$i}/name",
                    'message' => "Duplicate control_export name \"{$name}\".",
                ];
            }
            $exportNames[] = $name;
        }

        foreach ($manifest['triggers'] ?? [] as $i => $trigger) {
            $fires = $trigger['fires'] ?? null;
            if (! is_string($fires)) {
                continue;
            }
            if (preg_match('/^pickers\.([a-z][a-z0-9_]*)$/', $fires, $m)
                && ! in_array($m[1], $pickerRefs, true)
            ) {
                $errors[] = [
                    'pointer' => "/triggers/{$i}/fires",
                    'message' => "Trigger references unknown picker \"{$m[1]}\".",
                ];
            }
        }

        return $errors;
    }
}
