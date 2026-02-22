<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            'filters' => $request->only(['event_type', 'processed', 'user_id', 'from', 'to']),
            'eventTypes' => $eventTypes,
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
