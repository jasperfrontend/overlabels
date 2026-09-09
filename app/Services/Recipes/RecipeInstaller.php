<?php

namespace App\Services\Recipes;

use App\Models\BotAlias;
use App\Models\BotBuiltin;
use App\Models\BotCommand;
use App\Models\ExternalIntegration;
use App\Models\ListAppender;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\Picker;
use App\Models\Recipe;
use App\Models\RecipeChatTrigger;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Bot\BotAliasValidator;
use App\Services\Bot\BotCommandValidator;
use App\Services\Bot\BotCounterService;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use App\Services\HtmlSanitizationService;
use App\Services\ImageUploadService;
use App\Support\ListItems;
use App\Support\OverlayMarkdown;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Materialises a Recipe manifest into owned database rows for a specific
 * installer. Reads the catalogue row's `manifest` field, creates one
 * RecipeInstance, then walks the primitives + control_exports declared
 * in the manifest to produce per-user OptionSet / Picker / OverlayControl
 * rows wired together via the new recipe_instance_id FK.
 *
 * The manifest's `installs` section is the product half: overlays are
 * created from markdown documents shipped next to the manifest, through
 * the same parser the import button uses, and integrations are connected
 * the way their settings page connects them (row enabled, controls
 * provisioned). Created overlay ids land in `primitive_map.overlays`.
 */
class RecipeInstaller
{
    public function __construct(
        private readonly RecipeManifestValidator $validator,
        private readonly ExternalControlService $controlService,
        private readonly BotAliasValidator $aliasValidator,
        private readonly BotCommandValidator $commandValidator,
        private readonly BotCounterService $counters,
        private readonly ImageUploadService $images,
    ) {}

    /**
     * Install a Recipe for a User under the given instance slug.
     *
     * @throws InvalidArgumentException when inputs are malformed
     * @throws RuntimeException when install constraints are violated
     */
    public function install(Recipe $recipe, User $user, string $instanceSlug, ?string $label = null): RecipeInstance
    {
        if (! preg_match(RecipeInstance::SLUG_PATTERN, $instanceSlug)) {
            throw new InvalidArgumentException(
                'Instance slug must match '.RecipeInstance::SLUG_PATTERN
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

        // Aliases and commands go through the same validators the settings
        // forms use, BEFORE the transaction: a reply the form would refuse
        // refuses the whole install, with nothing created.
        $aliases = $this->validatedAliases($recipe->manifest, $user);
        $commands = $this->validatedCommands($recipe->manifest, $user);

        return DB::transaction(function () use ($recipe, $user, $instanceSlug, $label, $aliases, $commands) {
            $manifest = $recipe->manifest;

            $instance = RecipeInstance::create([
                'recipe_id' => $recipe->id,
                'user_id' => $user->id,
                'instance_slug' => $instanceSlug,
                'label' => $label ?? $manifest['name'],
            ]);

            $primitiveMap = ['option_sets' => [], 'pickers' => []];

            foreach ($manifest['primitives']['option_sets'] ?? [] as $os) {
                // The manifest authors plain string arrays; wrap them into
                // item objects at install time. The manifest schema is
                // unchanged.
                $built = ListItems::freshFromValues($os['items'] ?? [], 1);
                $row = OptionSet::create([
                    'user_id' => $user->id,
                    'recipe_instance_id' => $instance->id,
                    'slug' => $this->primitiveSlug($instanceSlug, $os['ref']),
                    'label' => $os['label'],
                    'items' => $built['items'],
                    'next_item_id' => $built['next_id'],
                    'min_items' => $os['min_items'] ?? 1,
                    'max_items' => $os['max_items'] ?? null,
                    'user_editable' => $os['user_editable'] ?? true,
                ]);
                $primitiveMap['option_sets'][$os['ref']] = $row->id;
            }

            foreach ($manifest['primitives']['pickers'] ?? [] as $p) {
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

            foreach ($manifest['control_exports'] ?? [] as $export) {
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

            foreach ($manifest['installs']['overlays'] ?? [] as $overlay) {
                $template = $this->installOverlay($recipe, $user, $overlay['file']);
                $primitiveMap['overlays'][$overlay['ref']] = $template->id;
            }

            foreach ($manifest['installs']['integrations'] ?? [] as $service) {
                // Whether the install CREATED the connection or found one the
                // streamer already had decides what uninstall may disconnect.
                $primitiveMap['integrations'][$service] = ['created' => $this->connectIntegration($user, $service)];
            }

            foreach ($manifest['installs']['lists'] ?? [] as $list) {
                $row = $this->installList($user, $instance, $list);
                $primitiveMap['lists'][$list['ref']] = $row->id;
            }

            foreach ($manifest['installs']['list_appenders'] ?? [] as $appender) {
                $listId = $primitiveMap['lists'][$appender['list']] ?? null;
                if ($listId === null) {
                    throw new RuntimeException(
                        "List appender '{$appender['command']}' targets unknown list '{$appender['list']}'."
                    );
                }
                $row = $this->installListAppender($user, $listId, $appender);
                $primitiveMap['list_appenders'][ltrim($appender['command'], '!')] = $row->id;
            }

            foreach ($aliases as $alias) {
                $row = BotAlias::create([
                    'user_id' => $user->id,
                    'command' => $alias['command'],
                    'target_template' => $alias['target_template'],
                    'permission_level' => $alias['permission_level'],
                    'cooldown_seconds' => $alias['cooldown_seconds'],
                    'enabled' => true,
                    'hidden' => $alias['hidden'],
                ]);
                $primitiveMap['bot_aliases'][$alias['command']] = $row->id;
            }

            foreach ($commands as $command) {
                $row = BotCommand::create([
                    'user_id' => $user->id,
                    'command' => $command['command'],
                    'permission_level' => $command['permission_level'],
                    'cooldown_seconds' => $command['cooldown_seconds'],
                    'reply' => $command['reply'],
                    'enabled' => true,
                    'hidden' => $command['hidden'],
                ]);
                // Same convenience the settings form does: a counter: tag in
                // the reply names a control, so make it exist now.
                $this->counters->provision($user, $command['reply']);
                $primitiveMap['bot_commands'][$command['command']] = $row->id;
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
     * One overlay from a markdown document next to the manifest, created the
     * way the import button creates one: the same parser, the same sanitizer,
     * the same control rows. The document is repo content, validated by test
     * rather than per request, so a parse failure here is a broken product,
     * not bad user input.
     */
    private function installOverlay(Recipe $recipe, User $user, string $file): OverlayTemplate
    {
        $path = self::directoryFor($recipe->slug).DIRECTORY_SEPARATOR.basename($file);
        $markdown = is_file($path) ? file_get_contents($path) : false;

        if ($markdown === false) {
            throw new RuntimeException("Recipe '{$recipe->slug}' names an overlay file that does not exist: {$file}");
        }

        $doc = OverlayMarkdown::parse($markdown);

        $fields = array_filter(
            HtmlSanitizationService::sanitizeTemplateFields([
                'name' => $doc['name'],
                'description' => $doc['description'],
                'head' => $doc['head'],
                'html' => $doc['html'],
                'css' => $doc['css'],
                'type' => $doc['type'],
                'tts_message' => $doc['tts_message'],
                'chat_message' => $doc['chat_message'],
                'tts_delay_ms' => $doc['tts_delay_ms'],
                'alert_sound_url' => $doc['alert_sound_url'],
            ]),
            fn ($value) => $value !== null,
        );
        $fields['is_public'] = false;

        $template = $user->overlayTemplates()->create($fields);
        $template->template_tags = $template->extractTemplateTags($user->foreachCaps());
        $template->save();

        foreach ($doc['controls'] as $i => $item) {
            // A list_writer points at control and List IDs on the author's
            // account. They mean nothing here, same as in a fork or an import.
            if ($item['type'] === 'list_writer') {
                continue;
            }

            $config = $item['config'];
            $value = $item['value'];

            if ($item['type'] === 'expression') {
                $expression = trim((string) ($config['expression'] ?? ''));
                $config = [
                    'expression' => $expression,
                    'dependencies' => OverlayControl::extractExpressionDependencies($expression),
                ];
                $value = null;
            }

            OverlayControl::createForTemplate($template, $user, [
                'key' => $item['key'],
                'label' => $item['label'],
                'description' => $item['description'],
                'type' => $item['type'],
                'value' => $value,
                'config' => $config,
                'sort_order' => $i,
            ]);
        }

        return $template;
    }

    /**
     * Connect an external service exactly as its settings page would: the
     * integration row enabled, its controls provisioned. Idempotent, and it
     * re-enables a row the streamer had switched off, because the product
     * being installed does not work without it.
     */
    private function connectIntegration(User $user, string $service): bool
    {
        $driver = ExternalServiceRegistry::driver($service);

        $integration = ExternalIntegration::firstOrCreate(
            ['user_id' => $user->id, 'service' => $service],
            ['enabled' => true],
        );

        if (! $integration->enabled) {
            $integration->enabled = true;
            $integration->save();
        }

        $this->controlService->provision($user, $driver);

        return $integration->wasRecentlyCreated;
    }

    /**
     * Everything an uninstall would remove, in the words the confirm dialog
     * uses. Rows the streamer already deleted by hand are not listed, and
     * an integration the install only found (not created) is never listed.
     *
     * @return list<string>
     */
    public function removals(RecipeInstance $instance): array
    {
        $map = $instance->primitive_map ?? [];
        $lines = [];

        foreach (OverlayTemplate::whereIn('id', array_values($map['overlays'] ?? []))->get() as $template) {
            $lines[] = 'The overlay '.$template->name;
        }
        foreach (OptionSet::whereIn('id', array_values($map['lists'] ?? []))->get() as $list) {
            $lines[] = 'The list '.($list->label ?: $list->slug).' and everything in it';
        }
        foreach (ListAppender::whereIn('id', array_values($map['list_appenders'] ?? []))->get() as $appender) {
            $lines[] = 'The !'.$appender->command.' chat command';
        }
        foreach (BotAlias::whereIn('id', array_values($map['bot_aliases'] ?? []))->get() as $alias) {
            $lines[] = 'The !'.$alias->command.' alias';
        }
        foreach (BotCommand::whereIn('id', array_values($map['bot_commands'] ?? []))->get() as $command) {
            $lines[] = 'The !'.$command->command.' chat command';
        }
        foreach ($map['integrations'] ?? [] as $service => $info) {
            if (($info['created'] ?? false) && ExternalIntegration::where('user_id', $instance->user_id)->where('service', $service)->exists()) {
                $lines[] = 'The '.$service.' integration connection and its controls';
            }
        }

        return $lines;
    }

    /**
     * Undo an install: delete every row the ledger in primitive_map names,
     * then the instance, whose cascades take the picker primitives and the
     * chat triggers. Rows the streamer already deleted are skipped. An
     * overlay that sits in a Kit refuses the whole uninstall before anything
     * is touched, because the kit pivot restricts the delete and a half-done
     * uninstall is worse than none.
     *
     * An integration is disconnected only if the install created it. One
     * the streamer had before the install is theirs and stays.
     */
    public function uninstall(RecipeInstance $instance): void
    {
        $map = $instance->primitive_map ?? [];
        $user = $instance->user;

        $overlays = OverlayTemplate::whereIn('id', array_values($map['overlays'] ?? []))->get();
        foreach ($overlays as $template) {
            $kit = $template->kits()->first();
            if ($kit) {
                throw new RuntimeException(
                    "The overlay {$template->name} is in your kit {$kit->title}. Take it out of the kit, then uninstall again."
                );
            }
        }

        DB::transaction(function () use ($map, $user, $overlays, $instance) {
            foreach ($overlays as $template) {
                $screenshotUrl = $template->screenshot_url;
                $template->delete();
                $this->images->deleteByUrl($screenshotUrl);
            }

            BotCommand::whereIn('id', array_values($map['bot_commands'] ?? []))->delete();
            BotAlias::whereIn('id', array_values($map['bot_aliases'] ?? []))->delete();
            ListAppender::whereIn('id', array_values($map['list_appenders'] ?? []))->delete();
            OptionSet::whereIn('id', array_values($map['lists'] ?? []))->delete();

            foreach ($map['integrations'] ?? [] as $service => $info) {
                if (! ($info['created'] ?? false)) {
                    continue;
                }
                $integration = ExternalIntegration::where('user_id', $user->id)->where('service', $service)->first();
                if ($integration) {
                    $this->controlService->deprovision($user, $service);
                    $integration->delete();
                }
            }

            $instance->delete();
        });
    }

    /**
     * One user List, empty, exactly as /dashboard/lists creates one. The slug
     * is the manifest's, not per instance: the overlay reads
     * [[[c:list:<slug>]]] and mods type !list <slug>, so it has to be the
     * name the product documents. A slug the account already uses is a
     * refusal, not a merge - the existing list may hold anything.
     *
     * @param  array{ref: string, slug: string, label?: string}  $list
     */
    private function installList(User $user, RecipeInstance $instance, array $list): OptionSet
    {
        if (OptionSet::where('user_id', $user->id)->where('slug', $list['slug'])->exists()) {
            throw new RuntimeException(
                "You already have a list with the slug '{$list['slug']}'. Rename or delete it, then install again."
            );
        }

        $built = ListItems::freshFromValues([], 1);

        return OptionSet::create([
            'user_id' => $user->id,
            'recipe_instance_id' => $instance->id,
            'slug' => $list['slug'],
            'label' => $list['label'] ?? null,
            'items' => $built['items'],
            'next_item_id' => $built['next_id'],
            'min_items' => 0,
            'max_items' => null,
            'user_editable' => true,
        ]);
    }

    /**
     * One chat command that appends to an installed list, exactly as the
     * list's Appenders panel creates one. Command collisions were refused
     * before the transaction opened, in assertNoChatCommandCollisions().
     *
     * @param  array<string, mixed>  $appender
     */
    private function installListAppender(User $user, int $listId, array $appender): ListAppender
    {
        return ListAppender::create([
            'user_id' => $user->id,
            'target_list_id' => $listId,
            'command' => strtolower(ltrim((string) $appender['command'], '!')),
            'permission_level' => $appender['permissions'] ?? 'everyone',
            'cooldown_seconds' => $appender['cooldown_seconds'] ?? 0,
            'value_template' => $appender['value'] ?? '[[[bot:from_user]]]',
            'dedup_policy' => $appender['dedup_policy'] ?? ListAppender::DEDUP_PER_CHATTER,
            'enabled' => true,
        ]);
    }

    /**
     * The manifest's bot aliases, run through BotAliasValidator exactly as
     * the settings form runs its input, so the same refusals apply: a
     * builtin's name, a command the account already has, a chain, a
     * self-loop, a bad placeholder. The validator's field message becomes
     * the install's refusal.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<array<string, mixed>>
     */
    private function validatedAliases(array $manifest, User $user): array
    {
        $aliases = [];

        foreach ($manifest['installs']['bot_aliases'] ?? [] as $alias) {
            try {
                $aliases[] = $this->aliasValidator->validateAndNormalize($user->id, [
                    'command' => $alias['command'],
                    'target_template' => $alias['target'],
                    'permission_level' => $alias['permissions'] ?? 'moderator',
                    'cooldown_seconds' => $alias['cooldown_seconds'] ?? 0,
                    'enabled' => true,
                    'hidden' => $alias['hidden'] ?? false,
                ]);
            } catch (ValidationException $e) {
                throw new RuntimeException("Alias {$alias['command']}: ".$this->firstMessage($e));
            }
        }

        return $aliases;
    }

    /**
     * The manifest's custom bot commands, through BotCommandValidator: the
     * reply's blocks, tags, rand ranges and counter keys are checked the
     * way the form checks them.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<array<string, mixed>>
     */
    private function validatedCommands(array $manifest, User $user): array
    {
        $commands = [];

        foreach ($manifest['installs']['bot_commands'] ?? [] as $command) {
            try {
                $commands[] = $this->commandValidator->validateAndNormalize($user->id, [
                    'command' => $command['command'],
                    'reply' => $command['reply'],
                    'permission_level' => $command['permissions'] ?? 'everyone',
                    'cooldown_seconds' => $command['cooldown_seconds'] ?? 0,
                    'enabled' => true,
                    'hidden' => $command['hidden'] ?? false,
                ]);
            } catch (ValidationException $e) {
                throw new RuntimeException("Command {$command['command']}: ".$this->firstMessage($e));
            }
        }

        return $commands;
    }

    private function firstMessage(ValidationException $e): string
    {
        foreach ($e->errors() as $messages) {
            foreach ($messages as $message) {
                return (string) $message;
            }
        }

        return $e->getMessage();
    }

    /**
     * Where a recipe's shipped files live. Manifests are repo content under
     * resources/recipes/<slug>/, and the overlay documents sit beside them.
     */
    public static function directoryFor(string $slug): string
    {
        return base_path('resources/recipes/'.$slug);
    }

    /**
     * Walks the manifest's chat_command triggers and refuses the install
     * if any of the command names collide with an existing BotBuiltin,
     * BotCommand, or RecipeChatTrigger for this user. Resolution at
     * runtime falls back to builtin > custom > recipe_trigger order
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
        // List appenders, aliases and custom commands are chat commands too,
        // and all share the one namespace.
        foreach (['list_appenders', 'bot_aliases', 'bot_commands'] as $section) {
            foreach ($manifest['installs'][$section] ?? [] as $entry) {
                $cmd = strtolower(ltrim((string) ($entry['command'] ?? ''), '!'));
                if ($cmd !== '') {
                    $commands[] = $cmd;
                }
            }
        }
        if ($commands === []) {
            return;
        }

        $appenderCollision = ListAppender::where('user_id', $user->id)
            ->whereIn('command', $commands)
            ->value('command');
        if ($appenderCollision) {
            throw new RuntimeException(
                "You already have a list append command '!{$appenderCollision}'. Rename or delete it, then install again."
            );
        }

        $aliasCollision = BotAlias::where('user_id', $user->id)
            ->whereIn('command', $commands)
            ->value('command');
        if ($aliasCollision) {
            throw new RuntimeException(
                "You already have an alias '!{$aliasCollision}'. Rename or delete it, then install again."
            );
        }

        $builtinCollision = BotBuiltin::where('user_id', $user->id)
            ->whereIn('command', $commands)
            ->value('command');
        if ($builtinCollision) {
            throw new RuntimeException(
                "Chat trigger '!{$builtinCollision}' collides with an existing built-in bot command."
            );
        }

        $commandCollision = BotCommand::where('user_id', $user->id)
            ->whereIn('command', $commands)
            ->value('command');
        if ($commandCollision) {
            throw new RuntimeException(
                "Chat trigger '!{$commandCollision}' collides with an existing Bot Command."
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
