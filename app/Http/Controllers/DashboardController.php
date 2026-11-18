<?php

namespace App\Http\Controllers;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEvent;
use App\Models\OptionSet;
use App\Models\OverlayTemplate;
use App\Models\RecipeInstance;
use App\Models\StreamSession;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\AlertMuteService;
use App\Services\Recipes\RecipeCatalog;
use App\Services\UnifiedEventFeedService;
use App\Support\ProductSetup;
use App\Support\WiringCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // "New" is a state, not a signup date: an account with no product
        // installed has nothing on stream yet, however old it is. Overlays and
        // alerts cannot answer this - every signup gets the starter kit.
        $isNewUser = ! RecipeInstance::where('user_id', $user->id)->exists();
        $installedProducts = $isNewUser ? [] : $this->installedProducts($user->id);

        // The dashboard is a lobby, not an inventory: who you are, the ONE
        // thing worth doing next, what is on your stream, and what happened
        // lately. Lists of overlays and alerts live on their own pages.
        return Inertia::render('dashboard/index', [
            'isNewUser' => $isNewUser,
            'installedProducts' => $installedProducts,
            'nextUp' => $isNewUser ? null : $this->nextUp($installedProducts),
            // A closure so a partial reload that does not ask for it cannot
            // spend the dialog on a render that never shows it.
            'showWelcomeDialog' => fn () => $isNewUser && $this->claimWelcomeDialog($user),
            'lastStream' => $this->lastStream($user->id),
            'recentEvents' => $this->mergeRecentEvents($user->id, 5),
            'whatsNew' => WhatsNewController::props($user),
        ]);
    }

    /**
     * The one thing the dashboard points at. In order: an installed product
     * that is not finished setting up (the newest first), then a game the
     * account has not tried, then nothing - which the page shows as "all set".
     * Never a list: a tired streamer gets one door, not a menu.
     *
     * @param  list<array<string, mixed>>  $installed
     * @return array<string, mixed>|null
     */
    private function nextUp(array $installed): ?array
    {
        foreach ($installed as $product) {
            if ($product['remaining'] > 0) {
                return [
                    'kind' => 'finish',
                    'slug' => $product['slug'],
                    'name' => $product['name'],
                    'hero' => $product['hero'],
                    'remaining' => $product['remaining'],
                    'todo' => $product['todo'],
                    'href' => route('products.manage', $product['slug']),
                ];
            }
        }

        $installedSlugs = array_column($installed, 'slug');
        $game = collect(app(RecipeCatalog::class)->listed())
            ->filter(fn (array $manifest) => ($manifest['category'] ?? null) === 'product')
            ->reject(fn (array $manifest) => in_array($manifest['slug'], $installedSlugs, true))
            ->first();

        if ($game === null) {
            return null;
        }

        return [
            'kind' => 'try',
            'slug' => $game['slug'],
            'name' => $game['name'],
            'hero' => $game['hero'] ?? null,
            'description' => $game['description'],
            'href' => '/products/'.$game['slug'],
        ];
    }

    /**
     * The latest stream session, live or ended, with how many moments landed
     * in it. Counted by time window over created_at, not by the session FK:
     * events arriving during a live stream are never stamped with it.
     *
     * @return array{started_at: string, ended_at: ?string, minutes: int, moments: int, live: bool}|null
     */
    private function lastStream(int $userId): ?array
    {
        $session = StreamSession::where('user_id', $userId)->latest('started_at')->first();

        if ($session === null || $session->started_at === null) {
            return null;
        }

        $end = $session->ended_at ?? now();
        $window = fn ($query) => $query->where('user_id', $userId)->whereBetween('created_at', [$session->started_at, $end]);

        $moments = $window(TwitchEvent::query())
            ->whereNotIn('event_type', ['stream.online', 'stream.offline'])
            ->count()
            + $window(ExternalEvent::query())->where('service', '!=', 'gps')->count();

        return [
            'started_at' => $session->started_at->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'minutes' => (int) $session->started_at->diffInMinutes($end),
            'moments' => $moments,
            'live' => $session->ended_at === null,
        ];
    }

    /**
     * The listed products this account has installed, newest install first,
     * for the dashboard's products box and tab. A product taken off the
     * listing has no page to manage it from, so it is left out here too -
     * same rule as the Installed count on /products.
     *
     * `remaining` is how many setup steps are still missing (the same checklist
     * the product page shows) and `todo` the first of them, in words.
     *
     * @return list<array{slug: string, name: string, description: string, hero: string|null, service: string|null, category: string|null, remaining: int, todo: string|null}>
     */
    private function installedProducts(int $userId): array
    {
        $listed = app(RecipeCatalog::class)->listed();

        return RecipeInstance::with('recipe')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->unique(fn (RecipeInstance $instance) => $instance->recipe?->slug)
            ->filter(fn (RecipeInstance $instance) => isset($listed[$instance->recipe?->slug]))
            ->map(function (RecipeInstance $instance) use ($listed) {
                $slug = $instance->recipe->slug;
                $missing = array_values(array_filter(
                    ProductSetup::steps($instance),
                    fn (array $wire) => $wire['state'] === WiringCatalog::MISSING,
                ));
                $first = $missing[0] ?? null;

                return [
                    'slug' => $slug,
                    'name' => $listed[$slug]['name'],
                    'description' => $listed[$slug]['description'],
                    'hero' => $listed[$slug]['hero'] ?? null,
                    'service' => $listed[$slug]['installs']['integrations'][0] ?? null,
                    'category' => $listed[$slug]['category'] ?? null,
                    'game' => (bool) ($listed[$slug]['game'] ?? false),
                    'remaining' => count($missing),
                    'todo' => $first === null ? null : (ProductSetup::STEPS[$first['key']]['todo'] ?? strtolower($first['label'])),
                ];
            })
            ->values()
            ->all();
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
