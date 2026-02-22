<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AdminAuditLog::with('admin:id,name');

        if ($adminId = $request->input('admin_id')) {
            $query->where('admin_id', $adminId);
        }

        if ($action = $request->input('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', $to);
        }

        $logs = $query->latest()->paginate(50)->withQueryString();

        return Inertia::render('admin/audit/index', [
            'logs' => $logs,
            'filters' => $request->only(['admin_id', 'action', 'from', 'to']),
        ]);
    }
}
