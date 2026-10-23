<?php

namespace App\Http\Controllers;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEvent;
use App\Models\OptionSet;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\TwitchEvent;
use App\Models\Update;
use App\Models\User;
use App\Services\AlertMuteService;
use App\Services\BroadcastMeter;
use App\Services\EventMeter;
use App\Services\UnifiedEventFeedService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $userAlertTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->alert()
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(10)
            ->get();

        $userStaticTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->static()
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(10)
            ->get();

        $communityTemplates = OverlayTemplate::where('owner_id', '!=', $user->id)
            ->where('is_public', true)
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(5)
            ->get();

        $userRecentEvents = $this->mergeRecentEvents($user->id, 20);

        $recentUpdates = Update::published()
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        // "New" is a state, not a signup date: an account with no product
        // installed has nothing on stream yet, however old it is. Overlays and
        // alerts cannot answer this - every signup gets the starter kit.
        $isNewUser = ! RecipeInstance::where('user_id', $user->id)->exists();

        return Inertia::render('dashboard/index', [
            'isNewUser' => $isNewUser,
            // A closure so a partial reload that does not ask for it cannot
            // spend the dialog on a render that never shows it.
            'showWelcomeDialog' => fn () => $isNewUser && $this->claimWelcomeDialog($user),
            'userId' => $user->id,
            'userAlertTemplates' => $userAlertTemplates,
            'userStaticTemplates' => $userStaticTemplates,
            'communityTemplates' => $communityTemplates,
            'userRecentEvents' => $userRecentEvents,
            'recentUpdates' => $recentUpdates,
            'whatsNew' => WhatsNewController::props($user),
            'usage' => config('metering.meter_mode', 'both') === 'broadcast'
                ? ($user->twitch_id ? app(BroadcastMeter::class)->summaryFor((string) $user->twitch_id) : null)
                : app(EventMeter::class)->summaryFor($user->id),
        ]);
    }

    /**
     * How long a product-less account goes before the welcome dialog opens
     * again. The dashboard card stays regardless; this only decides whether
     * the dialog interrupts. Someone who signed up, left and came back months
     * later without installing anything is greeted like a first-timer.
     */
    private const int WELCOME_DIALOG_REPEAT_SECONDS = 30 * 24 * 60 * 60;

    /**
     * Whether this render opens the welcome dialog, recording it if so. Shown
     * once, not on every visit: the card on the page carries the same push
     * for anyone who closes the dialog unread.
     */
    private function claimWelcomeDialog(User $user): bool
    {
        $shownAt = $user->preference('welcome_shown_at');

        if ($shownAt !== null && now()->timestamp - (int) $shownAt < self::WELCOME_DIALOG_REPEAT_SECONDS) {
            return false;
        }

        $user->setPreference('welcome_shown_at', now()->timestamp)->save();

        return true;
    }

    public function recentActivity(Request $request, UnifiedEventFeedService $eventFeed): Response
    {
        $user = $request->user();

        $filters = $eventFeed->normalizeFilters($request);
        $paginator = $eventFeed->paginate($user->id, $filters, 20)->withQueryString();

        // Only the feed itself responds to a filter change, and search runs on
        // every keystroke batch. The rest is deferred behind closures so a
        // partial reload that asks for `recentEvents` alone does not also pay
        // for the template list, the facet counts and the user's lists.
        return Inertia::render('dashboard/recents', [
            'recentTemplates' => fn () => OverlayTemplate::where('owner_id', $user->id)
                ->with('owner:id,name,avatar')
                ->latest('updated_at')
                ->limit(20)
                ->get(),
            'recentEvents' => $paginator,
            'filters' => $filters,
            'facets' => fn () => $eventFeed->facets($user->id),
            'userLists' => fn () => $this->eventFeedLists($user->id),
        ]);
    }

    /**
     * The user's lists, with their recent-events feed config, for the recents
     * page picker. Locked recipe lists are excluded - a feed appends items,
     * which a locked list forbids.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventFeedLists(int $userId): array
    {
        return OptionSet::where('user_id', $userId)
            ->where('user_editable', true)
            ->orderBy('label')
            ->orderBy('slug')
            ->get()
            ->map(function (OptionSet $list) {
                $items = $list->items ?? [];
                $count = count($items);

                return [
                    'id' => $list->id,
                    'slug' => $list->slug,
                    'label' => $list->label,
                    'max_items' => $list->max_items,
                    'disabled' => $list->disabled_at !== null,
                    'feed_enabled' => $list->eventFeedEnabled(),
                    'feed_types' => $list->eventFeedTypes(),
                    // Concrete proof the feed is working: how many lines have
                    // landed and the most recent one. On a dev box (no live
                    // broadcast) these update on Refresh after an event fires.
                    'items_count' => $count,
                    'last_item' => $count > 0 ? ($items[$count - 1]['value'] ?? null) : null,
                ];
            })
            ->values()
            ->all();
    }

    public function recentActivityDashboard(Request $request): Response
    {
        $user = $request->user();

        $recentTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->with('owner:id,name,avatar')
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $recentEvents = $this->mergeRecentEvents($user->id, 30);

        return Inertia::render('dashboard/index', [
            'recentTemplates' => $recentTemplates,
            'recentEvents' => $recentEvents,
        ]);
    }

    public function recentEvents(Request $request, UnifiedEventFeedService $eventFeed): Response
    {
        $user = $request->user();

        $filters = $eventFeed->normalizeFilters($request);
        $paginator = $eventFeed->paginate($user->id, $filters, 25)->withQueryString();
        $facets = $eventFeed->facets($user->id);

        return Inertia::render('dashboard/events', [
            'events' => $paginator,
            'filters' => $filters,
            'facets' => $facets,
            'alertsMuted' => app(AlertMuteService::class)->isMuted($user),
        ]);
    }

    /**
     * Merge Twitch and external events into a unified shape, sorted newest-first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mergeRecentEvents(int $userId, int $limit): array
    {
        $twitchEvents = TwitchEvent::where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (TwitchEvent $e) => [
                'id' => $e->id,
                'source' => 'twitch',
                'event_type' => $e->event_type,
                'label' => EventTemplateMapping::EVENT_TYPES[$e->event_type] ?? $e->event_type,
                'created_at' => $e->created_at->toIso8601String(),
                'event_data' => $e->event_data,
                'normalized_payload' => null,
            ]);

        $externalEvents = ExternalEvent::where('user_id', $userId)
            ->where('service', '!=', 'gps')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (ExternalEvent $e) => [
                'id' => $e->id,
                'source' => $e->service,
                'event_type' => $e->event_type,
                'created_at' => $e->created_at->toIso8601String(),
                'event_data' => null,
                'normalized_payload' => $e->normalized_payload,
            ]);

        return $twitchEvents
            ->concat($externalEvents)
            ->sortByDesc('created_at')
            ->values()
            ->take($limit)
            ->all();
    }
}
