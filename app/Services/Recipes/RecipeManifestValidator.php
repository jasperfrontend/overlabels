<?php

namespace App\Services\Recipes;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayTemplate;
use App\Services\External\ExternalServiceRegistry;
use App\Support\Dsl;
use App\Support\OverlayMarkdown;
use InvalidArgumentException;
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
     * `$directory` is where the manifest's overlay documents live. With it,
     * the ingredient checks read those documents too: a placeholder in one
     * must name a declared ingredient, an ingredient must be used somewhere,
     * and every choice must leave each document reading controls its service
     * provisions. Without it, only the manifest itself is checked.
     *
     * @return array{valid: bool, errors: list<array{pointer: string, message: string}>}
     *
     * @throws JsonException
     */
    public function validate(array|string $manifest, ?string $directory = null): array
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
            foreach ($this->semanticErrors($manifestArray, $directory) as $err) {
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

        return $this->validate($contents, dirname($path));
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
     *   - ingredients are well-formed, every placeholder names one, and
     *     every choice resolves to something the installer can pour
     *
     * @param  array<string, mixed>  $manifest
     * @return list<array{pointer: string, message: string}>
     */
    private function semanticErrors(array $manifest, ?string $directory): array
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

        return array_merge($errors, $this->installsErrors($manifest), $this->ingredientErrors($manifest, $directory));
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

            // A placeholder is checked once per choice, in ingredientErrors().
            if (RecipeIngredients::isPlaceholder($service)) {
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

    /**
     * The ingredients a manifest asks, and whether every answer it can get
     * installs. Shape first: unique keys, unique choice values, a default
     * that is one of them. Then references, both ways: every {{placeholder}}
     * in the manifest or an overlay document names a declared ingredient, and
     * every declared ingredient is used somewhere, because a question whose
     * answer changes nothing is a lie to the person answering it.
     *
     * Then every combination of choices is resolved and checked the way the
     * install will read it: a placeholder in `installs.integrations` or an
     * alert trigger's `service` must be a registered service with that event
     * type, and every overlay document carrying a placeholder must, once
     * filled, read only `c:<service>:<key>` controls that service provisions.
     * A choice that fails here fails the catalogue read, so it is a red test,
     * never a streamer's install.
     *
     * The document checks need `$directory`; without one only the manifest
     * is read and the "is used" check is skipped, since the use may be in a
     * document this call cannot see.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<array{pointer: string, message: string}>
     */
    private function ingredientErrors(array $manifest, ?string $directory): array
    {
        $errors = [];
        $ingredients = $manifest['ingredients'] ?? [];
        if (! is_array($ingredients)) {
            return [];
        }

        $keys = [];
        foreach ($ingredients as $i => $ingredient) {
            if (! is_array($ingredient)) {
                continue;
            }

            $key = $ingredient['key'] ?? null;
            if (is_string($key)) {
                if (in_array($key, $keys, true)) {
                    $errors[] = ['pointer' => "/ingredients/{$i}/key", 'message' => "Duplicate ingredient key \"{$key}\"."];
                }
                $keys[] = $key;
            }

            $values = [];
            foreach ($ingredient['choices'] ?? [] as $j => $choice) {
                $value = $choice['value'] ?? null;
                if (! is_string($value)) {
                    continue;
                }
                if (in_array($value, $values, true)) {
                    $errors[] = ['pointer' => "/ingredients/{$i}/choices/{$j}/value", 'message' => "Duplicate choice \"{$value}\"."];
                }
                $values[] = $value;
            }

            $default = $ingredient['default'] ?? null;
            if (is_string($default) && ! in_array($default, $values, true)) {
                $errors[] = ['pointer' => "/ingredients/{$i}/default", 'message' => "Default \"{$default}\" is not one of the choices."];
            }
        }

        // References, manifest side. The ingredients section itself is
        // skipped: a question may legitimately mention braces.
        $used = [];
        foreach (RecipeIngredients::strings($manifest) as $pointer => $string) {
            if (str_starts_with($pointer, '/ingredients/')) {
                continue;
            }
            foreach (RecipeIngredients::referenced($string) as $ref) {
                if (! in_array($ref, $keys, true)) {
                    $errors[] = ['pointer' => $pointer, 'message' => "Placeholder {{{$ref}}} names no ingredient."];
                }
                $used[] = $ref;
            }
        }

        // References, document side.
        $documents = $this->overlayDocuments($manifest, $directory);
        foreach ($documents as $i => $document) {
            foreach (RecipeIngredients::referenced($document['text']) as $ref) {
                if (! in_array($ref, $keys, true)) {
                    $errors[] = [
                        'pointer' => "/installs/overlays/{$i}/file",
                        'message' => "{$document['file']} says {{{$ref}}}, which names no ingredient.",
                    ];
                }
                $used[] = $ref;
            }
        }

        if ($directory !== null) {
            foreach ($ingredients as $i => $ingredient) {
                $key = is_array($ingredient) ? ($ingredient['key'] ?? null) : null;
                if (is_string($key) && ! in_array($key, $used, true)) {
                    $errors[] = ['pointer' => "/ingredients/{$i}/key", 'message' => "Ingredient \"{$key}\" is asked but nothing uses its answer."];
                }
            }
        }

        if ($keys === []) {
            return $errors;
        }

        foreach (RecipeIngredients::combinations($manifest) as $answers) {
            $with = 'With '.implode(', ', array_map(fn (string $k, string $v) => "{$k} = {$v}", array_keys($answers), $answers)).': ';
            $resolved = RecipeIngredients::resolve($manifest, $answers);

            foreach ($manifest['installs']['integrations'] ?? [] as $i => $service) {
                if (! RecipeIngredients::isPlaceholder($service)) {
                    continue;
                }
                $chosen = (string) $resolved['installs']['integrations'][$i];
                if (! ExternalServiceRegistry::has($chosen)) {
                    $errors[] = ['pointer' => "/installs/integrations/{$i}", 'message' => $with."unknown external service \"{$chosen}\"."];
                }
            }

            foreach ($manifest['installs']['alert_triggers'] ?? [] as $i => $trigger) {
                $service = $trigger['service'] ?? null;
                $eventType = $trigger['event_type'] ?? null;
                if (! RecipeIngredients::isPlaceholder($service) || ! is_string($eventType)) {
                    continue;
                }
                $chosen = (string) $resolved['installs']['alert_triggers'][$i]['service'];
                if ($chosen === 'twitch') {
                    if (! array_key_exists($eventType, EventTemplateMapping::EVENT_TYPES)) {
                        $errors[] = ['pointer' => "/installs/alert_triggers/{$i}/event_type", 'message' => $with."unknown Twitch event type \"{$eventType}\"."];
                    }
                } elseif (! ExternalServiceRegistry::has($chosen)) {
                    $errors[] = ['pointer' => "/installs/alert_triggers/{$i}/service", 'message' => $with."unknown external service \"{$chosen}\"."];
                } elseif (! array_key_exists($eventType, ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$chosen] ?? [])) {
                    $errors[] = ['pointer' => "/installs/alert_triggers/{$i}/event_type", 'message' => $with."service \"{$chosen}\" has no event type \"{$eventType}\"."];
                }
            }

            foreach ($documents as $i => $document) {
                if (RecipeIngredients::referenced($document['text']) === []) {
                    continue;
                }
                foreach ($this->unprovisionedControlTags(RecipeIngredients::fill($document['text'], $answers)) as $problem) {
                    $errors[] = ['pointer' => "/installs/overlays/{$i}/file", 'message' => $with.$document['file'].' '.$problem];
                }
            }
        }

        return $errors;
    }

    /**
     * The overlay documents next to the manifest that exist, keyed by their
     * index in `installs.overlays`. A missing file is the installer's refusal,
     * not this one's.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<int, array{file: string, text: string}>
     */
    private function overlayDocuments(array $manifest, ?string $directory): array
    {
        if ($directory === null || ! is_dir($directory)) {
            return [];
        }

        $documents = [];
        foreach ($manifest['installs']['overlays'] ?? [] as $i => $overlay) {
            $file = $overlay['file'] ?? null;
            if (! is_string($file)) {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.basename($file);
            if (! is_file($path)) {
                continue;
            }
            $documents[$i] = ['file' => $file, 'text' => (string) file_get_contents($path)];
        }

        return $documents;
    }

    /**
     * Every `c:<service>:<key>` tag a filled document reads that its service
     * does not provision, as one sentence each. Tags are extracted the way
     * the render allowlist extracts them, conditions included, so a control
     * read only inside an `[[[if:...]]]` is checked too. A document that
     * does not parse is one sentence as well: an install would refuse it.
     *
     * @return list<string>
     */
    private function unprovisionedControlTags(string $markdown): array
    {
        try {
            $doc = OverlayMarkdown::parse($markdown);
        } catch (InvalidArgumentException $e) {
            return ['does not parse once filled: '.$e->getMessage()];
        }

        $template = new OverlayTemplate([
            'head' => $doc['head'],
            'html' => $doc['html'],
            'css' => $doc['css'],
            'tts_message' => $doc['tts_message'],
            'chat_message' => $doc['chat_message'],
        ]);

        $problems = [];
        foreach ($template->extractTemplateTags([]) as $tag) {
            $segments = Dsl::segments($tag);
            if (count($segments) < 3 || $segments[0] !== 'c' || ! ExternalServiceRegistry::has($segments[1])) {
                continue;
            }
            $provisioned = array_column(ExternalServiceRegistry::driver($segments[1])->getAutoProvisionedControls(), 'key');
            if (! in_array($segments[2], $provisioned, true)) {
                $problems[] = "reads [[[{$tag}]]], which ".ExternalServiceRegistry::displayName($segments[1]).' does not provision.';
            }
        }

        return $problems;
    }
}
