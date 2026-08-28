<?php

namespace App\Http\Controllers;

use App\Models\Update;
use App\Models\UpdateInteraction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The What's New card on the dashboard: which posts a user has not cleared,
 * which of those they have already been to, and the writes that move both.
 */
class WhatsNewController extends Controller
{
    /**
     * Rows shown before the card defers the rest to /updates.
     */
    private const int CARD_LIMIT = 5;

    /**
     * The `whatsNew` prop for the dashboard.
     *
     * `total` counts every entry still on the card, not just the rendered
     * ones, so the overflow line can say how many are waiting in /updates.
     * `canUndo` decides whether the caught-up bar appears at all: someone who
     * has never cleared anything gets no card and no bar rather than a
     * standing "all caught up" they never earned.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, canUndo: bool}
     */
    public static function props(User $user): array
    {
        $unseen = Update::query()
            ->unseenBy($user)
            ->with(['interactions' => fn ($q) => $q->where('user_id', $user->id)])
            ->get();

        return [
            'items' => $unseen->take(self::CARD_LIMIT)->map(fn (Update $update) => [
                'id' => $update->id,
                'title' => $update->title,
                'excerpt' => $update->excerpt,
                'published_at' => $update->published_at,
                'href' => route('updates.show', $update->slug),
                'cta' => $update->cta(),
                // Stale: the reader has already been where this points. It stays
                // on the card, in greys, until they clear it.
                'stale' => $update->interactions->first()?->visited_at !== null,
            ])->values()->all(),
            'total' => $unseen->count(),
            'canUndo' => UpdateInteraction::query()
                ->where('user_id', $user->id)
                ->whereNotNull('dismissed_at')
                ->exists(),
        ];
    }

    /**
     * Clear every entry currently on the card.
     *
     * Scoped to what this user can actually see right now rather than to a
     * list of ids from the browser, so a stale page cannot clear a post that
     * shipped after it was rendered.
     */
    public function markSeen(Request $request): RedirectResponse
    {
        $user = $request->user();
        $now = now();

        Update::query()
            ->unseenBy($user)
            ->pluck('id')
            ->each(fn (int $id) => UpdateInteraction::query()->updateOrCreate(
                ['user_id' => $user->id, 'update_id' => $id],
                ['dismissed_at' => $now],
            ));

        return back();
    }

    /**
     * Clear one entry.
     */
    public function dismiss(Request $request, Update $update): RedirectResponse
    {
        UpdateInteraction::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'update_id' => $update->id],
            ['dismissed_at' => now()],
        );

        return back();
    }

    /**
     * Mark one entry visited from the browser.
     *
     * Only needed for a CTA pointing somewhere outside the app: the reader
     * leaves, so no request of ours can observe the visit the way
     * MarkWhatsNewVisited does for an internal route.
     */
    public function markVisited(Request $request, Update $update): RedirectResponse
    {
        UpdateInteraction::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'update_id' => $update->id],
            ['visited_at' => now()],
        );

        return back();
    }

    /**
     * Undo the most recent clearing.
     *
     * Scoped to the newest batch rather than every entry the account has ever
     * cleared, because the button sits next to one press. Undoing months of
     * them would resurrect posts the reader deliberately got rid of.
     *
     * The row itself survives with `dismissed_at` nulled, so an entry that was
     * grey before it was cleared comes back grey.
     */
    public function undo(Request $request): RedirectResponse
    {
        $user = $request->user();

        $latest = UpdateInteraction::query()
            ->where('user_id', $user->id)
            ->max('dismissed_at');

        if ($latest !== null) {
            UpdateInteraction::query()
                ->where('user_id', $user->id)
                ->where('dismissed_at', $latest)
                ->update(['dismissed_at' => null]);
        }

        return back();
    }
}
