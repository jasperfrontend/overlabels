<?php

namespace App\Http\Controllers;

use App\Events\ListUpdated;
use App\Models\OptionSet;
use App\Services\Lists\ListActionService;
use App\Support\BotChatGate;
use App\Support\ListItemTimestamps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CRUD over the authenticated user's OptionSets, exposed to the frontend
 * as "Lists". Both user-authored (recipe_instance_id IS NULL) and recipe-
 * installed lists surface here. Recipe-installed rows are editable when
 * their recipe declared `user_editable: true`; otherwise the items array
 * is locked but the row stays visible so the streamer knows it exists.
 *
 * Items are stored exactly as the user typed them. We do not dedupe, do
 * not strip blank lines, do not trim whitespace - lists are lists. The
 * only filter applied is NUL-byte removal, which is purely for the JSON
 * encode pipeline's sanity and would never be deliberate user input.
 */
class ListController extends Controller
{
    public function __construct(
        private readonly ListActionService $actions,
    ) {}

    /**
     * Tag-safe slug pattern, matching the rest of the c:* namespace
     * conventions. Lowercase, must start with a letter, max 50 chars.
     */
    private const string SLUG_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/';

    /**
     * GET /dashboard/lists
     */
    public function index(Request $request): Response
    {
        $lists = OptionSet::with('recipeInstance.recipe')
            ->where('user_id', $request->user()->id)
            ->orderBy('label')
            ->orderBy('slug')
            ->get()
            ->map(fn (OptionSet $os) => $this->serialize($os))
            ->values();

        return Inertia::render('dashboard/lists/index', [
            'lists' => $lists,
        ]);
    }

    /**
     * POST /dashboard/lists
     */
    public function store(Request $request): RedirectResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:'.self::SLUG_PATTERN,
                function ($attribute, $value, $fail) use ($userId) {
                    if (OptionSet::where('user_id', $userId)->where('slug', $value)->exists()) {
                        $fail("You already have a list with the slug '{$value}'.");
                    }
                },
            ],
            'label' => 'nullable|string|max:255',
            'items' => 'nullable|array',
            'items.*' => 'string',
        ]);

        $items = $this->sanitiseItems($validated['items'] ?? []);
        $list = OptionSet::create([
            'user_id' => $userId,
            'recipe_instance_id' => null,
            'slug' => $validated['slug'],
            'label' => $validated['label'] ?? null,
            'items' => $items,
            'item_added_at' => ListItemTimestamps::freshFor($items),
            'min_items' => 0,
            'max_items' => null,
            'user_editable' => true,
        ]);

        $this->broadcastUpdate($request->user()->twitch_id, $list);

        return back()->with('flash_list_id', $list->id);
    }

    /**
     * PUT /dashboard/lists/{list}
     */
    public function update(Request $request, OptionSet $list): RedirectResponse
    {
        $this->authorize($request, $list);

        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
            'items' => 'nullable|array',
            'items.*' => 'string',
            // Optional - toggling disabled is a one-click action from
            // the dashboard. When present, this PUT only flips the
            // disabled_at timestamp and ignores label/items even if
            // sent. Lets the UI fire a focused PATCH without having
            // to round-trip the items array.
            'disabled' => 'nullable|boolean',
            // Optional expiry-config PATCH. Same focused-update pattern
            // as `disabled` - when either field is present, items/label
            // are ignored and we only persist the TTL changes.
            // entry_ttl_seconds: per-item age-out, applied by the sweep
            // command. Null clears the TTL. Hard cap of 30 days because
            // anything longer should just be permanent.
            // expires_at: Unix seconds when the whole list should be
            // snapshot-cleared-disabled. Must be in the future when set.
            'entry_ttl_seconds' => 'sometimes|nullable|integer|min:10|max:2592000',
            'expires_at' => 'sometimes|nullable|integer|min:0',
            // Per-action chat permission overrides. Same focused-PATCH
            // pattern as `disabled` and the expiry fields. Keys must be
            // known actions; values must be known tier levels. Anything
            // matching the service-side default is dropped at save time
            // so the JSON stays minimal.
            'chat_permissions' => 'sometimes|array',
            'chat_permissions.*' => 'string|in:'.implode(',', array_keys(BotChatGate::TIER_ORDER)),
        ]);

        // Disable / enable is a stand-alone operation. Recipe-locked
        // lists CAN still be disabled by the owner - locking only
        // prevents item edits, not enable/disable state.
        if (array_key_exists('disabled', $validated) && $validated['disabled'] !== null) {
            $list->update([
                'disabled_at' => $validated['disabled'] ? now() : null,
            ]);
            $this->broadcastUpdate($request->user()->twitch_id, $list->fresh());

            return back()->with('flash_list_id', $list->id);
        }

        // Chat-permissions focused PATCH. Stored shape is the partial-
        // override map; keys that match the service-side default get
        // dropped so the JSON stays minimal (and an empty map persists
        // as NULL, signalling "everything default").
        if (array_key_exists('chat_permissions', $validated)) {
            $incoming = $validated['chat_permissions'] ?? [];
            $known = array_keys(ListActionService::ACTION_DEFAULTS);
            $stored = [];
            foreach ($incoming as $action => $level) {
                if (! in_array($action, $known, true)) {
                    continue;
                }
                if ($level === ListActionService::ACTION_DEFAULTS[$action]) {
                    continue;
                }
                $stored[$action] = $level;
            }
            $list->update(['chat_permissions' => $stored === [] ? null : $stored]);
            $this->broadcastUpdate($request->user()->twitch_id, $list->fresh());

            return back()->with('flash_list_id', $list->id);
        }

        // Expiry-config focused PATCH. Either field present alone is
        // enough to trigger this path. Items/label changes are not
        // bundled here - keep the surface single-purpose.
        $hasTtl = array_key_exists('entry_ttl_seconds', $validated);
        $hasExpires = array_key_exists('expires_at', $validated);
        if ($hasTtl || $hasExpires) {
            $updates = [];
            if ($hasTtl) {
                $updates['entry_ttl_seconds'] = $validated['entry_ttl_seconds'];
            }
            if ($hasExpires) {
                // expires_at clears -> re-enable a list that the sweeper
                // had previously expired. Streamers can set expires_at
                // to null to "reopen" a list, which feels more natural
                // than having to also click the Enable button.
                $expiresAt = $validated['expires_at'] !== null
                    ? \Illuminate\Support\Carbon::createFromTimestamp($validated['expires_at'])
                    : null;
                $updates['expires_at'] = $expiresAt;
                if ($expiresAt === null && $list->disabled_at !== null) {
                    $updates['disabled_at'] = null;
                }
            }
            $list->update($updates);
            $this->broadcastUpdate($request->user()->twitch_id, $list->fresh());

            return back()->with('flash_list_id', $list->id);
        }

        $newItems = $this->sanitiseItems($validated['items'] ?? []);

        // Recipe-installed lists may declare min/max bounds and a locked
        // flag. Refuse out-of-bounds edits with a clear error rather than
        // silently truncating - the streamer should see why their save
        // didn't take.
        if (! $list->user_editable && $list->recipe_instance_id !== null) {
            throw new HttpException(
                403,
                'This list is locked by the recipe that created it. Uninstall the recipe to edit it.'
            );
        }

        if ($list->min_items > 0 && count($newItems) < $list->min_items) {
            throw new HttpException(422, "This list requires at least {$list->min_items} items.");
        }

        if ($list->max_items !== null && count($newItems) > $list->max_items) {
            throw new HttpException(422, "This list allows at most {$list->max_items} items.");
        }

        // Preserve timestamps for items that match by value (oldest
        // match wins for duplicates); items removed lose their stamps;
        // new items get current time. So reordering doesn't reset the
        // entry-TTL clock, but renames or new entries do.
        $newTimestamps = ListItemTimestamps::preserveByValue(
            $list->items ?? [],
            $list->item_added_at ?? [],
            $newItems,
        );

        $list->update([
            'label' => $validated['label'] ?? $list->label,
            'items' => $newItems,
            'item_added_at' => $newTimestamps,
        ]);

        $this->broadcastUpdate($request->user()->twitch_id, $list->fresh());

        return back()->with('flash_list_id', $list->id);
    }

    /**
     * DELETE /dashboard/lists/{list}
     */
    public function destroy(Request $request, OptionSet $list): RedirectResponse
    {
        $this->authorize($request, $list);

        if ($list->recipe_instance_id !== null) {
            throw new HttpException(
                403,
                'This list is owned by a recipe. Uninstall the recipe to remove it.'
            );
        }

        $slug = $list->slug;
        $list->delete();

        ListUpdated::dispatch(
            (string) $request->user()->twitch_id,
            $slug,
            null,
            null,
        );

        return back();
    }

    private function authorize(Request $request, OptionSet $list): void
    {
        if ($list->user_id !== $request->user()->id) {
            throw new HttpException(404);
        }
    }

    /**
     * NUL-byte strip only. Items are otherwise preserved exactly as the
     * user submitted them - empties, duplicates, leading/trailing
     * whitespace, repeated values, all intentional.
     */
    private function sanitiseItems(array $items): array
    {
        return array_values(array_map(
            fn ($item) => str_replace("\0", '', (string) $item),
            $items
        ));
    }

    private function serialize(OptionSet $list): array
    {
        $recipe = $list->recipeInstance?->recipe;

        return [
            'id' => $list->id,
            'slug' => $list->slug,
            'label' => $list->label,
            'items' => $list->items ?? [],
            'min_items' => $list->min_items,
            'max_items' => $list->max_items,
            'user_editable' => $list->user_editable,
            'disabled_at' => $list->disabled_at?->timestamp,
            'entry_ttl_seconds' => $list->entry_ttl_seconds,
            'expires_at' => $list->expires_at?->timestamp,
            'recipe_instance_id' => $list->recipe_instance_id,
            'recipe' => $recipe ? [
                'slug' => $recipe->slug,
                'name' => $recipe->name,
                'version' => $recipe->version,
                'instance_slug' => $list->recipeInstance?->instance_slug,
            ] : null,
            'tag' => "[[[c:list:{$list->slug}]]]",
            'updated_at' => $list->updated_at?->timestamp,
            // Always send the fully-resolved map (defaults merged with
            // stored overrides) so the dashboard has a complete picture
            // without having to know the defaults itself.
            'chat_permissions' => $this->actions->resolveAllPermissions($list),
        ];
    }

    private function broadcastUpdate(?string $broadcasterId, OptionSet $list): void
    {
        ListUpdated::dispatchFor($broadcasterId, $list);
    }
}
