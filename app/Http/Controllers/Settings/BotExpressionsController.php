<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BotCommand;
use App\Models\BotExpression;
use App\Models\OverlayControl;
use App\Services\Bot\BotExpressionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BotExpressionsController extends Controller
{
    public function __construct(
        private readonly BotExpressionResolver $resolver,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $expressions = BotExpression::where('user_id', $user->id)
            ->orderBy('command')
            ->get()
            ->map(fn (BotExpression $e) => $this->serialize($e))
            ->all();

        return Inertia::render('settings/bot/expressions/Index', [
            'expressions' => $expressions,
            'botEnabled' => (bool) $user->bot_enabled,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('settings/bot/expressions/Edit', [
            'expression' => null,
            'permissionLevels' => BotCommand::PERMISSION_LEVELS,
            'reservedCommands' => array_column(BotCommand::DEFAULTS, 'command'),
            'availableControlKeys' => $this->availableControlKeys($request->user()->id),
        ]);
    }

    public function edit(Request $request, BotExpression $botExpression): Response
    {
        abort_unless($botExpression->user_id === $request->user()->id, 404);

        return Inertia::render('settings/bot/expressions/Edit', [
            'expression' => $this->serialize($botExpression),
            'permissionLevels' => BotCommand::PERMISSION_LEVELS,
            'reservedCommands' => array_column(BotCommand::DEFAULTS, 'command'),
            'availableControlKeys' => $this->availableControlKeys($request->user()->id),
        ]);
    }

    private function serialize(BotExpression $e): array
    {
        return [
            'id' => $e->id,
            'command' => $e->command,
            'permission_level' => $e->permission_level,
            'cooldown_seconds' => $e->cooldown_seconds,
            'expression' => $e->expression,
            'enabled' => $e->enabled,
            'hidden_from_commands' => $e->hidden_from_commands,
            'last_fired_at' => $e->last_fired_at?->toIso8601String(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $user = $request->user();

        BotExpression::create([
            'user_id' => $user->id,
            'command' => $data['command'],
            'permission_level' => $data['permission_level'],
            'cooldown_seconds' => $data['cooldown_seconds'],
            'expression' => $data['expression'],
            'enabled' => $data['enabled'],
            'hidden_from_commands' => $data['hidden_from_commands'],
        ]);

        return redirect()->route('settings.bot.expressions.index');
    }

    public function update(Request $request, BotExpression $botExpression): RedirectResponse
    {
        abort_unless($botExpression->user_id === $request->user()->id, 404);

        $data = $this->validatePayload($request, $botExpression);

        $botExpression->update([
            'command' => $data['command'],
            'permission_level' => $data['permission_level'],
            'cooldown_seconds' => $data['cooldown_seconds'],
            'expression' => $data['expression'],
            'enabled' => $data['enabled'],
            'hidden_from_commands' => $data['hidden_from_commands'],
        ]);

        return redirect()->route('settings.bot.expressions.index');
    }

    public function destroy(Request $request, BotExpression $botExpression): RedirectResponse
    {
        abort_unless($botExpression->user_id === $request->user()->id, 404);

        $botExpression->delete();

        return redirect()->route('settings.bot.expressions.index');
    }

    /**
     * Dry-run resolve. Used by the builder UI to render a live preview as the
     * author types. Does not persist anything; does not hit Twitch (Helix tags
     * resolve to empty so the user sees that gap visually).
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'expression' => 'required|string|max:5000',
        ]);

        $stubContext = [
            'from_user' => 'CoolChatter',
            'from_user_login' => 'coolchatter',
            'from_user_id' => '12345',
            'command' => '!preview',
            'args' => 'sample arg one two',
            'channel' => strtolower($request->user()->twitch_data['login'] ?? 'channel'),
            'args.0' => 'sample',
            'args.1' => 'arg',
            'args.2' => 'one',
            'args.3' => 'two',
        ];

        $resolved = $this->resolver->resolve(
            $request->user(),
            $data['expression'],
            $stubContext,
            dryRun: true,
        );

        return response()->json([
            'resolved' => $resolved,
            'length' => mb_strlen($resolved),
        ]);
    }

    /**
     * @return array<string,mixed> Validated payload plus normalised command (lowercased, no leading !).
     */
    private function validatePayload(Request $request, ?BotExpression $existing = null): array
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'command' => [
                'required',
                'string',
                'max:30',
                'regex:/^!?[a-zA-Z0-9_-]{1,30}$/',
            ],
            'permission_level' => ['required', Rule::in(BotCommand::PERMISSION_LEVELS)],
            'cooldown_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'expression' => ['required', 'string', 'max:2000'],
            'enabled' => ['required', 'boolean'],
            'hidden_from_commands' => ['required', 'boolean'],
        ]);

        $command = strtolower(ltrim($data['command'], '!'));

        // No collision with builtin commands.
        $reserved = array_column(BotCommand::DEFAULTS, 'command');
        if (in_array($command, $reserved, true)) {
            throw ValidationException::withMessages([
                'command' => "'!$command' is a built-in bot command and can't be reused as an expression.",
            ]);
        }

        // No duplicate per user.
        $duplicate = BotExpression::where('user_id', $userId)
            ->where('command', $command)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'command' => "You already have an expression for '!$command'.",
            ]);
        }

        $data['command'] = $command;

        return $data;
    }

    /**
     * @return array<int,string> Sorted list of control identifiers available
     *                           for [[[c:...]]] tags. Service-managed controls
     *                           return their broadcastKey ("kofi:total_received");
     *                           own controls return their plain key.
     */
    private function availableControlKeys(int $userId): array
    {
        return OverlayControl::where('user_id', $userId)
            ->get()
            ->map(fn (OverlayControl $c) => $c->source_managed ? $c->broadcastKey() : $c->key)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
