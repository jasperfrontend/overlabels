<?php

namespace App\Services\Recipes;

use App\Models\BotCommand;
use App\Models\BotExpression;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\Picker;
use App\Models\Recipe;
use App\Models\RecipeChatTrigger;
use App\Models\RecipeInstance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Materialises a Recipe manifest into owned database rows for a specific
 * installer. Reads the catalogue row's `manifest` field, creates one
 * RecipeInstance, then walks the primitives + control_exports declared
 * in the manifest to produce per-user OptionSet / Picker / OverlayControl
 * rows wired together via the new recipe_instance_id FK.
 *
 * Triggers from the manifest are intentionally NOT materialised yet -
 * chat-command routing and the dashboard-button endpoint are step 4 work.
 * The installer reads triggers out of the manifest only when stored for
 * later use; today they live on the catalogue row and consumers ignore
 * them.
 */
class RecipeInstaller
{
    public function __construct(
        private readonly RecipeManifestValidator $validator,
    ) {}

    /**
     * Install a Recipe for a User under the given instance slug.
     *
     * @throws InvalidArgumentException when inputs are malformed
     * @throws RuntimeException         when install constraints are violated
     */
    public function install(Recipe $recipe, User $user, string $instanceSlug, ?string $label = null): RecipeInstance
    {
        if (! preg_match(RecipeInstance::SLUG_PATTERN, $instanceSlug)) {
            throw new InvalidArgumentException(
                "Instance slug must match ".RecipeInstance::SLUG_PATTERN
            );
        }

        // Defensive: catalogue insert already validated the manifest, but
        // re-running validation catches any catalogue row hand-edited via
        // tinker, a stale seeded copy, or future migrations that mutated
        // the manifest in place.
        $result = $this->validator->validate($recipe->manifest);
        if (! $result['valid']) {
            throw new RuntimeException(
                "Recipe '{$recipe->slug}' v{$recipe->version} manifest is invalid: ".
                json_encode($result['errors'])
            );
        }

        $duplicate = RecipeInstance::where('user_id', $user->id)
            ->where('recipe_id', $recipe->id)
            ->where('instance_slug', $instanceSlug)
            ->exists();

        if ($duplicate) {
            throw new RuntimeException(
                "Instance slug '{$instanceSlug}' is already in use for recipe '{$recipe->slug}'."
            );
        }

        if ($recipe->max_instances_per_user !== null) {
            $count = RecipeInstance::where('user_id', $user->id)
                ->where('recipe_id', $recipe->id)
                ->count();
            if ($count >= $recipe->max_instances_per_user) {
                throw new RuntimeException(
                    "Per-user install cap reached for recipe '{$recipe->slug}' (max: {$recipe->max_instances_per_user})."
                );
            }
        }

        $this->assertNoChatCommandCollisions($recipe->manifest, $user);

        return DB::transaction(function () use ($recipe, $user, $instanceSlug, $label) {
            $manifest = $recipe->manifest;

            $instance = RecipeInstance::create([
                'recipe_id' => $recipe->id,
                'user_id' => $user->id,
                'instance_slug' => $instanceSlug,
                'label' => $label ?? $manifest['name'],
            ]);

            $primitiveMap = ['option_sets' => [], 'pickers' => []];

            foreach ($manifest['primitives']['option_sets'] as $os) {
                $row = OptionSet::create([
                    'user_id' => $user->id,
                    'recipe_instance_id' => $instance->id,
                    'slug' => $this->primitiveSlug($instanceSlug, $os['ref']),
                    'label' => $os['label'],
                    'items' => $os['items'] ?? [],
                    'min_items' => $os['min_items'] ?? 1,
                    'max_items' => $os['max_items'] ?? null,
                    'user_editable' => $os['user_editable'] ?? true,
                ]);
                $primitiveMap['option_sets'][$os['ref']] = $row->id;
            }

            foreach ($manifest['primitives']['pickers'] as $p) {
                $optionSetId = $primitiveMap['option_sets'][$p['option_set_ref']] ?? null;
                if ($optionSetId === null) {
                    throw new RuntimeException(
                        "Picker '{$p['ref']}' references unknown option_set '{$p['option_set_ref']}'."
                    );
                }
                $row = Picker::create([
                    'user_id' => $user->id,
                    'recipe_instance_id' => $instance->id,
                    'option_set_id' => $optionSetId,
                    'slug' => $this->primitiveSlug($instanceSlug, $p['ref']),
                    'label' => $p['label'],
                    'consume_on_pick' => $p['consume_on_pick'] ?? false,
                    'concurrency' => $p['concurrency'] ?? Picker::CONCURRENCY_REJECT,
                    'user_editable' => $p['user_editable'] ?? false,
                ]);
                $primitiveMap['pickers'][$p['ref']] = $row->id;
            }

            foreach ($manifest['control_exports'] as $export) {
                $field = $this->parseFromField($export['from']);
                $type = match ($field) {
                    'result_at' => 'number',
                    'running' => 'boolean',
                    default => 'text',
                };
                $defaultValue = match ($field) {
                    'result_at', 'running' => '0',
                    default => '',
                };

                OverlayControl::create([
                    'overlay_template_id' => null,
                    'user_id' => $user->id,
                    'recipe_instance_id' => $instance->id,
                    'key' => $export['name'],
                    'label' => $export['name'],
                    'type' => $type,
                    'value' => $defaultValue,
                    'config' => null,
                    'sort_order' => 0,
                    'source' => null,
                    'source_managed' => true,
                ]);
            }

            $instance->update(['primitive_map' => $primitiveMap]);

            foreach ($manifest['triggers'] ?? [] as $trigger) {
                if (($trigger['kind'] ?? null) !== 'chat_command') {
                    // dashboard_button triggers don't need their own row;
                    // they're fired via the web endpoint which looks the
                    // picker up directly off the recipe_instance.
                    continue;
                }

                $pickerRef = $this->parseFiresTarget($trigger['fires'] ?? '');
                $pickerId = $primitiveMap['pickers'][$pickerRef] ?? null;
                if ($pickerId === null) {
                    throw new RuntimeException(
                        "Chat trigger fires unknown picker '{$pickerRef}'."
                    );
                }

                RecipeChatTrigger::create([
                    'recipe_instance_id' => $instance->id,
                    'user_id' => $user->id,
                    'picker_id' => $pickerId,
                    'command' => ltrim((string) $trigger['command'], '!'),
                    'permission_level' => $trigger['permissions'] ?? 'everyone',
                    'cooldown_seconds' => $trigger['cooldown_seconds'] ?? 0,
                    'enabled' => true,
                ]);
            }

            return $instance->fresh(['recipe', 'optionSets', 'pickers', 'overlayControls', 'chatTriggers']);
        });
    }

    /**
     * Walks the manifest's chat_command triggers and refuses the install
     * if any of the command names collide with an existing BotCommand,
     * BotExpression, or RecipeChatTrigger for this user. Resolution at
     * runtime falls back to builtin > expression > recipe_trigger order
     * but enforcing here gives the user a clear error message rather
     * than a silently-unreachable install.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function assertNoChatCommandCollisions(array $manifest, User $user): void
    {
        $commands = [];
        foreach ($manifest['triggers'] ?? [] as $trigger) {
            if (($trigger['kind'] ?? null) !== 'chat_command') {
                continue;
            }
            $cmd = ltrim((string) ($trigger['command'] ?? ''), '!');
            if ($cmd !== '') {
                $commands[] = $cmd;
            }
        }
        if ($commands === []) {
            return;
        }

        $builtinCollision = BotCommand::where('user_id', $user->id)
            ->whereIn('command', $commands)
            ->value('command');
        if ($builtinCollision) {
            throw new RuntimeException(
                "Chat trigger '!{$builtinCollision}' collides with an existing built-in bot command."
            );
        }

        $expressionCollision = BotExpression::where('user_id', $user->id)
            ->whereIn('command', $commands)
            ->value('command');
        if ($expressionCollision) {
            throw new RuntimeException(
                "Chat trigger '!{$expressionCollision}' collides with an existing Bot Expression."
            );
        }

        $triggerCollision = RecipeChatTrigger::where('user_id', $user->id)
            ->whereIn('command', $commands)
            ->value('command');
        if ($triggerCollision) {
            throw new RuntimeException(
                "Chat trigger '!{$triggerCollision}' collides with an existing recipe trigger for this user."
            );
        }
    }

    /**
     * Extract the picker ref from a triggers[].fires path like "pickers.flipper".
     */
    private function parseFiresTarget(string $fires): string
    {
        if (preg_match('/^pickers\.([a-z][a-z0-9_]*)$/', $fires, $m)) {
            return $m[1];
        }

        throw new RuntimeException("Invalid trigger 'fires' path: {$fires}");
    }

    /**
     * Per-user primitive slug = "<instance_slug>_<manifest_ref>".
     * Keeps two installs of the same recipe non-colliding even when
     * the manifest reuses the same internal refs.
     */
    private function primitiveSlug(string $instanceSlug, string $ref): string
    {
        $candidate = "{$instanceSlug}_{$ref}";
        if (strlen($candidate) > 50) {
            throw new RuntimeException(
                "Generated primitive slug exceeds 50 chars: '{$candidate}'. Shorten the instance slug."
            );
        }

        return $candidate;
    }

    /**
     * Extracts the picker-field name from a control_export 'from' path.
     * Schema-validated input shape: pickers.<ref>.{result|result_at|running}.
     */
    private function parseFromField(string $from): string
    {
        if (preg_match('/^pickers\.[a-z][a-z0-9_]*\.(result|result_at|running)$/', $from, $m)) {
            return $m[1];
        }

        throw new RuntimeException("Invalid control_export 'from' path: {$from}");
    }
}
