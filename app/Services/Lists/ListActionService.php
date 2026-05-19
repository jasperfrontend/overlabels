<?php

namespace App\Services\Lists;

use App\Events\ListUpdated;
use App\Models\ListSnapshot;
use App\Models\OptionSet;
use App\Models\User;
use App\Support\BotChatGate;
use App\Support\ListItemTimestamps;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Action runner behind the `!list` meta-command + the dashboard action
 * buttons. Parses raw args ("[slug] [action] [args...]"), validates,
 * runs the action, creates a snapshot before destructive ops, and
 * returns a chat-friendly reply string the caller writes to
 * bot_chat_outbox or surfaces in the dashboard UI.
 *
 * Self-documentation is built in: bare `!list`, `!list <slug>`, and
 * unknown actions all reply with help instead of erroring silently.
 *
 * Permission gating and bot routing lives in the controller; this
 * service assumes the caller has already verified the invoker is
 * allowed to run actions.
 */
class ListActionService
{
    /**
     * Hard cap on chat replies. Twitch chat allows 500 chars; we leave
     * some slack for the bot's "@user " prefix it may add and any
     * trailing emoji.
     */
    private const int MAX_REPLY_CHARS = 400;

    private const array ACTIONS = [
        'draw', 'clear', 'disable', 'enable', 'pop', 'clone',
        'count', 'first', 'last', 'random', 'search', 'searchall',
    ];

    /**
     * Default permission level per chat-callable action. Anything not in
     * the action list (help, slug-only, unknown action) is always allowed
     * - those are informational replies. Streamers override these per
     * list via OptionSet->chat_permissions; the merged map is what
     * resolvePermission returns.
     *
     * Defaults intentionally lock everything to moderator+ to preserve
     * the pre-migration behaviour. The dashboard checkbox UI exposes a
     * binary everyone/moderator toggle today; the stored shape is full
     * permission level strings so a richer UI (sub/vip) can land later
     * without a migration.
     */
    public const array ACTION_DEFAULTS = [
        'count' => 'moderator',
        'first' => 'moderator',
        'last' => 'moderator',
        'random' => 'moderator',
        'search' => 'moderator',
        'searchall' => 'moderator',
        'pop' => 'moderator',
        'draw' => 'moderator',
        'clear' => 'moderator',
        'clone' => 'moderator',
        'disable' => 'moderator',
        'enable' => 'moderator',
    ];

    /**
     * Entry point. $rawArgs is everything the chatter typed after the
     * `!list` command (or whatever the streamer renamed it to). $invoker
     * is the user who typed it (mod or broadcaster) - used for the
     * "@user" prefix in error messages; pass the streamer themselves
     * when invoked via the dashboard buttons.
     *
     * $badges is the chatter's lowercased IRC badges. Pass `null` to
     * bypass the per-action permission gate entirely - that's the
     * dashboard-buttons path, where the request has already been
     * authorised as the owner of the list. From chat, always pass the
     * real badges array (even an empty array for everyone-tier viewers).
     *
     * Returns the reply string the caller should surface (write to
     * bot_chat_outbox for chat, toast/return in JSON for dashboard).
     * @throws Throwable
     */
    public function handleInvocation(
        User $owner,
        string $rawArgs,
        string $invokerDisplayName = '',
        ?array $badges = null,
    ): string {
        $tokens = preg_split('/\s+/', trim($rawArgs)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn ($t) => $t !== ''));

        if ($tokens === []) {
            return $this->helpMessage($invokerDisplayName);
        }

        $slug = strtolower(array_shift($tokens));

        /** @var OptionSet|null $list */
        $list = OptionSet::where('user_id', $owner->id)->where('slug', $slug)->first();
        if (! $list) {
            return $this->mention($invokerDisplayName)."no list named '$slug'. Check your lists at /dashboard/lists.";
        }

        if ($tokens === []) {
            return $this->listHelpMessage($invokerDisplayName, $slug);
        }

        $action = strtolower(array_shift($tokens));

        // Per-action permission gate. Unknown actions fall through to
        // the help message below - we don't gate "did the chatter spell
        // the action right" behind a permission check, because that's
        // an informational reply and gating it would be confusing.
        if ($badges !== null && array_key_exists($action, self::ACTION_DEFAULTS)) {
            $required = $this->resolvePermission($list, $action);
            if (! BotChatGate::hasPermission($required, $badges)) {
                return $this->mention($invokerDisplayName)."'$action' on '$list->slug' is $required+ only.";
            }
        }

        return match ($action) {
            'count' => $this->actionCount($list),
            'first' => $this->actionFirst($list, $tokens),
            'last' => $this->actionLast($list, $tokens),
            'random' => $this->actionRandom($list, $tokens),
            'clear' => $this->actionClear($owner, $list),
            'disable' => $this->actionDisable($owner, $list),
            'enable' => $this->actionEnable($owner, $list),
            'draw' => $this->actionDraw($owner, $list),
            'pop' => $this->actionPop($owner, $list, $tokens, $invokerDisplayName),
            'clone' => $this->actionClone($owner, $list, $tokens, $invokerDisplayName),
            'search' => $this->actionSearch($list, $tokens, $invokerDisplayName),
            'searchall' => $this->actionSearchAll($list, $tokens, $invokerDisplayName),
            default => $this->unknownActionMessage($invokerDisplayName, $action),
        };
    }

    /**
     * Merge the list's stored chat_permissions over ACTION_DEFAULTS and
     * return the required permission level for $action. Unknown actions
     * (informational helpers, typos) return 'everyone' so callers that
     * route through here don't accidentally block help text.
     */
    public function resolvePermission(OptionSet $list, string $action): string
    {
        if (! array_key_exists($action, self::ACTION_DEFAULTS)) {
            return 'everyone';
        }

        $stored = $list->chat_permissions ?? [];
        $level = $stored[$action] ?? self::ACTION_DEFAULTS[$action];

        // Defensive: if a stored value somehow drifts out of the known
        // tier set, fall back to the default rather than letting an
        // invalid level slip through (BotChatGate would just floor it
        // to 'everyone', which is the wrong direction for safety).
        return array_key_exists($level, BotChatGate::TIER_ORDER)
            ? $level
            : self::ACTION_DEFAULTS[$action];
    }

    /**
     * Merge stored overrides over defaults so the dashboard always
     * receives a complete map (every action has an explicit level).
     * Used by ListController::serialize.
     *
     * @return array<string, string>
     */
    public function resolveAllPermissions(OptionSet $list): array
    {
        $stored = $list->chat_permissions ?? [];
        $out = [];
        foreach (self::ACTION_DEFAULTS as $action => $default) {
            $level = $stored[$action] ?? $default;
            $out[$action] = array_key_exists($level, BotChatGate::TIER_ORDER)
                ? $level
                : $default;
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────
    // Help messages
    // ─────────────────────────────────────────────────────────────────

    private function helpMessage(string $invokerName): string
    {
        return $this->mention($invokerName).'List actions: draw, clear, disable, enable, pop first|last, clone <slug>, count, first [N], last [N], random [N], search <keyword>, searchall <keyword>. Usage: !list <slug> <action>';
    }

    private function listHelpMessage(string $invokerName, string $slug): string
    {
        return $this->mention($invokerName)."Actions for '$slug': draw, clear, disable, enable, pop first|last, clone <slug>, count, first [N], last [N], random [N], search <keyword>, searchall <keyword>";
    }

    private function unknownActionMessage(string $invokerName, string $action): string
    {
        return $this->mention($invokerName)."'$action' isn't a valid action. Try: ".implode(', ', self::ACTIONS);
    }

    private function mention(string $invokerName): string
    {
        return $invokerName === '' ? '' : "@$invokerName - ";
    }

    // ─────────────────────────────────────────────────────────────────
    // Read actions (no mutation, no snapshot, no broadcast)
    // ─────────────────────────────────────────────────────────────────

    private function actionCount(OptionSet $list): string
    {
        $count = count($list->items ?? []);

        return $count === 0
            ? "'$list->slug' is empty."
            : "'$list->slug' has $count ".($count === 1 ? 'entry.' : 'entries.');
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function actionFirst(OptionSet $list, array $tokens): string
    {
        return $this->actionSliceRead($list, $tokens, 'first');
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function actionLast(OptionSet $list, array $tokens): string
    {
        return $this->actionSliceRead($list, $tokens, 'last');
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function actionRandom(OptionSet $list, array $tokens): string
    {
        $items = array_values($list->items ?? []);
        if ($items === []) {
            return "'$list->slug' is empty.";
        }

        $n = $this->parseCountToken($tokens, count($items));
        $keys = array_rand($items, min($n, count($items)));
        $keys = is_array($keys) ? $keys : [$keys];
        $picked = array_map(static fn ($k) => $items[$k], $keys);

        $label = count($picked) === 1
            ? "Random from '$list->slug': "
            : count($picked).' from \''.$list->slug.'\': ';

        return $this->truncate($label.implode(', ', $picked));
    }

    /**
     * Shared first/last reader.
     *
     * @param  array<int, string>  $tokens
     */
    private function actionSliceRead(OptionSet $list, array $tokens, string $which): string
    {
        $items = array_values($list->items ?? []);
        if ($items === []) {
            return "'$list->slug' is empty.";
        }

        $n = $this->parseCountToken($tokens, count($items));
        $slice = $which === 'first'
            ? array_slice($items, 0, $n)
            : array_slice($items, -$n);

        $label = $n === 1
            ? ucfirst($which)." of '$list->slug': "
            : ucfirst($which)." $n of '$list->slug': ";

        return $this->truncate($label.implode(', ', $slice));
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function parseCountToken(array $tokens, int $max): int
    {
        if ($tokens === []) {
            return 1;
        }
        $raw = $tokens[0];
        if (! ctype_digit($raw)) {
            return 1;
        }
        $n = max(1, (int) $raw);

        return min($n, $max);
    }

    // ─────────────────────────────────────────────────────────────────
    // Search actions (case-insensitive substring, newest-first)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Top match, searching from newest entry to oldest. Newest = end of
     * the items array, which is append-order (chat appends push there,
     * dashboard edits preserve order).
     *
     * @param  array<int, string>  $tokens
     */
    private function actionSearch(OptionSet $list, array $tokens, string $invokerName): string
    {
        if ($tokens === []) {
            return $this->mention($invokerName)."search needs a keyword: !list $list->slug search <keyword>";
        }

        $items = array_values($list->items ?? []);
        if ($items === []) {
            return "'$list->slug' is empty.";
        }

        $keyword = implode(' ', $tokens);

        // Iterate newest-first. stripos handles case-insensitivity and
        // multibyte-safe-enough for typical chat content (Twitch chat is
        // mostly ASCII anyway).
        foreach (array_reverse($items) as $item) {
            if (stripos((string) $item, $keyword) !== false) {
                return $this->truncate("First search results for '$keyword' in list '$list->slug', searched from new to old: $item");
            }
        }

        return "No matches for '$keyword' in '$list->slug'.";
    }

    /**
     * All matches with [N] prefix, newest-first. Truncates at the
     * MAX_REPLY_CHARS boundary - drops trailing matches and appends a
     * "(+N more)" suffix so the reader knows there's more.
     *
     * @param  array<int, string>  $tokens
     */
    private function actionSearchAll(OptionSet $list, array $tokens, string $invokerName): string
    {
        if ($tokens === []) {
            return $this->mention($invokerName)."searchall needs a keyword: !list $list->slug searchall <keyword>";
        }

        $items = array_values($list->items ?? []);
        if ($items === []) {
            return "'$list->slug' is empty.";
        }

        $keyword = implode(' ', $tokens);

        $matches = [];
        foreach (array_reverse($items) as $item) {
            if (stripos((string) $item, $keyword) !== false) {
                $matches[] = (string) $item;
            }
        }

        if ($matches === []) {
            return "No matches for '$keyword' in '$list->slug'.";
        }

        $prefix = "All search results for '$keyword' in '$list->slug': ";
        $body = $prefix;
        $included = 0;
        foreach ($matches as $i => $m) {
            $chunk = ($i === 0 ? '' : ' ').'['.($i + 1).']'.$m;
            if (mb_strlen($body.$chunk) > self::MAX_REPLY_CHARS) {
                break;
            }
            $body .= $chunk;
            $included++;
        }

        // The very-first-match-too-long edge case: fall back to the
        // ellipsis truncate helper so we never return a bare prefix.
        if ($included === 0) {
            return $this->truncate($prefix.'[1]'.$matches[0]);
        }

        $omitted = count($matches) - $included;
        if ($omitted > 0) {
            $body .= " (+$omitted more)";
        }

        return $body;
    }

    // ─────────────────────────────────────────────────────────────────
    // Destructive actions (snapshot before, broadcast after)
    // ─────────────────────────────────────────────────────────────────

    /**
     * @throws Throwable
     */
    private function actionClear(User $owner, OptionSet $list): string
    {
        $items = $list->items ?? [];
        if ($items === []) {
            return "'$list->slug' was already empty.";
        }

        return DB::transaction(function () use ($owner, $list, $items) {
            $locked = OptionSet::lockForUpdate()->find($list->id);
            $count = count($locked->items ?? []);
            $this->snapshot($locked, ListSnapshot::REASON_BEFORE_CLEAR, $owner->id);
            $locked->update(['items' => [], 'item_added_at' => []]);
            $this->broadcast($owner, $locked->fresh());

            return "Cleared '$locked->slug' ($count ".($count === 1 ? 'entry' : 'entries').' archived to snapshot).';
        });
    }

    /**
     * @throws Throwable
     */
    private function actionDraw(User $owner, OptionSet $list): string
    {
        $items = array_values($list->items ?? []);
        if ($items === []) {
            return "Can't draw - '$list->slug' is empty.";
        }

        return DB::transaction(function () use ($owner, $list) {
            $locked = OptionSet::lockForUpdate()->find($list->id);
            $current = array_values($locked->items ?? []);
            if ($current === []) {
                return "Can't draw - '$locked->slug' is empty.";
            }
            $this->snapshot($locked, ListSnapshot::REASON_BEFORE_DRAW, $owner->id);
            $winnerIdx = array_rand($current);
            $winner = $current[$winnerIdx];
            unset($current[$winnerIdx]);
            $newTimestamps = ListItemTimestamps::removeAt($locked->item_added_at ?? [], $winnerIdx);
            $locked->update([
                'items' => array_values($current),
                'item_added_at' => $newTimestamps,
            ]);
            $this->broadcast($owner, $locked->fresh());

            return "🎰 Winner of '$locked->slug': $winner";
        });
    }

    /**
     * @param array<int, string> $tokens
     * @throws Throwable
     */
    private function actionPop(User $owner, OptionSet $list, array $tokens, string $invokerName): string
    {
        if ($tokens === []) {
            return $this->mention($invokerName)."pop needs first or last: !list $list->slug pop first  OR  !list $list->slug pop last";
        }

        $which = strtolower($tokens[0]);
        if ($which !== 'first' && $which !== 'last') {
            return $this->mention($invokerName)."pop needs first or last (got '$which'): !list $list->slug pop first  OR  !list $list->slug pop last";
        }

        return DB::transaction(function () use ($owner, $list, $which) {
            $locked = OptionSet::lockForUpdate()->find($list->id);
            $current = array_values($locked->items ?? []);
            if ($current === []) {
                return "Can't pop - '$locked->slug' is empty.";
            }
            $this->snapshot($locked, ListSnapshot::REASON_BEFORE_POP, $owner->id);

            $oldTimestamps = $locked->item_added_at ?? [];
            if ($which === 'first') {
                $popped = array_shift($current);
                $newTimestamps = ListItemTimestamps::removeAt($oldTimestamps, 0);
            } else {
                $popped = array_pop($current);
                $newTimestamps = ListItemTimestamps::removeAt($oldTimestamps, count($oldTimestamps) - 1);
            }

            $locked->update([
                'items' => $current,
                'item_added_at' => $newTimestamps,
            ]);
            $this->broadcast($owner, $locked->fresh());

            return "Popped $which from '$locked->slug': $popped";
        });
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function actionClone(User $owner, OptionSet $list, array $tokens, string $invokerName): string
    {
        if ($tokens === []) {
            return $this->mention($invokerName)."clone needs a new slug: !list $list->slug clone <new_slug>";
        }

        $newSlug = strtolower($tokens[0]);
        if (! preg_match(OptionSet::SLUG_PATTERN, $newSlug)) {
            return $this->mention($invokerName)."'$newSlug' isn't a valid slug. Use lowercase letters, digits, underscores; start with a letter.";
        }

        if (OptionSet::where('user_id', $owner->id)->where('slug', $newSlug)->exists()) {
            return $this->mention($invokerName)."you already have a list named '$newSlug'. Pick a different slug.";
        }

        $count = count($list->items ?? []);
        $newList = OptionSet::create([
            'user_id' => $owner->id,
            'recipe_instance_id' => null,
            'slug' => $newSlug,
            // Inherit the parent's label verbatim. The streamer already
            // picked a distinct slug for the clone; auto-prefixing
            // "Copy of" is condescending and creates a rename chore.
            // If they want a different display label, the lists page
            // has a label input.
            'label' => $list->label,
            'items' => $list->items ?? [],
            // Inherit item timestamps too - the clone is intended as a
            // snapshot, so per-entry ages carry over. If the streamer
            // is using entry-TTL on the parent and clones mid-stream,
            // the clone inherits the same "time-left" for each entry.
            'item_added_at' => $list->item_added_at ?? [],
            'min_items' => 0,
            'max_items' => null,
            'user_editable' => true,
        ]);

        // Broadcast the new list's contents so the dashboard / overlays
        // pick it up live. (The list-index page will need its own poll
        // to see new lists appearing, but the contents are correct.)
        ListUpdated::dispatchFor((string) $owner->twitch_id, $newList);

        return "Cloned '$list->slug' to '$newSlug' ($count ".($count === 1 ? 'item' : 'items').').';
    }

    // ─────────────────────────────────────────────────────────────────
    // State actions (toggle disabled_at, broadcast)
    // ─────────────────────────────────────────────────────────────────

    private function actionDisable(User $owner, OptionSet $list): string
    {
        if ($list->disabled_at !== null) {
            return "'$list->slug' is already disabled.";
        }
        $list->update(['disabled_at' => Carbon::now()]);
        $this->broadcast($owner, $list->fresh());

        return "Disabled '$list->slug'. Chat appenders will silently no-op until re-enabled.";
    }

    private function actionEnable(User $owner, OptionSet $list): string
    {
        if ($list->disabled_at === null) {
            return "'$list->slug' is already enabled.";
        }
        $list->update(['disabled_at' => null]);
        $this->broadcast($owner, $list->fresh());

        return "Enabled '$list->slug'.";
    }

    // ─────────────────────────────────────────────────────────────────
    // Snapshot + broadcast helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * Create a snapshot of the list's CURRENT state, before the
     * destructive mutation. Caller is expected to be inside the
     * lockForUpdate transaction.
     */
    public function snapshot(OptionSet $list, string $reason, ?int $triggeredByUserId): ListSnapshot
    {
        return ListSnapshot::create([
            'list_id' => $list->id,
            'items' => array_values($list->items ?? []),
            'reason' => $reason,
            'triggered_by_user_id' => $triggeredByUserId,
            'pinned' => false,
            'created_at' => now(),
        ]);
    }

    private function broadcast(User $owner, OptionSet $list): void
    {
        ListUpdated::dispatchFor((string) $owner->twitch_id, $list);
    }

    /**
     * Cap chat-bound reply strings so they don't exceed Twitch's 500-char
     * limit (with slack for the bot's @user prefix).
     */
    private function truncate(string $reply): string
    {
        if (mb_strlen($reply) <= self::MAX_REPLY_CHARS) {
            return $reply;
        }

        return mb_substr($reply, 0, self::MAX_REPLY_CHARS - 1).'…';
    }
}
