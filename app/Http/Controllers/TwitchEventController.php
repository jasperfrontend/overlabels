<?php

namespace App\Http\Controllers;

use App\Models\TwitchEvent;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwitchEventController extends Controller
{
    /**
     * Display a listing of the Twitch events.
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function index(Request $request)
    {
        try {
            $query = TwitchEvent::query();

            // Filter by event type if provided
            if ($request->has('type')) {
                $query->ofType($request->type);
            }

            // Filter by processed status if provided
            if ($request->has('processed')) {
                $processed = filter_var($request->processed, FILTER_VALIDATE_BOOLEAN);
                $query->where('processed', $processed);
            }

            // Order by the most recent first
            $query->orderBy('created_at', 'desc');

            // Paginate results
            $events = $query->paginate($request->per_page ?? 15);

            return response()->json($events);
        } catch (Exception $e) {
            Log::error('Failed to retrieve Twitch events: '.$e->getMessage());

            return response()->json(['error' => 'Failed to retrieve events'], 500);
        }
    }

    /**
     * Display the specified Twitch event.
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function show(int $id)
    {
        try {
            $event = TwitchEvent::findOrFail($id);

            return response()->json($event);
        } catch (Exception $e) {
            Log::error('Failed to retrieve Twitch event: '.$e->getMessage());

            return response()->json(['error' => 'Event not found'], 404);
        }
    }

    /**
     * Mark an event as processed.
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function markAsProcessed(int $id)
    {
        try {
            $event = TwitchEvent::findOrFail($id);
            $event->markAsProcessed();

            return response()->json([
                'success' => true,
                'message' => 'Event marked as processed',
                'event' => $event,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to mark event as processed: '.$e->getMessage());

            return response()->json(['error' => 'Failed to update event'], 500);
        }
    }

    /**
     * Mark multiple events as processed.
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function batchMarkAsProcessed(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer',
            ]);

            $count = TwitchEvent::whereIn('id', $request->ids)
                ->update(['processed' => true]);

            return response()->json([
                'success' => true,
                'message' => "$count events marked as processed",
            ]);
        } catch (Exception $e) {
            Log::error('Failed to batch mark events as processed: '.$e->getMessage());

            return response()->json(['error' => 'Failed to update events'], 500);
        }
    }

    /**
     * Remove the specified event from storage.
     *
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function destroy(int $id)
    {
        try {
            $event = TwitchEvent::findOrFail($id);
            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete Twitch event: '.$e->getMessage());

            return response()->json(['error' => 'Failed to delete event'], 500);
        }
    }
}
