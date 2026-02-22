<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\OverlayTemplate;
use App\Models\TwitchEvent;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'users' => User::count(),
            'templates' => OverlayTemplate::count(),
            'events' => TwitchEvent::count(),
            'pending_events' => TwitchEvent::where('processed', false)->count(),
        ];

        $recentSignups = User::where('is_system_user', false)
            ->latest()
            ->limit(10)
            ->get(['id', 'name', 'email', 'twitch_id', 'role', 'created_at']);

        $recentAuditLogs = AdminAuditLog::with('admin:id,name')
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('admin/Dashboard', [
            'stats' => $stats,
            'recentSignups' => $recentSignups,
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }
}
