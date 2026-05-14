<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BotAlias;
use App\Models\BotCommand;
use App\Models\BotExpression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BotAliasesController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $aliases = BotAlias::where('user_id', $user->id)
            ->orderBy('command')
            ->get()
            ->map(fn (BotAlias $a) => $this->serialize($a))
            ->all();

        return Inertia::render('settings/bot/aliases/Index', [
            'aliases' => $aliases,
            'botEnabled' => (bool) $user->bot_enabled,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('settings/bot/aliases/Edit', [
            'alias' => null,
            'permissionLevels' => BotCommand::PERMISSION_LEVELS,
            'knownCommands' => $this->knownCommandsForUser($request->user()->id),
        ]);
    }

    public function edit(Request $request, BotAlias $botAlias): Response
    {
        abort_unless($botAlias->user_id === $request->user()->id, 404);

        return Inertia::render('settings/bot/aliases/Edit', [
            'alias' => $this->serialize($botAlias),
            'permissionLevels' => BotCommand::PERMISSION_LEVELS,
            'knownCommands' => $this->knownCommandsForUser($request->user()->id, exceptAliasId: $botAlias->id),
        ]);
    }

    private function serialize(BotAlias $a): array
    {
        return [
            'id' => $a->id,
            'command' => $a->command,
            'target_template' => $a->target_template,
            'permission_level' => $a->permission_level,
            'cooldown_seconds' => $a->cooldown_seconds,
            'enabled' => $a->enabled,
            'hidden_from_commands' => $a->hidden_from_commands,
            'last_fired_at' => $a->last_fired_at?->toIso8601String(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $user = $request->user();

        BotAlias::create([
            'user_id' => $user->id,
            'command' => $data['command'],
            'target_template' => $data['target_template'],
            'permission_level' => $data['permission_level'],
            'cooldown_seconds' => $data['cooldown_seconds'],
            'enabled' => $data['enabled'],
            'hidden_from_commands' => $data['hidden_from_commands'],
        ]);

        return redirect()->route('settings.bot.aliases.index');
    }

    public function update(Request $request, BotAlias $botAlias): RedirectResponse
    {
        abort_unless($botAlias->user_id === $request->user()->id, 404);

        $data = $this->validatePayload($request, $botAlias);

        $botAlias->update([
            'command' => $data['command'],
            'target_template' => $data['target_template'],
            'permission_level' => $data['permission_level'],
            'cooldown_seconds' => $data['cooldown_seconds'],
            'enabled' => $data['enabled'],
            'hidden_from_commands' => $data['hidden_from_commands'],
        ]);

        return redirect()->route('settings.bot.aliases.index');
    }

    public function destroy(Request $request, BotAlias $botAlias): RedirectResponse
    {
        abort_unless($botAlias->user_id === $request->user()->id, 404);

        $botAlias->delete();

        return redirect()->route('settings.bot.aliases.index');
    }

    /**
     * @return array<string,mixed> Validated payload plus normalised command + target_template.
     */
    private function validatePayload(Request $request, ?BotAlias $existing = null): array
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'command' => [
                'required',
                'string',
                'max:30',
                'regex:/^!?[a-zA-Z0-9_-]{1,30}$/',
            ],
            'target_template' => [
                'required',
                'string',
                'max:200',
                // Must start with optional '!' then a command name (letters/digits/_-), then optionally a space + args.
                // Args can contain anything except control chars; placeholders {1}..{9+}/{*} validated separately.
                'regex:/^!?[a-zA-Z0-9_-]{1,30}(\s.*)?$/u',
            ],
            'permission_level' => ['required', Rule::in(BotCommand::PERMISSION_LEVELS)],
            'cooldown_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'enabled' => ['required', 'boolean'],
            'hidden_from_commands' => ['required', 'boolean'],
        ]);

        $command = strtolower(ltrim($data['command'], '!'));
        $target = $this->normalizeTargetTemplate($data['target_template']);

        // No collision with built-in bot commands. Aliases must claim a fresh command name.
        $reservedBuiltins = array_column(BotCommand::DEFAULTS, 'command');
        if (in_array($command, $reservedBuiltins, true)) {
            throw ValidationException::withMessages([
                'command' => "'!$command' is a built-in bot command and can't be used as an alias name.",
            ]);
        }

        // No collision with the user's own expressions (resolution order keeps expressions ahead of aliases).
        $clashesWithExpression = BotExpression::where('user_id', $userId)
            ->where('command', $command)
            ->exists();
        if ($clashesWithExpression) {
            throw ValidationException::withMessages([
                'command' => "You already have an expression for '!$command'. Pick a different alias name.",
            ]);
        }

        // No duplicate alias per user.
        $duplicate = BotAlias::where('user_id', $userId)
            ->where('command', $command)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'command' => "You already have an alias for '!$command'.",
            ]);
        }

        $targetCommand = $this->extractTargetCommand($target);

        // Self-loop: alias that rewrites to itself would infinite-loop the bot if we ever
        // allowed multi-hop; block it at save time so the constraint is explicit.
        if ($targetCommand === $command) {
            throw ValidationException::withMessages([
                'target_template' => "An alias can't point to itself.",
            ]);
        }

        // Chain: pointing at another alias. The bot does one hop only, so chaining would
        // silently drop. Reject so the user gets a clear error instead of broken behavior.
        $chains = BotAlias::where('user_id', $userId)
            ->where('command', $targetCommand)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();
        if ($chains) {
            throw ValidationException::withMessages([
                'target_template' => "'!$targetCommand' is itself an alias. Point this alias at the underlying command instead.",
            ]);
        }

        // Validate placeholder syntax inside target_template: only {1}, {2}, ..., {*} allowed.
        if (preg_match_all('/\{([^}]+)\}/u', $target, $matches)) {
            foreach ($matches[1] as $placeholder) {
                if (! preg_match('/^([1-9][0-9]*|\*)$/', $placeholder)) {
                    throw ValidationException::withMessages([
                        'target_template' => "Invalid placeholder '{{$placeholder}}'. Use {1}, {2}, ... or {*}.",
                    ]);
                }
            }
        }

        $data['command'] = $command;
        $data['target_template'] = $target;

        return $data;
    }

    /**
     * Strip a leading '!' if present, collapse internal whitespace runs to single spaces,
     * trim ends. The leading '!' is implicit when we route on the bot side.
     */
    private function normalizeTargetTemplate(string $raw): string
    {
        $trimmed = trim($raw);
        $trimmed = ltrim($trimmed, '!');

        return preg_replace('/\s+/u', ' ', $trimmed);
    }

    /**
     * First whitespace-separated token of the (already normalized) target template,
     * lowercased. e.g. "increment wins {1}" -> "increment".
     */
    private function extractTargetCommand(string $normalizedTarget): string
    {
        $token = strtok($normalizedTarget, " \t");

        return strtolower($token === false ? '' : $token);
    }

    /**
     * Surface a hint list to the editor UI: builtins + the user's own expressions.
     * Aliases are excluded because chains aren't allowed.
     *
     * @return array<int,array{command:string,kind:string}>
     */
    private function knownCommandsForUser(int $userId, ?int $exceptAliasId = null): array
    {
        $builtins = collect(BotCommand::DEFAULTS)->map(fn ($d) => [
            'command' => $d['command'],
            'kind' => 'builtin',
        ]);

        $expressions = BotExpression::where('user_id', $userId)
            ->orderBy('command')
            ->pluck('command')
            ->map(fn (string $c) => ['command' => $c, 'kind' => 'expression']);

        return $builtins->merge($expressions)
            ->sortBy('command')
            ->values()
            ->all();
    }
}
