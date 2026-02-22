<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OverlayAccessLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAccessLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = OverlayAccessLog::with(['token:id,user_id,name,token_prefix', 'token.user:id,name,twitch_id']);

        if ($slug = $request->input('template_slug')) {
            $query->where('template_slug', $slug);
        }

        if ($from = $request->input('from')) {
            $query->where('accessed_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->where('accessed_at', '<=', $to);
        }

        $logs = $query->orderByDesc('accessed_at')->paginate(50)->withQueryString();

        return Inertia::render('admin/logs/index', [
            'logs' => $logs,
            'filters' => $request->only(['template_slug', 'from', 'to']),
        ]);
    }
}
