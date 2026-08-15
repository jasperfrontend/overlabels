<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BotBuiltin;
use App\Models\BotCommand;
use App\Models\OverlayControl;
use App\Services\Bot\BotCommandResolver;
use App\Services\Bot\BotCommandValidator;
use App\Services\Bot\BotCounterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class BotCommandsController extends Controller
{
    public function __construct(
        private readonly BotCommandResolver $resolver,
        private readonly BotCommandValidator $validator,
        private readonly BotCounterService $counters,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $commands = BotCommand::where('user_id', $user->id)
            ->orderBy('command')
            ->get()
            ->map(fn (BotCommand $c) => $this->serialize($c))
            ->all();

        return Inertia::render('settings/bot/commands/Index', [
            'commands' => $commands,
            'botEnabled' => (bool) $user->bot_enabled,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('settings/bot/commands/Edit', [
            'command' => null,
            'permissionLevels' => BotBuiltin::PERMISSION_LEVELS,
            'reservedCommands' => array_column(BotBuiltin::DEFAULTS, 'command'),
            'availableControlKeys' => $this->availableControlKeys($request->user()->id),
        ]);
    }

    public function edit(Request $request, BotCommand $botCommand): Response
    {
        abort_unless($botCommand->user_id === $request->user()->id, 404);

        return Inertia::render('settings/bot/commands/Edit', [
            'command' => $this->serialize($botCommand),
            'permissionLevels' => BotBuiltin::PERMISSION_LEVELS,
            'reservedCommands' => array_column(BotBuiltin::DEFAULTS, 'command'),
            'availableControlKeys' => $this->availableControlKeys($request->user()->id),
        ]);
    }

    private function serialize(BotCommand $c): array
    {
        return [
            'id' => $c->id,
            'command' => $c->command,
            'permission_level' => $c->permission_level,
            'cooldown_seconds' => $c->cooldown_seconds,
            'reply' => $c->reply,
            'enabled' => $c->enabled,
            'hidden' => $c->hidden,
            'last_fired_at' => $c->last_fired_at?->toIso8601String(),
            'destroy_at' => $c->destroy_at?->toIso8601String(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $user = $request->user();

        BotCommand::create([
            'user_id' => $user->id,
            'command' => $data['command'],
            'permission_level' => $data['permission_level'],
            'cooldown_seconds' => $data['cooldown_seconds'],
            'reply' => $data['reply'],
            'enabled' => $data['enabled'],
            'hidden' => $data['hidden'],
            'destroy_at' => $this->destroyAtFromHours($data['destroy_hours'] ?? null),
        ]);

        // Any counter: tag names a counter control; create the ones that don't
        // exist yet so they show up in the UI immediately. Idempotent, and
        // bump() provisions again at fire time, so this is convenience only.
        $this->counters->provision($user, $data['reply']);

        return redirect()->route('settings.bot.commands.index');
    }

    public function update(Request $request, BotCommand $botCommand): RedirectResponse
    {
        abort_unless($botCommand->user_id === $request->user()->id, 404);

        $data = $this->validatePayload($request, $botCommand);

        $botCommand->update([
            'command' => $data['command'],
            'permission_level' => $data['permission_level'],
            'cooldown_seconds' => $data['cooldown_seconds'],
            'reply' => $data['reply'],
            'enabled' => $data['enabled'],
            'hidden' => $data['hidden'],
            'destroy_at' => $this->destroyAtFromHours($data['destroy_hours'] ?? null),
        ]);

        $this->counters->provision($request->user(), $data['reply']);

        return redirect()->route('settings.bot.commands.index');
    }

    public function destroy(Request $request, BotCommand $botCommand): RedirectResponse
    {
        abort_unless($botCommand->user_id === $request->user()->id, 404);

        $botCommand->delete();

        return redirect()->route('settings.bot.commands.index');
    }

    /**
     * Dry-run resolve. Used by the builder UI to render a live preview as the
     * author types. Does not persist anything; does not hit Twitch (Helix tags
     * resolve to empty so the user sees that gap visually).
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'reply' => 'required|string|max:5000',
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
            $data['reply'],
            $stubContext,
            dryRun: true,
        );

        return response()->json([
            'resolved' => $resolved,
            'length' => mb_strlen($resolved),
        ]);
    }

    private function validatePayload(Request $request, ?BotCommand $existing = null): array
    {
        return $this->validator->validateAndNormalize($request->user()->id, $request->all(), $existing);
    }

    /**
     * Turn the form's "hours from now" timer into an absolute destroy_at,
     * mirroring the chat-admin `destroy` option. Null or 0 clears the timer.
     * The countdown always restarts from now, so re-saving a command is a
     * free extend/shorten (or cancel) - identical to re-running it from chat.
     */
    private function destroyAtFromHours(?int $hours): ?Carbon
    {
        return $hours && $hours > 0 ? now()->addHours($hours) : null;
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
