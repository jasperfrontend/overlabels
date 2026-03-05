<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OverlayAccessToken;
use App\Services\LockdownService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminLockdownController extends Controller
{
    public function __construct(private readonly LockdownService $lockdown) {}

    public function index(): Response
    {
        $status = $this->lockdown->getStatus();

        return Inertia::render('admin/Lockdown', [
            'lockdown' => $status,
            'totalTokens' => OverlayAccessToken::count(),
            'activeTokens' => OverlayAccessToken::where('is_active', true)->count(),
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->lockdown->isActive()) {
            return back()->with(['message' => 'System is already in lockdown.', 'type' => 'warning']);
        }

        $this->lockdown->activate($request->user(), $request, $request->input('reason', ''));

        return redirect()->route('admin.lockdown.index')
            ->with(['message' => 'Lockdown engaged. All overlays are now offline.', 'type' => 'success']);
    }

    public function deactivate(Request $request): RedirectResponse
    {
        if (! $this->lockdown->isActive()) {
            return back()->with(['message' => 'System is not in lockdown.', 'type' => 'warning']);
        }

        $this->lockdown->deactivate($request->user(), $request);

        return redirect()->route('admin.lockdown.index')
            ->with(['message' => 'Lockdown lifted. Access tokens restored.', 'type' => 'success']);
    }
}
