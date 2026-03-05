<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalEvent;
use App\Models\TwitchEvent;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTwitchEventController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(Request $request): Response
    {
        $source = $request->input('source', 'twitch');

        if ($source === 'external') {
            $query = ExternalEvent::with('user:id,name,twitch_id');

            if ($type = $request->input('event_type')) {
                $query->where('event_type', $type);
            }
            if ($userId = $request->input('user_id')) {
                $query->where('user_id', $userId);
            }
            if ($from = $request->input('from')) {
                $query->where('created_at', '>=', $from);
            }
            if ($to = $request->input('to')) {
                $query->where('created_at', '<=', $to);
            }

            $events = $query->latest()->paginate(50)->withQueryString();
            $eventTypes = ExternalEvent::distinct()->pluck('event_type')->sort()->values();

            return Inertia::render('admin/events/index', [
                'events' => $events,
                'filters' => $request->only(['event_type', 'user_id', 'from', 'to', 'source']),
                'eventTypes' => $eventTypes,
                'source' => 'external',
            ]);
        }

        $query = TwitchEvent::with('user:id,name,twitch_id');

        if ($type = $request->input('event_type')) {
            $query->where('event_type', $type);
        }

        if ($request->has('processed')) {
            $query->where('processed', $request->boolean('processed'));
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', $to);
        }

        $events = $query->latest()->paginate(50)->withQueryString();

        $eventTypes = TwitchEvent::distinct()->pluck('event_type')->sort()->values();

        return Inertia::render('admin/events/index', [
            'events' => $events,
            'filters' => $request->only(['event_type', 'processed', 'user_id', 'from', 'to', 'source']),
            'eventTypes' => $eventTypes,
            'source' => 'twitch',
        ]);
    }

    public function showExternal(ExternalEvent $externalEvent): Response
    {
        $externalEvent->load('user:id,name,twitch_id');

        return Inertia::render('admin/events/show-external', [
            'event' => $externalEvent,
        ]);
    }

    public function show(TwitchEvent $event): Response
    {
        $event->load('user:id,name,twitch_id');

        return Inertia::render('admin/events/show', [
            'event' => $event,
        ]);
    }

    public function update(Request $request, TwitchEvent $event): RedirectResponse
    {
        $request->validate(['processed' => 'required|boolean']);

        $event->update(['processed' => $request->boolean('processed')]);

        return back()->with('message', 'Event updated.');
    }

    public function prune(Request $request): RedirectResponse
    {
        $period = $request->input('period', '90');
        $source = $request->input('source', 'twitch');
        $model  = $source === 'external' ? ExternalEvent::class : TwitchEvent::class;

        $query = $model::query();
        if ($period !== 'all') {
            $query->where('created_at', '<', now()->subDays((int) $period));
        }

        $count = $query->count();
        $query->delete();

        $this->audit->log($request->user(), "{$source}_events.pruned", null, null, [
            'period' => $period,
            'deleted_count' => $count,
        ], $request);

        return back()->with('message', "Pruned {$count} {$source} event" . ($count === 1 ? '' : 's') . '.');
    }

    public function destroy(Request $request, TwitchEvent $event): RedirectResponse
    {
        $this->audit->log($request->user(), 'event.deleted', 'TwitchEvent', $event->id, [
            'event_type' => $event->event_type,
            'user_id' => $event->user_id,
        ], $request);

        $event->delete();

        return redirect()->route('admin.events.index')->with('message', 'Event deleted.');
    }
}
