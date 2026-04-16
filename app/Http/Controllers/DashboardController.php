<?php

namespace App\Http\Controllers;

use App\Models\ExternalEvent;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
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
            ->limit(5)
            ->get();

        $userStaticTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->static()
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(5)
            ->get();

        $communityTemplates = OverlayTemplate::where('owner_id', '!=', $user->id)
            ->where('is_public', true)
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(5)
            ->get();

        $userRecentEvents = $this->mergeRecentEvents($user->id, 5);

        return Inertia::render('dashboard/index', [
            'userId' => $user->id,
            'userAlertTemplates' => $userAlertTemplates,
            'userStaticTemplates' => $userStaticTemplates,
            'communityTemplates' => $communityTemplates,
            'userRecentEvents' => $userRecentEvents,
            'needsOnboarding' => $request->session()->pull('preview_onboarding', false) || (! $user->isOnboarded() && ! $user->hasAlertMappings()),
            'twitchId' => $user->twitch_id,
        ]);
    }

    public function recentActivity(Request $request): Response
    {
        $user = $request->user();

        $recentTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->with('owner:id,name,avatar')
            ->latest('updated_at')
            ->limit(20)
            ->get();

        $recentEvents = $this->mergeRecentEvents($user->id, 30);

        return Inertia::render('dashboard/recents', [
            'recentTemplates' => $recentTemplates,
            'recentEvents' => $recentEvents,
        ]);
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

    public function recentEvents(Request $request): Response
    {
        $user = $request->user();

        $events = $this->mergeRecentEvents($user->id, 50);

        return Inertia::render('dashboard/events', [
            'events' => $events,
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
                'created_at' => $e->created_at->toIso8601String(),
                'event_data' => $e->event_data,
                'normalized_payload' => null,
            ]);

        $externalEvents = ExternalEvent::where('user_id', $userId)
            ->where('service', '!=', 'overlabels-mobile')
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
