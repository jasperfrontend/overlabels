<?php

namespace App\Http\Controllers;

use App\Models\BotCommand;
use App\Models\BotExpression;
use App\Models\ListAppender;
use App\Models\OptionSet;
use App\Models\RecipeChatTrigger;
use App\Support\BotChatGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CRUD for ListAppenders, scoped per-list. Each appender is a chat
 * command that appends to a specific OptionSet (List) when invoked.
 * Endpoints are JSON (consumed by the Lists Vue page via axios) rather
 * than Inertia redirects, so adding/editing appenders feels inline
 * instead of full-page-reloady.
 *
 * Command-name collision rules at save time: refuse if the chosen
 * command conflicts with an existing BotCommand (builtin),
 * BotExpression, RecipeChatTrigger, or another ListAppender for this
 * user. The bot's commandMap also resolves ties at runtime
 * (builtin > expression > recipe_trigger > list_append), but failing
 * loudly at save time gives the streamer a clear error.
 */
class ListAppenderController extends Controller
{
    /**
     * GET /dashboard/lists/{list}/appenders
     */
    public function index(Request $request, OptionSet $list): JsonResponse
    {
        $this->authorizeOwnership($request, $list);

        $appenders = ListAppender::where('target_list_id', $list->id)
            ->orderBy('command')
            ->get()
            ->map(fn (ListAppender $a) => $this->serialize($a));

        return response()->json(['appenders' => $appenders]);
    }

    /**
     * POST /dashboard/lists/{list}/appenders
     */
    public function store(Request $request, OptionSet $list): JsonResponse
    {
        $this->authorizeOwnership($request, $list);
        $userId = $request->user()->id;

        $validated = $this->validatePayload($request, $userId);

        $appender = ListAppender::create([
            'user_id' => $userId,
            'target_list_id' => $list->id,
            'command' => $validated['command'],
            'permission_level' => $validated['permission_level'],
            'cooldown_seconds' => $validated['cooldown_seconds'],
            'value_template' => $validated['value_template'],
            'args_empty_reply' => $validated['args_empty_reply'] ?? null,
            'dedup_policy' => $validated['dedup_policy'],
            'max_size' => $validated['max_size'] ?? null,
            'enabled' => $validated['enabled'] ?? true,
        ]);

        return response()->json(['appender' => $this->serialize($appender)], 201);
    }

    /**
     * PUT /dashboard/lists/{list}/appenders/{appender}
     */
    public function update(Request $request, OptionSet $list, ListAppender $appender): JsonResponse
    {
        $this->authorizeOwnership($request, $list);
        $this->authorizeAppender($request, $list, $appender);

        $validated = $this->validatePayload($request, $request->user()->id, $appender->id);

        $appender->update([
            'command' => $validated['command'],
            'permission_level' => $validated['permission_level'],
            'cooldown_seconds' => $validated['cooldown_seconds'],
            'value_template' => $validated['value_template'],
            'args_empty_reply' => $validated['args_empty_reply'] ?? null,
            'dedup_policy' => $validated['dedup_policy'],
            'max_size' => $validated['max_size'] ?? null,
            'enabled' => $validated['enabled'] ?? $appender->enabled,
        ]);

        return response()->json(['appender' => $this->serialize($appender->fresh())]);
    }

    /**
     * DELETE /dashboard/lists/{list}/appenders/{appender}
     */
    public function destroy(Request $request, OptionSet $list, ListAppender $appender): JsonResponse
    {
        $this->authorizeOwnership($request, $list);
        $this->authorizeAppender($request, $list, $appender);

        $appender->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, int $userId, ?int $ignoreAppenderId = null): array
    {
        return $request->validate([
            'command' => [
                'required',
                'string',
                'max:30',
                'regex:/^[a-zA-Z0-9_]+$/',
                function ($attribute, $value, $fail) use ($userId, $ignoreAppenderId) {
                    $command = strtolower($value);

                    if (BotCommand::where('user_id', $userId)->where('command', $command)->exists()) {
                        $fail("Command '!{$command}' collides with an existing built-in bot command.");

                        return;
                    }
                    if (BotExpression::where('user_id', $userId)->where('command', $command)->exists()) {
                        $fail("Command '!{$command}' collides with an existing Bot Expression.");

                        return;
                    }
                    if (RecipeChatTrigger::where('user_id', $userId)->where('command', $command)->exists()) {
                        $fail("Command '!{$command}' collides with an existing recipe trigger.");

                        return;
                    }
                    $q = ListAppender::where('user_id', $userId)->where('command', $command);
                    if ($ignoreAppenderId) {
                        $q->where('id', '!=', $ignoreAppenderId);
                    }
                    if ($q->exists()) {
                        $fail("You already have a list append command '!{$command}'.");
                    }
                },
            ],
            'permission_level' => ['required', 'string', 'in:'.implode(',', array_keys(BotChatGate::TIER_ORDER))],
            'cooldown_seconds' => 'required|integer|min:0|max:86400',
            'value_template' => 'required|string|max:500',
            'args_empty_reply' => 'nullable|string|max:500',
            'dedup_policy' => 'required|string|in:'.implode(',', ListAppender::DEDUP_POLICIES),
            'max_size' => 'nullable|integer|min:1|max:10000',
            'enabled' => 'sometimes|boolean',
        ]);
    }

    private function authorizeOwnership(Request $request, OptionSet $list): void
    {
        if ($list->user_id !== $request->user()->id) {
            throw new HttpException(404);
        }
    }

    private function authorizeAppender(Request $request, OptionSet $list, ListAppender $appender): void
    {
        if ($appender->user_id !== $request->user()->id || $appender->target_list_id !== $list->id) {
            throw new HttpException(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ListAppender $a): array
    {
        return [
            'id' => $a->id,
            'target_list_id' => $a->target_list_id,
            'command' => $a->command,
            'permission_level' => $a->permission_level,
            'cooldown_seconds' => $a->cooldown_seconds,
            'value_template' => $a->value_template,
            'args_empty_reply' => $a->args_empty_reply,
            'dedup_policy' => $a->dedup_policy,
            'max_size' => $a->max_size,
            'enabled' => $a->enabled,
            'last_fired_at' => $a->last_fired_at?->timestamp,
        ];
    }
}
