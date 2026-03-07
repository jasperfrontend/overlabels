<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Mchev\Banhammer\Models\Ban;
use Stevebauman\Location\Facades\Location;

class AdminSessionController extends Controller
{
    public function index(Request $request): Response
    {
        $sessions = DB::table('sessions')
            ->orderByDesc('last_activity')
            ->paginate(50);

        // Attach user info
        $userIds = collect($sessions->items())->pluck('user_id')->filter()->unique();
        $users = User::whereIn('id', $userIds)->get(['id', 'name', 'email', 'twitch_id'])->keyBy('id');

        // Check ban status in bulk
        $bannedUserIds = $userIds->isNotEmpty()
            ? User::whereIn('id', $userIds)->banned()->pluck('id')->toArray()
            : [];

        $ips = collect($sessions->items())->pluck('ip_address')->filter()->unique()->values()->toArray();
        $bannedIps = ! empty($ips)
            ? Ban::whereIn('ip', $ips)->notExpired()->pluck('ip')->toArray()
            : [];

        $items = collect($sessions->items())->map(function ($session) use ($users, $bannedUserIds, $bannedIps) {
            $session->user = $session->user_id ? $users->get($session->user_id) : null;
            $session->last_activity_human = date('Y-m-d H:i:s', $session->last_activity);
            $session->is_user_banned = in_array($session->user_id, $bannedUserIds);
            $session->is_ip_banned = in_array($session->ip_address, $bannedIps);

            return $session;
        });

        return Inertia::render('admin/sessions/index', [
            'sessions' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'total' => $sessions->total(),
                    'per_page' => $sessions->perPage(),
                ],
                'links' => $sessions->linkCollection(),
            ],
        ]);
    }

    public function ipLookup(Request $request, string $ip): JsonResponse
    {
        $position = Location::get($ip);

        if (! $position || $position->isEmpty()) {
            return response()->json(['error' => 'Could not resolve location for this IP address.'], 404);
        }

        return response()->json($position->toArray());
    }

    public function destroy(Request $request, string $session): RedirectResponse
    {
        DB::table('sessions')->where('id', $session)->delete();

        return back()->with('message', 'Session invalidated.');
    }
}
