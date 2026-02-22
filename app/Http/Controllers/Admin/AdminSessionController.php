<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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

        $items = collect($sessions->items())->map(function ($session) use ($users) {
            $session->user = $session->user_id ? $users->get($session->user_id) : null;
            $session->last_activity_human = date('Y-m-d H:i:s', $session->last_activity);

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

    public function destroy(Request $request, string $session): RedirectResponse
    {
        DB::table('sessions')->where('id', $session)->delete();

        return back()->with('message', 'Session invalidated.');
    }
}
