<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BotAlias;
use App\Models\BotCommand;
use App\Models\BotExpression;
use App\Services\Bot\BotAliasValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotAliasesController extends Controller
{
    public function __construct(
        private readonly BotAliasValidator $validator,
    ) {}

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

    private function validatePayload(Request $request, ?BotAlias $existing = null): array
    {
        return $this->validator->validateAndNormalize($request->user()->id, $request->all(), $existing);
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
