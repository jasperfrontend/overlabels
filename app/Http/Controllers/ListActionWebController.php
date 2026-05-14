<?php

namespace App\Http\Controllers;

use App\Events\ListUpdated;
use App\Models\ListMetaCommand;
use App\Models\ListSnapshot;
use App\Models\OptionSet;
use App\Services\Lists\ListActionService;
use App\Support\ListItemTimestamps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Dashboard-side web endpoints for the list-actions surface. Same
 * vocabulary as the `!list` meta-command (draw/clear/pop/clone/etc),
 * just invoked via button clicks instead of chat. Backed by the same
 * ListActionService.
 *
 * Also handles snapshot CRUD (list/restore/pin/delete) and the
 * one-per-user ListMetaCommand config (the streamer opts into !list
 * here and picks a custom command name if they want).
 */
class ListActionWebController extends Controller
{
    public function __construct(
        private readonly ListActionService $service,
    ) {}

    /**
     * POST /dashboard/lists/{list}/actions
     * Body: { action: "draw"|"clear"|... , args?: "<remaining args>" }
     *
     * args is the same shape the chat command sends - whatever comes
     * after the action name. For "pop first" the body is
     * {action: "pop", args: "first"}; for "clone new_slug" it's
     * {action: "clone", args: "new_slug"}; for "first 5" it's
     * {action: "first", args: "5"}.
     */
    public function runAction(Request $request, OptionSet $list): JsonResponse
    {
        $this->authorizeOwnership($request, $list);

        $validated = $request->validate([
            'action' => 'required|string|max:30',
            'args' => 'nullable|string|max:500',
        ]);

        // Re-assemble the raw args string the service expects: "<slug> <action> <args>"
        $raw = trim("{$list->slug} {$validated['action']} ".($validated['args'] ?? ''));
        $reply = $this->service->handleInvocation(
            $request->user(),
            $raw,
            $request->user()->display_name ?: $request->user()->name ?: ''
        );

        return response()->json([
            'reply' => $reply,
        ]);
    }

    /**
     * GET /dashboard/lists/{list}/snapshots
     */
    public function listSnapshots(Request $request, OptionSet $list): JsonResponse
    {
        $this->authorizeOwnership($request, $list);

        $snapshots = ListSnapshot::where('list_id', $list->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (ListSnapshot $s) => [
                'id' => $s->id,
                'reason' => $s->reason,
                'items' => $s->items ?? [],
                'item_count' => count($s->items ?? []),
                'pinned' => $s->pinned,
                'created_at' => $s->created_at->timestamp,
            ]);

        return response()->json(['snapshots' => $snapshots]);
    }

    /**
     * POST /dashboard/lists/{list}/snapshots/manual
     * Take a snapshot of the current state on demand (not tied to a
     * destructive action). Useful before manual edits.
     */
    public function manualSnapshot(Request $request, OptionSet $list): JsonResponse
    {
        $this->authorizeOwnership($request, $list);

        $snapshot = $this->service->snapshot($list, ListSnapshot::REASON_MANUAL, $request->user()->id);

        return response()->json([
            'snapshot' => [
                'id' => $snapshot->id,
                'reason' => $snapshot->reason,
                'item_count' => count($snapshot->items ?? []),
                'pinned' => $snapshot->pinned,
                'created_at' => $snapshot->created_at->timestamp,
            ],
        ]);
    }

    /**
     * POST /dashboard/lists/{list}/snapshots/{snapshot}/restore
     * Replaces the current items with the snapshot's items. Creates
     * a before_restore snapshot of the current state first so the
     * restore is itself undoable.
     */
    public function restoreSnapshot(Request $request, OptionSet $list, ListSnapshot $snapshot): JsonResponse
    {
        $this->authorizeOwnership($request, $list);
        $this->authorizeSnapshot($list, $snapshot);

        $this->service->snapshot($list, ListSnapshot::REASON_BEFORE_RESTORE, $request->user()->id);
        // Restored items get fresh timestamps so an old snapshot doesn't
        // immediately get swept by a short entry-TTL. Restoration is
        // semantically equivalent to "add these items again now."
        $restoredItems = $snapshot->items ?? [];
        $list->update([
            'items' => $restoredItems,
            'item_added_at' => ListItemTimestamps::freshFor($restoredItems),
        ]);

        ListUpdated::dispatchFor((string) $request->user()->twitch_id, $list->fresh());

        return response()->json([
            'restored' => true,
            'item_count' => count($list->fresh()->items ?? []),
        ]);
    }

    /**
     * PATCH /dashboard/lists/{list}/snapshots/{snapshot}/pin
     * Toggles pinned. Pinned snapshots survive the 30-day retention sweep.
     */
    public function togglePin(Request $request, OptionSet $list, ListSnapshot $snapshot): JsonResponse
    {
        $this->authorizeOwnership($request, $list);
        $this->authorizeSnapshot($list, $snapshot);

        $snapshot->update(['pinned' => ! $snapshot->pinned]);

        return response()->json(['pinned' => $snapshot->pinned]);
    }

    /**
     * DELETE /dashboard/lists/{list}/snapshots/{snapshot}
     */
    public function deleteSnapshot(Request $request, OptionSet $list, ListSnapshot $snapshot): JsonResponse
    {
        $this->authorizeOwnership($request, $list);
        $this->authorizeSnapshot($list, $snapshot);

        $snapshot->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * GET /dashboard/lists/meta-command
     * Returns the user's !list meta-command config (or null if not opted in).
     */
    public function getMeta(Request $request): JsonResponse
    {
        $meta = ListMetaCommand::where('user_id', $request->user()->id)->first();

        return response()->json([
            'meta' => $meta ? [
                'command' => $meta->command,
                'enabled' => $meta->enabled,
            ] : null,
        ]);
    }

    /**
     * PUT /dashboard/lists/meta-command
     * Body: { command: "list", enabled: true }
     * Creates or updates the user's meta-command config. Refuses on
     * collision with existing builtin / expression / recipe_trigger /
     * list_append command names.
     */
    public function saveMeta(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'command' => [
                'required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/',
                function ($attribute, $value, $fail) use ($userId) {
                    $cmd = strtolower($value);
                    if (\App\Models\BotCommand::where('user_id', $userId)->where('command', $cmd)->exists()) {
                        $fail("'{$cmd}' collides with an existing built-in command.");

                        return;
                    }
                    if (\App\Models\BotExpression::where('user_id', $userId)->where('command', $cmd)->exists()) {
                        $fail("'{$cmd}' collides with an existing Bot Expression.");

                        return;
                    }
                    if (\App\Models\RecipeChatTrigger::where('user_id', $userId)->where('command', $cmd)->exists()) {
                        $fail("'{$cmd}' collides with an existing recipe trigger.");

                        return;
                    }
                    if (\App\Models\ListAppender::where('user_id', $userId)->where('command', $cmd)->exists()) {
                        $fail("'{$cmd}' collides with an existing list append command.");
                    }
                },
            ],
            'enabled' => 'sometimes|boolean',
        ]);

        $meta = ListMetaCommand::updateOrCreate(
            ['user_id' => $userId],
            [
                'command' => $validated['command'],
                'enabled' => $validated['enabled'] ?? true,
            ]
        );

        return response()->json([
            'meta' => [
                'command' => $meta->command,
                'enabled' => $meta->enabled,
            ],
        ]);
    }

    private function authorizeOwnership(Request $request, OptionSet $list): void
    {
        if ($list->user_id !== $request->user()->id) {
            throw new HttpException(404);
        }
    }

    private function authorizeSnapshot(OptionSet $list, ListSnapshot $snapshot): void
    {
        if ($snapshot->list_id !== $list->id) {
            throw new HttpException(404);
        }
    }
}
