<?php

namespace App\Services\Recipes;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Services\External\ExternalServiceRegistry;
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
     *   - installs.alert_triggers / alert_targets reference a declared
     *     overlay, and name an event type the platform actually has
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
            // Pattern enforced by JSON Schema: pickers.<ref>.{result|result_at|running}
            if (preg_match('/^pickers\.([a-z][a-z0-9_]*)\.(?:result|result_at|running)$/', $from, $m)
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

        return array_merge($errors, $this->installsErrors($manifest));
    }

    /**
     * Cross-reference checks for the `installs` section. Overlay refs are the
     * currency here: alert_triggers and alert_targets both address overlays by
     * ref, so a duplicate ref stopped being a harmless typo the moment it
     * started deciding which template a trigger lands on.
     *
     * Event types are checked against the platform's own catalogues rather
     * than a pattern, because a manifest naming an event that does not exist
     * installs a row nothing will ever match and nothing downstream complains.
     * install() re-validates before it writes, so these are the install's
     * guard as much as the catalogue's.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<array{pointer: string, message: string}>
     */
    private function installsErrors(array $manifest): array
    {
        $errors = [];
        $installs = $manifest['installs'] ?? [];

        $overlayRefs = [];
        foreach ($installs['overlays'] ?? [] as $i => $overlay) {
            $ref = $overlay['ref'] ?? null;
            if (! is_string($ref)) {
                continue;
            }
            if (in_array($ref, $overlayRefs, true)) {
                $errors[] = [
                    'pointer' => "/installs/overlays/{$i}/ref",
                    'message' => "Duplicate overlay ref \"{$ref}\".",
                ];
            }
            $overlayRefs[] = $ref;
        }

        $claimed = [];
        foreach ($installs['alert_triggers'] ?? [] as $i => $trigger) {
            $ref = $trigger['overlay'] ?? null;
            if (is_string($ref) && ! in_array($ref, $overlayRefs, true)) {
                $errors[] = [
                    'pointer' => "/installs/alert_triggers/{$i}/overlay",
                    'message' => "Alert trigger references unknown overlay \"{$ref}\".",
                ];
            }

            $service = $trigger['service'] ?? null;
            $eventType = $trigger['event_type'] ?? null;
            if (! is_string($service) || ! is_string($eventType)) {
                continue;
            }

            if ($service === 'twitch') {
                if (! array_key_exists($eventType, EventTemplateMapping::EVENT_TYPES)) {
                    $errors[] = [
                        'pointer' => "/installs/alert_triggers/{$i}/event_type",
                        'message' => "Unknown Twitch event type \"{$eventType}\".",
                    ];
                }
            } elseif (! ExternalServiceRegistry::has($service)) {
                $errors[] = [
                    'pointer' => "/installs/alert_triggers/{$i}/service",
                    'message' => "Unknown external service \"{$service}\".",
                ];
            } elseif (! array_key_exists($eventType, ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$service] ?? [])) {
                $errors[] = [
                    'pointer' => "/installs/alert_triggers/{$i}/event_type",
                    'message' => "Service \"{$service}\" has no event type \"{$eventType}\".",
                ];
            }

            // Two triggers on one event are two alerts racing for it, and the
            // ladder in resolveForEvent would quietly pick one of them.
            $key = $service.':'.$eventType;
            if (in_array($key, $claimed, true)) {
                $errors[] = [
                    'pointer' => "/installs/alert_triggers/{$i}/event_type",
                    'message' => "Duplicate alert trigger for \"{$key}\".",
                ];
            }
            $claimed[] = $key;
        }

        $targeted = [];
        foreach ($installs['alert_targets'] ?? [] as $i => $target) {
            $alert = $target['alert'] ?? null;
            if (is_string($alert)) {
                if (! in_array($alert, $overlayRefs, true)) {
                    $errors[] = [
                        'pointer' => "/installs/alert_targets/{$i}/alert",
                        'message' => "Alert target references unknown overlay \"{$alert}\".",
                    ];
                }
                // sync() replaces, so a second entry would erase the first.
                if (in_array($alert, $targeted, true)) {
                    $errors[] = [
                        'pointer' => "/installs/alert_targets/{$i}/alert",
                        'message' => "Duplicate alert target for \"{$alert}\".",
                    ];
                }
                $targeted[] = $alert;
            }

            foreach ($target['overlays'] ?? [] as $j => $overlayRef) {
                if (is_string($overlayRef) && ! in_array($overlayRef, $overlayRefs, true)) {
                    $errors[] = [
                        'pointer' => "/installs/alert_targets/{$i}/overlays/{$j}",
                        'message' => "Alert target references unknown overlay \"{$overlayRef}\".",
                    ];
                }
            }
        }

        return $errors;
    }
}
