<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OverlayReport;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(Request $request): Response
    {
        $query = OverlayReport::with([
            'reporter:id,name,twitch_id',
            'reviewer:id,name',
            'template:id,slug,is_public',
        ]);

        $status = $request->input('status', 'open');
        if (in_array($status, [OverlayReport::STATUS_OPEN, OverlayReport::STATUS_READ], true)) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhere('template_name', 'like', "%{$search}%")
                    ->orWhere('template_slug', 'like', "%{$search}%")
                    ->orWhere('reporter_email', 'like', "%{$search}%");
            });
        }

        $reports = $query->latest()->paginate(50)->withQueryString();

        $reports->through(fn (OverlayReport $report) => [
            'id' => $report->id,
            'reason' => $report->reason,
            'status' => $report->status,
            'ip_address' => $report->ip_address,
            'created_at' => $report->created_at,
            'reviewed_at' => $report->reviewed_at,
            'reviewed_by' => $report->reviewer?->name,
            'reporter' => [
                'label' => $report->reporterLabel(),
                'user_id' => $report->reporter_user_id,
                // Anonymous reporters are unverified: the email was typed into
                // a public form, never confirmed. The admin table says so.
                'is_authenticated' => $report->reporter_user_id !== null,
            ],
            'template' => [
                'name' => $report->template_name,
                'slug' => $report->template_slug,
                // Null once the overlay is deleted. The snapshot above still
                // says what the report was about.
                'id' => $report->overlay_template_id,
                'url' => $report->overlay_template_id
                    ? route('overlay.public', $report->template_slug)
                    : null,
                'admin_url' => $report->overlay_template_id
                    ? route('admin.templates.show', $report->overlay_template_id)
                    : null,
                'is_public' => $report->template?->is_public ?? false,
            ],
        ]);

        return Inertia::render('admin/reports/index', [
            'reports' => $reports,
            'filters' => $request->only(['status', 'search']),
            'stats' => [
                'open' => OverlayReport::where('status', OverlayReport::STATUS_OPEN)->count(),
                'read' => OverlayReport::where('status', OverlayReport::STATUS_READ)->count(),
            ],
        ]);
    }

    public function update(Request $request, OverlayReport $report): RedirectResponse
    {
        $admin = $this->resolveAdmin($request);

        $validated = $request->validate([
            'status' => 'required|in:open,read',
        ]);

        $isRead = $validated['status'] === OverlayReport::STATUS_READ;

        $report->update([
            'status' => $validated['status'],
            'reviewed_at' => $isRead ? now() : null,
            'reviewed_by_id' => $isRead ? $admin->id : null,
        ]);

        $this->audit->log($admin, 'report.status_changed', 'OverlayReport', $report->id, [
            'status' => $validated['status'],
            'template_slug' => $report->template_slug,
        ], $request);

        return back()->with('message', $isRead ? 'Report marked as read.' : 'Report reopened.');
    }

    public function destroy(Request $request, OverlayReport $report): RedirectResponse
    {
        $admin = $this->resolveAdmin($request);

        // The reason is copied into the audit log before the row goes: the
        // audit log is the append-only record of what admins acted on, and a
        // deleted report should not vanish from it without a trace.
        $this->audit->log($admin, 'report.deleted', 'OverlayReport', $report->id, [
            'template_slug' => $report->template_slug,
            'template_name' => $report->template_name,
            'reason' => $report->reason,
            'reporter' => $report->reporterLabel(),
        ], $request);

        $report->delete();

        return back()->with('message', 'Report deleted.');
    }

    private function resolveAdmin(Request $request): User
    {
        // During impersonation, get the real admin
        if ($realAdminId = $request->session()->get('real_admin_id')) {
            return User::findOrFail($realAdminId);
        }

        return $request->user();
    }
}
